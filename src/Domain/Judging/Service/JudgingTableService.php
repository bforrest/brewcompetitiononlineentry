<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\Service;

use Bcoem\Domain\Judging\JudgingTable;
use Bcoem\Domain\Judging\Repository\JudgingTableRepository;
use Bcoem\Domain\Judging\ValueObject\Flight;
use Bcoem\Domain\Judging\ValueObject\FlightId;
use Bcoem\Domain\Judging\ValueObject\FlightQueue;
use Bcoem\Domain\Judging\ValueObject\LocationId;
use Bcoem\Domain\Judging\ValueObject\TableId;
use Bcoem\Domain\Judging\ValueObject\TableState;
use Bcoem\Security\Identity;
use DateTime;

/**
 * JudgingTableService orchestrates judging table operations.
 *
 * Responsibilities:
 * - Create tables
 * - Manage table state (transitions)
 * - Add/remove flights from queue
 * - Audit logging
 */
final class JudgingTableService
{
    public function __construct(
        private readonly JudgingTableRepository $repository,
        private readonly JudgingValidationService $validation
    ) {
    }

    /**
     * Create a new judging table.
     *
     * @return TableId
     */
    public function createTable(
        string $name,
        LocationId $location,
        int $entryLimit,
        Identity $admin
    ): TableId {
        $now = new DateTime();
        $table = new JudgingTable(
            id: new TableId(0),
            name: $name,
            state: TableState::Planning,
            flights: new FlightQueue(),
            location: $location,
            entryLimit: $entryLimit,
            stateChangedAt: $now
        );

        return $this->repository->insert($table);
    }

    /**
     * Get table by ID.
     */
    public function getTable(TableId $tableId): JudgingTable
    {
        return $this->repository->getById($tableId);
    }

    /**
     * List tables at a location.
     *
     * @return array<int, JudgingTable>
     */
    public function listTablesByLocation(LocationId $location): array
    {
        return $this->repository->listByLocation($location);
    }

    /**
     * List tables at a location by state.
     *
     * @return array<int, JudgingTable>
     */
    public function listTablesByLocationAndState(LocationId $location, TableState $state): array
    {
        return $this->repository->listByLocationAndState($location, $state);
    }

    /**
     * Transition table to a new state.
     */
    public function transitionTableState(
        TableId $tableId,
        TableState $newState,
        Identity $admin
    ): void {
        $table = $this->getTable($tableId);
        $this->validation->validateTableIsEditable($table);

        $now = new DateTime();
        $table->transitionToState($newState, $now);

        $this->repository->updateState($tableId, $newState, $now);
    }

    /**
     * Add flight to table queue.
     */
    public function addFlight(
        TableId $tableId,
        Flight $flight,
        Identity $admin
    ): void {
        $table = $this->getTable($tableId);
        $now = new DateTime();

        $table->addFlight($flight, $now);

        $this->repository->update($table);
    }

    /**
     * Remove flight from table queue.
     */
    public function removeFlight(
        TableId $tableId,
        FlightId $flightId,
        Identity $admin
    ): void {
        $table = $this->getTable($tableId);
        $this->validation->validateTableIsEditable($table);

        $now = new DateTime();
        $table->removeFlight($flightId, $now);

        $this->repository->update($table);
    }

    /**
     * Get next flight number for a round at a table.
     */
    public function getNextFlightNumber(TableId $tableId, int $round): int
    {
        $table = $this->getTable($tableId);
        return $table->nextFlightNumberInRound($round);
    }

    /**
     * Check if table is ready for judging (active state).
     */
    public function isReadyForJudging(TableId $tableId): bool
    {
        $table = $this->getTable($tableId);
        return $table->isReadyForJudging();
    }

    /**
     * Check if table is locked.
     */
    public function isLocked(TableId $tableId): bool
    {
        $table = $this->getTable($tableId);
        return $table->isLocked();
    }
}
