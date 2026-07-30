<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration;

use Bcoem\Database\Connection;
use Bcoem\Domain\Judging\JudgingTable;
use Bcoem\Domain\Judging\Repository\JudgingTableRepository;
use Bcoem\Domain\Judging\ValueObject\FlightQueue;
use Bcoem\Domain\Judging\ValueObject\LocationId;
use Bcoem\Domain\Judging\ValueObject\TableId;
use Bcoem\Domain\Judging\ValueObject\TableState;
use DateTime;

class JudgingTableRepositoryIntegrationTest extends IntegrationTestCase
{
    private JudgingTableRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = new Connection(self::$conn);
        $this->repository = new JudgingTableRepository($connection, self::$pfx);
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

        $this->assertGreaterThan(0, $insertedId->value());

        $retrieved = $this->repository->getById($insertedId);

        $this->assertEquals('Test Table A', $retrieved->name());
        $this->assertEquals(10, $retrieved->entryLimit());
        $this->assertEquals(TableState::Planning, $retrieved->state());
        $this->assertEquals(1, $retrieved->location()->value());
    }

    public function testListByLocation(): void
    {
        $location = new LocationId(2);

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
        $tableId = $this->insertTable('State Transition Test', new LocationId(5), 10);

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

    private function insertTable(string $name, LocationId $location, int $entryLimit): TableId
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

    private function insertTableWithState(string $name, LocationId $location, TableState $state): TableId
    {
        $id = $this->insert('judging_tables', [
            'tableName' => $name,
            'tableState' => $state->value,
            'tableLocation' => $location->value(),
            'tableEntryLimit' => 10,
            'tableStateChanged' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        return new TableId($id);
    }
}
