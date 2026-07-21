<?php
declare(strict_types=1);

namespace Tests\Integration\Domain\Judging;

use Bcoem\Database\Connection;
use Bcoem\Domain\Judging\Repository\JudgingTableRepository;
use Bcoem\Domain\Judging\Service\JudgingTableService;
use Bcoem\Domain\Judging\Service\JudgingValidationService;
use Bcoem\Domain\Judging\ValueObject\LocationId;
use Bcoem\Domain\Judging\ValueObject\TableId;
use Bcoem\Domain\Judging\ValueObject\TableState;
use Bcoem\Kernel\Identity;
use Bcoem\Kernel\Security\User;
use Bcoem\Kernel\Security\Role;
use DateTime;
use PHPUnit\Framework\TestCase;

class JudgingTableServiceIntegrationTest extends TestCase
{
    private Connection $connection;
    private JudgingTableService $service;
    private JudgingTableRepository $repository;
    private string $tablePrefix = 'test_';
    private Identity $testAdmin;

    protected function setUp(): void
    {
        if (!getenv('DB_TEST_HOST')) {
            $this->markTestSkipped('No test database configured');
        }

        $mysqli = new \mysqli(
            getenv('DB_TEST_HOST') ?: 'localhost',
            getenv('DB_TEST_USER') ?: 'root',
            getenv('DB_TEST_PASSWORD') ?: '',
            getenv('DB_TEST_NAME') ?: 'bcoem_test'
        );

        if ($mysqli->connect_error) {
            $this->markTestSkipped('Could not connect to test database');
        }

        $this->connection = new Connection($mysqli);
        $this->repository = new JudgingTableRepository($this->connection, $this->tablePrefix);
        $validationService = new JudgingValidationService();
        $this->service = new JudgingTableService($this->repository, $validationService);

        $user = new User(id: 1, email: 'admin@test.local', name: 'Admin User');
        $this->testAdmin = new Identity($user, [Role::Admin]);

        $this->cleanupTestTables();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestTables();
    }

    private function cleanupTestTables(): void
    {
        try {
            $this->connection->execute("DELETE FROM {$this->tablePrefix}judging_tables", []);
        } catch (\Throwable) {
            // Table may not exist
        }
    }

    public function testCreateTable(): void
    {
        $tableId = $this->service->createTable(
            'Integration Test Table',
            new LocationId(1),
            15,
            $this->testAdmin
        );

        $this->assertGreaterThan(0, $tableId->value());

        $table = $this->service->getTable($tableId);

        $this->assertEquals('Integration Test Table', $table->name());
        $this->assertEquals(15, $table->entryLimit());
        $this->assertEquals(TableState::Planning, $table->state());
    }

    public function testListTablesByLocation(): void
    {
        $location = new LocationId(2);

        $id1 = $this->service->createTable('Table 1', $location, 10, $this->testAdmin);
        $id2 = $this->service->createTable('Table 2', $location, 12, $this->testAdmin);
        $id3 = $this->service->createTable('Table 3', new LocationId(3), 10, $this->testAdmin);

        $tables = $this->service->listTablesByLocation($location);

        $this->assertCount(2, $tables);
        $this->assertEquals('Table 1', $tables[0]->name());
        $this->assertEquals('Table 2', $tables[1]->name());
    }

    public function testListTablesByLocationAndState(): void
    {
        $location = new LocationId(4);

        $id1 = $this->service->createTable('Table Active', $location, 10, $this->testAdmin);
        $id2 = $this->service->createTable('Table Planning', $location, 10, $this->testAdmin);

        $this->service->transitionTableState($id1, TableState::Active, $this->testAdmin);

        $activeTables = $this->service->listTablesByLocationAndState($location, TableState::Active);
        $planningTables = $this->service->listTablesByLocationAndState($location, TableState::Planning);

        $this->assertCount(1, $activeTables);
        $this->assertCount(1, $planningTables);
        $this->assertEquals('Table Active', $activeTables[0]->name());
        $this->assertEquals('Table Planning', $planningTables[0]->name());
    }

    public function testTransitionTableState(): void
    {
        $tableId = $this->service->createTable('State Test', new LocationId(5), 10, $this->testAdmin);

        $table = $this->service->getTable($tableId);
        $this->assertEquals(TableState::Planning, $table->state());

        $this->service->transitionTableState($tableId, TableState::Active, $this->testAdmin);

        $updated = $this->service->getTable($tableId);
        $this->assertEquals(TableState::Active, $updated->state());
    }

    public function testIsReadyForJudging(): void
    {
        $tableId = $this->service->createTable('Ready Test', new LocationId(6), 10, $this->testAdmin);

        $this->assertFalse($this->service->isReadyForJudging($tableId));

        $this->service->transitionTableState($tableId, TableState::Active, $this->testAdmin);

        $this->assertTrue($this->service->isReadyForJudging($tableId));
    }

    public function testIsLocked(): void
    {
        $tableId = $this->service->createTable('Lock Test', new LocationId(7), 10, $this->testAdmin);

        $this->assertFalse($this->service->isLocked($tableId));

        $this->service->transitionTableState($tableId, TableState::Active, $this->testAdmin);
        $this->service->transitionTableState($tableId, TableState::Judged, $this->testAdmin);
        $this->service->transitionTableState($tableId, TableState::Locked, $this->testAdmin);

        $this->assertTrue($this->service->isLocked($tableId));
    }

    public function testGetNextFlightNumber(): void
    {
        $tableId = $this->service->createTable('Flight Test', new LocationId(8), 10, $this->testAdmin);

        $nextNumber = $this->service->getNextFlightNumber($tableId, 1);

        $this->assertGreaterThanOrEqual(1, $nextNumber);
    }
}
