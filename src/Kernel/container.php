<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
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

    return $logger;
};

$containerBuilder->addDefinitions([
    'logger.app'      => static fn (): Logger => $logChannel('app'),
    'logger.security' => static fn (): Logger => $logChannel('security'),
    'logger.legacy'   => static fn (): Logger => $logChannel('legacy'),
    // Default PSR-3 logger for any new code that type-hints LoggerInterface.
    LoggerInterface::class => \DI\get('logger.app'),
]);

return $containerBuilder->build();
