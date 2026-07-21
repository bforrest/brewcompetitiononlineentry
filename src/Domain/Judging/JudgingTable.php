<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging;

use Bcoem\Domain\Judging\ValueObject\Flight;
use Bcoem\Domain\Judging\ValueObject\FlightId;
use Bcoem\Domain\Judging\ValueObject\FlightQueue;
use Bcoem\Domain\Judging\ValueObject\LocationId;
use Bcoem\Domain\Judging\ValueObject\TableId;
use Bcoem\Domain\Judging\ValueObject\TableState;
use DateTime;

/**
 * JudgingTable is the aggregate root for judging workflow.
 *
 * Responsibilities:
 * - Manage table state lifecycle (planning → active → judged → locked → archived)
 * - Manage the flight queue (ordered list of entries to judge)
 * - Record state transitions for audit trail
 * - Enforce state machine invariants
 *
 * The JudgingTable does NOT directly record scores; that's the responsibility of
 * JudgingScoreRepository. The JudgingTable only tracks which entries are in the queue
 * and what state the table is in.
 *
 * Immutable except for controlled mutations via methods that return new state.
 */
final class JudgingTable
{
    /**
     * Domain events recorded during this table's lifecycle.
     * Used for audit trail and event sourcing.
     *
     * @var array<int, array{action: string, entity: string, entity_id: int|string, before: array, after: array, timestamp: DateTime}>
     */
    private array $events = [];

    public function __construct(
        private readonly TableId $id,
        private readonly string $name,
        private TableState $state,
        private FlightQueue $flights,
        private readonly LocationId $location,
        private readonly int $entryLimit,
        private DateTime $stateChangedAt
    ) {
    }

    public function id(): TableId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function state(): TableState
    {
        return $this->state;
    }

    public function flights(): FlightQueue
    {
        return $this->flights;
    }

    public function location(): LocationId
    {
        return $this->location;
    }

    public function entryLimit(): int
    {
        return $this->entryLimit;
    }

    public function stateChangedAt(): DateTime
    {
        return $this->stateChangedAt;
    }

    /**
     * Transition table to a new state.
     *
     * Records the transition in the event log for audit trail.
     *
     * @throws \Bcoem\Domain\Judging\Exception\InvalidStateTransitionException
     */
    public function transitionToState(TableState $newState, DateTime $at): void
    {
        if ($newState === $this->state) {
            return;
        }

        $oldState = $this->state;
        $this->state = $oldState->transitionTo($newState);
        $this->stateChangedAt = $at;

        $this->recordEvent('state_changed', 'judging_table', $this->id->value(), [
            'state' => $oldState->value,
        ], [
            'state' => $newState->value,
        ], $at);
    }

    /**
     * Add a flight to the queue.
     *
     * Transitions table to Active if not already active/judged/locked.
     */
    public function addFlight(Flight $flight, DateTime $at): void
    {
        $this->flights = $this->flights->add($flight);

        if ($this->state === TableState::Planning) {
            $this->transitionToState(TableState::Active, $at);
        }

        $this->recordEvent('flight_added', 'judging_flight', $flight->id()->value(), [], [
            'flight_number' => $flight->flightNumber(),
            'entry_id' => $flight->entryId()->value(),
            'round' => $flight->round(),
        ], $at);
    }

    /**
     * Remove a flight from the queue.
     *
     * @throws \InvalidArgumentException if flight not found
     */
    public function removeFlight(FlightId $flightId, DateTime $at): void
    {
        $flight = $this->flights->getById($flightId);
        if ($flight === null) {
            throw new \InvalidArgumentException(sprintf('Flight %s not found', $flightId));
        }

        $this->flights = $this->flights->remove($flightId);

        $this->recordEvent('flight_removed', 'judging_flight', $flightId->value(), [
            'flight_number' => $flight->flightNumber(),
            'entry_id' => $flight->entryId()->value(),
            'round' => $flight->round(),
        ], [], $at);
    }

    /**
     * Get next highest flight number for a round (for auto-incrementing).
     */
    public function nextFlightNumberInRound(int $round): int
    {
        return $this->flights->maxFlightNumberInRound($round) + 1;
    }

    /**
     * Check if table is actively judging (only in Active state).
     */
    public function isReadyForJudging(): bool
    {
        return $this->state === TableState::Active;
    }

    /**
     * Check if table state is locked (scores immutable).
     */
    public function isLocked(): bool
    {
        return $this->state === TableState::Locked;
    }

    /**
     * Check if table can be edited by admin.
     */
    public function isEditable(): bool
    {
        return $this->state->isEditable();
    }

    /**
     * Get all recorded events for audit trail.
     *
     * @return array<int, array{action: string, entity: string, entity_id: int|string, before: array, after: array, timestamp: DateTime}>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Record an event in the audit trail.
     *
     * @param array $before Previous state
     * @param array $after New state
     */
    private function recordEvent(
        string $action,
        string $entity,
        int|string $entityId,
        array $before,
        array $after,
        DateTime $at
    ): void {
        $this->events[] = [
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'before' => $before,
            'after' => $after,
            'timestamp' => $at,
        ];
    }
}
