<?php

declare(strict_types=1);

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Kernel/bootstrap_errors.php';

class LegacyErrorLoggingTest extends TestCase
{
    public function test_reported_warning_is_logged_to_the_legacy_channel_and_falls_through(): void
    {
        $log = new TestHandler();
        $handler = bcoem_legacy_error_logger(new Logger('legacy', [$log]));

        // PHPUnit narrows error_reporting() for the test run; force the full
        // mask so this severity counts as "reported" (the branch under test).
        $previous = error_reporting();
        error_reporting(E_ALL);
        try {
            // Returning false lets PHP's own (display_errors=Off) handling
            // continue, so we never alter the app's established warning posture
            // - we only record an inventory entry.
            $result = $handler(E_USER_WARNING, 'Undefined array key "foo"', '/app/legacy.php', 42);
        } finally {
            error_reporting($previous);
        }

        $this->assertFalse($result);
        $this->assertTrue($log->hasWarningRecords());
        $records = $log->getRecords();
        $this->assertStringContainsString('Undefined array key', $records[0]->message);
        $this->assertSame('/app/legacy.php', $records[0]->context['file']);
        $this->assertSame(42, $records[0]->context['line']);
    }

    public function test_suppressed_error_is_not_logged(): void
    {
        $log = new TestHandler();
        $handler = bcoem_legacy_error_logger(new Logger('legacy', [$log]));

        // Simulate the @-operator / error_reporting mask: severity bit is off.
        $previous = error_reporting();
        error_reporting($previous & ~E_USER_NOTICE);
        try {
            $result = $handler(E_USER_NOTICE, 'noise', '/app/legacy.php', 1);
        } finally {
            error_reporting($previous);
        }

        $this->assertFalse($result);
        $this->assertFalse($log->hasRecords(\Monolog\Level::Warning));
        $this->assertCount(0, $log->getRecords());
    }
}
