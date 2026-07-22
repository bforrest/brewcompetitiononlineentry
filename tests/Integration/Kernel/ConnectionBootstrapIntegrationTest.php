<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration\Kernel;

use PHPUnit\Framework\TestCase;
use Bcoem\Database\Connection;

/**
 * Proves Connection::class resolves for a route that never touched any
 * Bcoem\Legacy\* handler in this same request - the exact gap that let
 * every pure-Slim Phase 3 route (Entry, Judging, Export, and the new
 * Registration routes) crash with "mysqli connection not initialized"
 * the first time it was ever hit without a legacy page running first.
 */
class ConnectionBootstrapIntegrationTest extends TestCase
{
    /**
     * paths.php unconditionally runs ini_set() for these three settings
     * before session_start() (error_reporting, display_errors, log_errors),
     * and those calls succeed silently - unlike the session-related ini_set
     * calls elsewhere in paths.php, which already fail loudly with a PHP
     * warning when a session is active. Left unrestored, they'd leak into
     * whatever Integration test PHPUnit happens to run next in this same
     * process (executionOrder="depends,defects" doesn't guarantee this test
     * runs last), silently suppressing E_DEPRECATED and display_errors for
     * tests that never asked for that.
     */
    private string|false $originalErrorReporting;
    private string|false $originalDisplayErrors;
    private string|false $originalLogErrors;

    protected function setUp(): void
    {
        $this->originalErrorReporting = ini_get('error_reporting');
        $this->originalDisplayErrors = ini_get('display_errors');
        $this->originalLogErrors = ini_get('log_errors');

        // Simulate the exact "never touched legacy" state: no prior
        // require of paths.php/config.php in this process, no globals set.
        unset($GLOBALS['connection'], $GLOBALS['prefix'], $GLOBALS['database']);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    protected function tearDown(): void
    {
        if (isset($GLOBALS['connection']) && $GLOBALS['connection'] instanceof \mysqli) {
            $GLOBALS['connection']->close();
        }
        unset($GLOBALS['connection'], $GLOBALS['prefix'], $GLOBALS['database']);

        if ($this->originalErrorReporting !== false) {
            ini_set('error_reporting', $this->originalErrorReporting);
        }
        if ($this->originalDisplayErrors !== false) {
            ini_set('display_errors', $this->originalDisplayErrors);
        }
        if ($this->originalLogErrors !== false) {
            ini_set('log_errors', $this->originalLogErrors);
        }
    }

    public function test_connection_class_bootstraps_from_scratch(): void
    {
        $container = require dirname(__DIR__, 3) . '/src/Kernel/container.php';

        $connection = $container->get(Connection::class);

        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertTrue(isset($GLOBALS['connection']) && $GLOBALS['connection'] instanceof \mysqli);
        $this->assertSame('baseline_', $GLOBALS['prefix']);

        // Prove it's a live, usable connection, not just a truthy value.
        $rows = $connection->select('SELECT 1 as one');
        $this->assertSame(1, (int) $rows[0]['one']);
    }
}
