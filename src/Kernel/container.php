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
     * The legacy $GLOBALS['connection'] (mysqli) is set by site/config.php
     * during bootstrap. This wrapper enforces prepared statements only
     * and serves as the single point of database access for all Phase 3+
     * code (repositories, services, etc.).
     */
    Connection::class => static function (): Connection {
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
            new \Symfony\Component\Validator\Validator\RecursiveValidator(
                new \Symfony\Component\Validator\Mapping\ClassMetadataFactory(
                    new \Symfony\Component\Validator\Mapping\Loader\AttributeLoader()
                ),
                []
            ),
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
     * Phase 3.3: AdminPreferences domain services and repositories.
     * Task 2: DI Container Wiring
     *
     * Dependency hierarchy:
     * - AdminPreferencesRepository: lowest level, database access only (depends on Connection)
     * - PreferencesValidationService: pure logic, no dependencies
     * - StyleCatalogService: style lookups (depends on Connection)
     * - AdminPreferencesService: orchestration layer (depends on all above)
     */
    AdminPreferencesRepository::class => static fn (ContainerInterface $container): AdminPreferencesRepository =>
        new AdminPreferencesRepository($container->get(Connection::class)),

    PreferencesValidationService::class => static fn (): PreferencesValidationService =>
        new PreferencesValidationService(),

    StyleCatalogService::class => static fn (ContainerInterface $container): StyleCatalogService =>
        new StyleCatalogService($container->get(Connection::class)),

    AdminPreferencesService::class => static fn (ContainerInterface $container): AdminPreferencesService =>
        new AdminPreferencesService(
            $container->get(AdminPreferencesRepository::class),
            $container->get(PreferencesValidationService::class),
            $container->get(StyleCatalogService::class),
        ),
]);

return $containerBuilder->build();
