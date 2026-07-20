<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;

/**
 * Kernel error-logging bootstrap.
 *
 * The un-migrated legacy library emits a steady stream of benign PHP
 * warnings/notices (undefined array keys, implicit conversions, ...). Rather
 * than touch each of the hundreds of call sites, a single set_error_handler
 * routes them into Monolog's `legacy` channel - a live inventory of latent
 * defects to burn down in later phases, NOT a build-breaking gate. The handler
 * deliberately returns false so PHP's own error handling (display_errors=Off in
 * production) still runs afterwards: we observe, we do not change the app's
 * established warning posture.
 *
 * Registered only from the production front controller (index.php), never from
 * buildApp() or the test bootstrap, so it cannot interfere with PHPUnit's own
 * warning-to-failure conversion.
 */

if (!function_exists('bcoem_legacy_error_logger')) {
    /**
     * Build (but do not register) the legacy error handler callable.
     *
     * @return callable(int,string,string,int):bool
     */
    function bcoem_legacy_error_logger(LoggerInterface $legacyLogger): callable
    {
        return static function (
            int $severity,
            string $message,
            string $file = '',
            int $line = 0,
        ) use ($legacyLogger): bool {
            // Respect the @-operator and the active error_reporting() mask:
            // if this severity is currently suppressed, record nothing.
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            $legacyLogger->warning($message, [
                'severity' => $severity,
                'file'     => $file,
                'line'     => $line,
            ]);

            // Fall through to PHP's default handler (does not short-circuit it).
            return false;
        };
    }
}

if (!function_exists('bcoem_register_error_logging')) {
    /**
     * Register the legacy warning/notice capture on the given channel.
     */
    function bcoem_register_error_logging(LoggerInterface $legacyLogger): void
    {
        set_error_handler(bcoem_legacy_error_logger($legacyLogger));
    }
}
