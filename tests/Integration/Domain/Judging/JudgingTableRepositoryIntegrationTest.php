<?php
declare(strict_types=1);

namespace Tests\Integration\Domain\Judging;

use Bcoem\Database\Connection;
use Bcoem\Domain\Judging\JudgingTable;
use Bcoem\Domain\Judging\Repository\JudgingTableRepository;
use Bcoem\Domain\Judging\ValueObject\FlightQueue;
use Bcoem\Domain\Judging\ValueObject\LocationId;
use Bcoem\Domain\Judging\ValueObject\TableId;
use Bcoem\Domain\Judging\ValueObject\TableState;
use DateTime;
use PHPUnit\Framework\TestCase;

class JudgingTableRepositoryIntegrationTest extends TestCase
{
    private Connection $connection;
    private JudgingTableRepository $repository;
    private string $tablePrefix = 'test_';

    protected function setUp(): void
    {
        // Get test database connection
        // In a real setup, this would use a test container or fixture
        // For now, we'll skip these tests if no DB is available
        if (!getenv('DB_TEST_HOST')) {
            $this->markTestSkipped('No test database configured');
        }

        // Initialize connection with test prefix
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s',
            getenv('DB_TEST_HOST') ?: 'localhost',
            getenv('DB_TEST_PORT') ?: 3306,
            getenv('DB_TEST_NAME') ?: 'bcoem_test'
        );

        $mysqli = new \mysqli(
            getenv('DB_TEST_HOST') ?: 'localhost',
            getenv('DB_TEST_USER') ?: 'root',
            getenv('DB_TEST_PASSWORD') ?: '',
            getenv('DB_TEST_NAME') ?: 'bcoem_test'
        );

        if ($mysqli->connect_error) {
            $this->markTestSkipped('Could not connect to test database: ' . $mysqli->connect_error);
        }

        $this->connection = new Connection($mysqli);
        $this->repository = new JudgingTableRepository($this->connection, $this->tablePrefix);

        // Clean up test tables
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
            // Table may not exist yet
        }
    }

    public function testInsertAndGetById(): void
    {
        $table = new JudgingTable(
            id: new TableId(0),
            name: 'Test Table A',
            state: TableState::Planning,
            flights: new FlightQueue(),
            location: new LocationId(1),
            entryLimit: 10,
            stateChangedAt: new DateTime()
        );

        $insertedId = $this->repository->insert($table);

        $this->assertGreaterThan(0, $insertedId);

        $retrieved = $this->repository->getById(new TableId($insertedId));

        $this->assertNotNull($retrieved);
        $this->assertEquals('Test Table A', $retrieved->name());
        $this->assertEquals(10, $retrieved->entryLimit());
        $this->assertEquals(TableState::Planning, $retrieved->state());
        $this->assertEquals(1, $retrieved->location()->value());
    }

    public function testListByLocation(): void
    {
        $location = new LocationId(2);

        // Insert multiple tables
        $this->insertTable('Table 1', $location, 10);
        $this->insertTable('Table 2', $location, 15);
        $this->insertTable('Table 3', new LocationId(3), 10);

        $tables = $this->repository->listByLocation($location);

        $this->assertCount(2, $tables);
        $this->assertEquals('Table 1', $tables[0]->name());
        $this->assertEquals('Table 2', $tables[1]->name());
    }

    public function testListByLocationAndState(): void
    {
        $location = new LocationId(4);

        $this->insertTableWithState('Table Active', $location, TableState::Active);
        $this->insertTableWithState('Table Planning', $location, TableState::Planning);
        $this->insertTableWithState('Table Active 2', $location, TableState::Active);

        $activeTables = $this->repository->listByLocationAndState($location, TableState::Active);

        $this->assertCount(2, $activeTables);
        $this->assertEquals('Table Active', $activeTables[0]->name());
        $this->assertEquals('Table Active 2', $activeTables[1]->name());
    }

    public function testUpdateState(): void
    {
        $table = $this->insertTable('State Transition Test', new LocationId(5), 10);
        $tableId = new TableId($table);

        $newState = TableState::Active;
        $now = new DateTime();

        $this->repository->updateState($tableId, $newState, $now);

        $updated = $this->repository->getById($tableId);
        $this->assertEquals(TableState::Active, $updated->state());
    }

    public function testCountByState(): void
    {
        $location = new LocationId(6);

        $this->insertTableWithState('T1', $location, TableState::Planning);
        $this->insertTableWithState('T2', $location, TableState::Planning);
        $this->insertTableWithState('T3', $location, TableState::Active);

        $planningCount = $this->repository->countByState(TableState::Planning);
        $activeCount = $this->repository->countByState(TableState::Active);

        $this->assertGreaterThanOrEqual(2, $planningCount);
        $this->assertGreaterThanOrEqual(1, $activeCount);
    }

    private function insertTable(string $name, LocationId $location, int $entryLimit): int
    {
        $table = new JudgingTable(
            id: new TableId(0),
            name: $name,
            state: TableState::Planning,
            flights: new FlightQueue(),
            location: $location,
            entryLimit: $entryLimit,
            stateChangedAt: new DateTime()
        );

        return $this->repository->insert($table);
    }

    private function insertTableWithState(string $name, LocationId $location, TableState $state): int
    {
        $sql = sprintf(
            'INSERT INTO %s (name, tableState, location, entryLimit, tableStateChanged) VALUES (?, ?, ?, ?, ?)',
            $this->tablePrefix . 'judging_tables'
        );

        $this->connection->execute($sql, [
            $name,
            $state->value,
            $location->value(),
            10,
            (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        return $this->connection->lastInsertId();
    }
}
