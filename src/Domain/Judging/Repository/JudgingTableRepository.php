<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\Repository;

use Bcoem\Database\Connection;
use Bcoem\Domain\Judging\Exception\TableNotFoundException;
use Bcoem\Domain\Judging\JudgingTable;
use Bcoem\Domain\Judging\ValueObject\FlightQueue;
use Bcoem\Domain\Judging\ValueObject\LocationId;
use Bcoem\Domain\Judging\ValueObject\TableId;
use Bcoem\Domain\Judging\ValueObject\TableState;
use DateTime;

/**
 * JudgingTableRepository handles all queries for judging tables.
 *
 * All queries use prepared statements via Connection wrapper.
 * No direct mysqli calls allowed.
 */
final class JudgingTableRepository
{
    private readonly string $table;
    private readonly string $flightTable;

    public function __construct(
        private readonly Connection $connection,
        string $tablePrefix = 'baseline_'
    ) {
        $this->table = $tablePrefix . 'judging_tables';
        $this->flightTable = $tablePrefix . 'judging_flights';
    }

    /**
     * Get table by ID.
     *
     * @throws TableNotFoundException
     */
    public function getById(TableId $tableId): JudgingTable
    {
        $sql = sprintf('SELECT * FROM %s WHERE id = ?', $this->table);
        $row = $this->connection->selectOne($sql, [$tableId->value()]);

        if (!$row) {
            throw new TableNotFoundException(sprintf('Judging table %d not found', $tableId->value()));
        }

        return $this->rowToTable($row);
    }

    /**
     * Get all tables at a location by state.
     *
     * @return array<int, JudgingTable>
     */
    public function listByLocationAndState(LocationId $locationId, TableState $state): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE tableLocation = ? AND tableState = ? ORDER BY tableNumber ASC',
            $this->table
        );
        $rows = $this->connection->select($sql, [$locationId->value(), $state->value]);

        return array_map(fn($row) => $this->rowToTable($row), $rows);
    }

    /**
     * Get all tables at a location.
     *
     * @return array<int, JudgingTable>
     */
    public function listByLocation(LocationId $locationId): array
    {
        $sql = sprintf('SELECT * FROM %s WHERE tableLocation = ? ORDER BY tableNumber ASC', $this->table);
        $rows = $this->connection->select($sql, [$locationId->value()]);

        return array_map(fn($row) => $this->rowToTable($row), $rows);
    }

    /**
     * Count tables in a given state.
     */
    public function countByState(TableState $state): int
    {
        $sql = sprintf('SELECT COUNT(*) as count FROM %s WHERE tableState = ?', $this->table);
        $row = $this->connection->selectOne($sql, [$state->value]);
        return (int) $row['count'];
    }

    /**
     * Insert a new judging table.
     *
     * @return TableId
     */
    public function insert(JudgingTable $table): TableId
    {
        $sql = sprintf(
            'INSERT INTO %s (tableName, tableStyles, tableNumber, tableLocation, tableJudges, tableStewards, tableState, tableStateChanged) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            $this->table
        );

        $this->connection->execute($sql, [
            $table->name(),
            null,
            null,
            $table->location()->value(),
            null,
            null,
            $table->state()->value,
            $table->stateChangedAt()->format('Y-m-d H:i:s'),
        ]);

        $lastId = $this->connection->lastInsertId();
        return new TableId($lastId);
    }

    /**
     * Update table state.
     */
    public function updateState(TableId $tableId, TableState $newState, DateTime $changedAt): void
    {
        $sql = sprintf(
            'UPDATE %s SET tableState = ?, tableStateChanged = ? WHERE id = ?',
            $this->table
        );

        $this->connection->execute($sql, [
            $newState->value,
            $changedAt->format('Y-m-d H:i:s'),
            $tableId->value(),
        ]);
    }

    /**
     * Update table properties.
     */
    public function update(JudgingTable $table): void
    {
        $sql = sprintf(
            'UPDATE %s SET tableName = ?, tableStyles = ?, tableNumber = ?, tableLocation = ?, tableState = ?, tableStateChanged = ? WHERE id = ?',
            $this->table
        );

        $this->connection->execute($sql, [
            $table->name(),
            null,
            null,
            $table->location()->value(),
            $table->state()->value,
            $table->stateChangedAt()->format('Y-m-d H:i:s'),
            $table->id()->value(),
        ]);
    }

    /**
     * Convert database row to JudgingTable aggregate.
     *
     * @param array<string, mixed> $row
     */
    private function rowToTable(array $row): JudgingTable
    {
        $tableId = new TableId((int) $row['id']);

        // Load flight queue for this table
        $flights = $this->loadFlightQueue($tableId);

        return new JudgingTable(
            id: $tableId,
            name: (string) $row['tableName'],
            state: TableState::from((string) $row['tableState']),
            flights: $flights,
            location: new LocationId((int) $row['tableLocation']),
            entryLimit: isset($row['tableEntryLimit']) ? (int) $row['tableEntryLimit'] : 0,
            stateChangedAt: new DateTime($row['tableStateChanged'] ?? 'now')
        );
    }

    /**
     * Load flight queue for a table from database.
     *
     * @return FlightQueue
     */
    private function loadFlightQueue(TableId $tableId): FlightQueue
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE flightTable = ? ORDER BY flightRound ASC, flightNumber ASC',
            $this->flightTable
        );
        $rows = $this->connection->select($sql, [$tableId->value()]);

        $flights = [];
        foreach ($rows as $row) {
            $flights[] = new \Bcoem\Domain\Judging\ValueObject\Flight(
                id: new \Bcoem\Domain\Judging\ValueObject\FlightId((int) $row['id']),
                entryId: new \Bcoem\Domain\Entry\ValueObject\EntryId((int) $row['flightEntryID']),
                flightNumber: (int) $row['flightNumber'],
                round: (int) $row['flightRound']
            );
        }

        return new FlightQueue($flights);
    }
}
