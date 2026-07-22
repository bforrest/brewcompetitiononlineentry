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
    protected function setUp(): void
    {
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
