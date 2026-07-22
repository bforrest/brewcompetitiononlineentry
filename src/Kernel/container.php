<?php

declare(strict_types=1);

use Bcoem\Database\Connection;
use Bcoem\Domain\AdminPreferences\Repository\AdminPreferencesRepository;
use Bcoem\Domain\AdminPreferences\Service\AdminPreferencesService;
use Bcoem\Domain\AdminPreferences\Service\PreferencesValidationService;
use Bcoem\Domain\AdminPreferences\Service\StyleCatalogService;
use Bcoem\Domain\Entry\Repository\EntryRepository;
use Bcoem\Domain\Entry\Service\AuditLogger;
use Bcoem\Domain\Entry\Service\EntryService;
use Bcoem\Domain\Entry\Service\EntryValidationService;
use Bcoem\Domain\Entry\Service\StyleService;
use Bcoem\Domain\Judging\Repository\JudgingScoreRepository;
use Bcoem\Domain\Judging\Repository\JudgingTableRepository;
use Bcoem\Domain\Judging\Service\JudgingScoreService;
use Bcoem\Domain\Judging\Service\JudgingTableService;
use Bcoem\Domain\Judging\Service\JudgingValidationService;
use Bcoem\Domain\Export\Repository\BrewingExportRepository;
use Bcoem\Domain\Export\Repository\ParticipantExportRepository;
use Bcoem\Domain\Export\Repository\JudgingExportRepository;
use Bcoem\Domain\Export\Service\ExportService;
use Bcoem\Domain\Export\Service\ExportValidationService;
use Bcoem\Domain\Export\Service\ExportFormatterService;
use Bcoem\Domain\Registration\Repository\RegistrationRepository;
use Bcoem\Domain\Registration\Service\CaptchaVerifier;
use Bcoem\Domain\Registration\Service\GoogleRecaptchaVerifier;
use Bcoem\Domain\Registration\Service\HCaptchaVerifier;
use Bcoem\Domain\Registration\Service\NullCaptchaVerifier;
use Bcoem\Domain\Registration\Service\RegistrationService;
use GuzzleHttp\Client;
use Bcoem\Kernel\Logging\TraceIdProcessor;
use DI\ContainerBuilder;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Make mysqli's exception-throwing error mode EXPLICIT.
 *
 * PHP 8.1+ already defaults to MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT, so
 * this line does not change current runtime behavior. It exists so a future
 * PHP downgrade or a stray `mysqli.report_mode` ini override cannot silently
 * revert mysqli to the old "return false" behavior - which would quietly
 * resurrect the legacy `or die(mysqli_error())` idiom (and the info-disclosure
 * it leaked). With this mode on, every failed query throws mysqli_sql_exception
 * and is caught centrally by the Slim ErrorMiddleware (see src/Kernel/app.php),
 * never printed raw to the client.
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * PHP-DI container. Legacy globals (mysqli connection, table prefix) are
 * NOT wired here - src/Legacy/ reads them directly from $GLOBALS, exactly
 * as legacy code always has. Only genuinely new (Phase 3+) services get
 * container entries.
 */
$containerBuilder = new ContainerBuilder();

/**
 * Monolog channels. Each channel is a distinct Logger instance sharing one
 * output stream (BCOEM_LOG_FILE, default php://stderr so records surface via
 * `docker compose logs web` in the container and the web server's error log
 * on shared hosting):
 *   - app      general application/kernel errors (the ErrorMiddleware handler)
 *   - security authorization denials and login success/failure
 *   - legacy   PHP warnings/notices from the un-migrated legacy code, captured
 *              by the kernel set_error_handler as a live latent-defect inventory
 */
$logChannel = static function (string $channel): Logger {
    $stream = getenv('BCOEM_LOG_FILE') ?: 'php://stderr';
    $handler = new StreamHandler($stream, Level::Debug);
    // allowInlineLineBreaks=true keeps multi-line stack traces readable;
    // ignoreEmptyContextAndExtra=true drops noisy empty [] {} tails.
    $handler->setFormatter(new LineFormatter(null, null, true, true));

    $logger = new Logger($channel);
    $logger->pushHandler($handler);
    $logger->pushProcessor(new PsrLogMessageProcessor());
    // Task 12: stamps the active OTel trace ID (if any) onto every record,
    // so a request's logs and its Jaeger trace can be correlated. A no-op
    // outside a traced HTTP request (see TraceIdProcessor's own docblock).
    $logger->pushProcessor(new TraceIdProcessor());

    return $logger;
};

$containerBuilder->addDefinitions([
    'logger.app'      => static fn (): Logger => $logChannel('app'),
    'logger.security' => static fn (): Logger => $logChannel('security'),
    'logger.legacy'   => static fn (): Logger => $logChannel('legacy'),
    // Default PSR-3 logger for any new code that type-hints LoggerInterface.
    LoggerInterface::class => \DI\get('logger.app'),

    /**
     * OpenTelemetry tracer (Task 12). Deliberately NOT a manually-built
     * TracerProvider/exporter here - Globals::tracerProvider() resolves to
     * whatever open-telemetry/sdk's own env-driven autoloader configured
     * (OTEL_PHP_AUTOLOAD_ENABLED + OTEL_* env vars, set only for real Apache
     * requests - see docker-compose.yml/docker/apache/vhost.conf), which is
     * the SAME global TracerProvider instance the mysqli auto-instrumentation
     * package uses. Building a second, separate SDK instance here would give
     * this container's spans and the auto-instrumented mysqli spans two
     * different trace pipelines that could never nest into one trace.
     * Outside that env (CLI/PHPUnit/PHPStan, or a shared-hosting deploy with
     * no OTEL_PHP_AUTOLOAD_ENABLED and no native extension), this resolves to
     * the API's built-in no-op TracerProvider - every call below becomes a
     * free no-op, same as every other binding in this file being unaffected
     * by tracing.
     */
    'tracer' => static fn (): TracerInterface => Globals::tracerProvider()->getTracer('bcoem'),
    TracerInterface::class => \DI\get('tracer'),

    /**
     * Phase 3: Database connection wrapper for prepared statements.
     *
     * Bootstraps the legacy $GLOBALS['connection']/$GLOBALS['prefix'] on
     * demand for any pure-Slim route (Entry, Judging, Export, Registration)
     * that never touches a Bcoem\Legacy\* handler in the same request -
     * nothing else in this pipeline ever sets them (index.php deliberately
     * never eagerly requires paths.php; legacy handlers only ever set these
     * as LOCAL variables inside their own handler method's scope, never as
     * true globals - see index.php's own docblock). Declaring `global`
     * before the require is what makes paths.php's plain `$connection = ...`
     * assignment land in $GLOBALS instead of this closure's local scope.
     * A no-op whenever a prior IntegrationTestCase (or a future legacy-then-
     * modern request chain) already set $GLOBALS['connection'].
     */
    Connection::class => static function (): Connection {
        if (!isset($GLOBALS['connection']) || !($GLOBALS['connection'] instanceof \mysqli)) {
            global $connection, $prefix, $database, $hostname, $username, $password,
                   $database_port, $brewing, $base_url, $sub_directory, $installation_id,
                   $session_expire_after, $setup_free_access, $server_root, $prefix_session,
                   $db_conn, $current_version, $current_version_display,
                   $current_version_display_append, $current_version_date_display,
                   $current_version_date, $public_captcha_key, $private_captcha_key;
            require ROOT . 'paths.php';
        }

        if (isset($GLOBALS['connection']) && $GLOBALS['connection'] instanceof \mysqli) {
            return new Connection($GLOBALS['connection']);
        }
        throw new \RuntimeException('mysqli connection not initialized in $GLOBALS[\'connection\']');
    },

    /**
     * Phase 3: Entry domain services and repositories.
     */
    AuditLogger::class => static fn (ContainerInterface $container): AuditLogger =>
        new AuditLogger($container->get(Connection::class)),

    EntryRepository::class => static fn (ContainerInterface $container): EntryRepository =>
        new EntryRepository($container->get(Connection::class)),

    StyleService::class => static fn (): StyleService =>
        new StyleService(),

    EntryValidationService::class => static fn (ContainerInterface $container): EntryValidationService =>
        new EntryValidationService(
            $container->get(EntryRepository::class),
            (new \Symfony\Component\Validator\ValidatorBuilder())
                ->addLoader(new \Symfony\Component\Validator\Mapping\Loader\AttributeLoader())
                ->getValidator(),
            $container->get(StyleService::class),
        ),

    EntryService::class => static fn (ContainerInterface $container): EntryService =>
        new EntryService(
            $container->get(Connection::class),
            $container->get(EntryRepository::class),
            $container->get(EntryValidationService::class),
            $container->get(AuditLogger::class),
            $container->get(LoggerInterface::class),
        ),

    /**
     * Phase 3.2: Judging domain services and repositories.
     * Dependency hierarchy:
     * - JudgingTableRepository, JudgingScoreRepository: database access (depend on Connection)
     * - JudgingValidationService: pure logic (no dependencies)
     * - JudgingTableService: table/flight management (depends on JudgingTableRepository + validation)
     * - JudgingScoreService: score recording (depends on both repositories + validation)
     */
    JudgingTableRepository::class => static fn (ContainerInterface $container): JudgingTableRepository =>
        new JudgingTableRepository($container->get(Connection::class)),

    JudgingScoreRepository::class => static fn (ContainerInterface $container): JudgingScoreRepository =>
        new JudgingScoreRepository($container->get(Connection::class)),

    JudgingValidationService::class => static fn (): JudgingValidationService =>
        new JudgingValidationService(),

    JudgingTableService::class => static fn (ContainerInterface $container): JudgingTableService =>
        new JudgingTableService(
            $container->get(JudgingTableRepository::class),
            $container->get(JudgingValidationService::class),
        ),

    JudgingScoreService::class => static fn (ContainerInterface $container): JudgingScoreService =>
        new JudgingScoreService(
            $container->get(JudgingScoreRepository::class),
            $container->get(JudgingTableRepository::class),
            $container->get(JudgingValidationService::class),
        ),

    /**
     * Phase 3.3: AdminPreferences domain services and repositories.
     * Task 2: DI Container Wiring
     *
     * Dependency hierarchy:
     * - AdminPreferencesRepository: lowest level, database access only (depends on Connection)
     * - PreferencesValidationService: pure logic + command validation (depends on Validator)
     * - StyleCatalogService: style lookups (depends on Connection)
     * - AdminPreferencesService: orchestration layer (depends on all above)
     */
    AdminPreferencesRepository::class => static fn (ContainerInterface $container): AdminPreferencesRepository =>
        new AdminPreferencesRepository($container->get(Connection::class)),

    PreferencesValidationService::class => static fn (): PreferencesValidationService =>
        new PreferencesValidationService(
            (new \Symfony\Component\Validator\ValidatorBuilder())
                ->addLoader(new \Symfony\Component\Validator\Mapping\Loader\AttributeLoader())
                ->getValidator(),
        ),

    StyleCatalogService::class => static fn (ContainerInterface $container): StyleCatalogService =>
        new StyleCatalogService($container->get(Connection::class)),

    AdminPreferencesService::class => static fn (ContainerInterface $container): AdminPreferencesService =>
        new AdminPreferencesService(
            $container->get(AdminPreferencesRepository::class),
            $container->get(PreferencesValidationService::class),
            $container->get(StyleCatalogService::class),
        ),

    /**
     * Phase 3.4: Export domain services and repositories.
     * Dependency hierarchy:
     * - BrewingExportRepository, ParticipantExportRepository, JudgingExportRepository: database access (depend on Connection)
     * - ExportValidationService: validation logic (depends on Validator)
     * - ExportService: orchestration (depends on repositories + validation)
     * - ExportFormatterService: output formatting (no dependencies)
     */
    BrewingExportRepository::class => static fn (ContainerInterface $container): BrewingExportRepository =>
        new BrewingExportRepository($container->get(Connection::class)),

    ParticipantExportRepository::class => static fn (ContainerInterface $container): ParticipantExportRepository =>
        new ParticipantExportRepository($container->get(Connection::class)),

    JudgingExportRepository::class => static fn (ContainerInterface $container): JudgingExportRepository =>
        new JudgingExportRepository($container->get(Connection::class)),

    ExportValidationService::class => static fn (): ExportValidationService =>
        new ExportValidationService(
            (new \Symfony\Component\Validator\ValidatorBuilder())
                ->addLoader(new \Symfony\Component\Validator\Mapping\Loader\AttributeLoader())
                ->getValidator()
        ),

    ExportService::class => static fn (ContainerInterface $container): ExportService =>
        new ExportService(
            $container->get(BrewingExportRepository::class),
            $container->get(ParticipantExportRepository::class),
            $container->get(JudgingExportRepository::class),
            $container->get(ExportValidationService::class),
        ),

    ExportFormatterService::class => static fn (): ExportFormatterService =>
        new ExportFormatterService(),

    /**
     * Phase 3.7: Registration domain services and repositories.
     * CaptchaVerifier binds by prefsCAPTCHA (0 -> Null, matching legacy's own
     * bypass and docker/03-e2e-fixtures.sql's prefsCAPTCHA=0 setting), else
     * by $_SESSION['prefsGoogleAccount']'s third pipe-delimited field (type
     * 2 = hCaptcha, else reCAPTCHA v2) - ports
     * process_users_register.inc.php:29-89's own branching exactly.
     */
    RegistrationRepository::class => static fn (ContainerInterface $container): RegistrationRepository =>
        new RegistrationRepository($container->get(Connection::class)),

    CaptchaVerifier::class => static function (): CaptchaVerifier {
        if ((int) ($_SESSION['prefsCAPTCHA'] ?? 0) === 0) {
            return new NullCaptchaVerifier();
        }

        $accountParts = explode('|', (string) ($_SESSION['prefsGoogleAccount'] ?? ''));
        $secretKey = $accountParts[1] ?? '';
        $type = getenv('HOSTED') === 'true' ? '2' : ($accountParts[2] ?? '1');
        $client = new Client();

        if ($type === '2') {
            return new HCaptchaVerifier($client, $secretKey);
        }
        return new GoogleRecaptchaVerifier($client, $secretKey, $_SERVER['SERVER_NAME'] ?? '');
    },

    RegistrationService::class => static fn (ContainerInterface $container): RegistrationService =>
        new RegistrationService(
            $container->get(RegistrationRepository::class),
            $container->get(CaptchaVerifier::class),
        ),
]);

return $containerBuilder->build();
