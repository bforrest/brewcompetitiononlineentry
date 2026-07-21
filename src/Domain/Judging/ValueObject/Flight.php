<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\ValueObject;

use Bcoem\Domain\Entry\ValueObject\EntryId;

/**
 * Flight represents a single entry's place in the judging queue at a table.
 *
 * Flights are ordered by flightNumber within a round. Judges score entries in flight order.
 * Immutable: once created, cannot be changed (create new instance to modify).
 */
final class Flight
{
    public function __construct(
        private readonly FlightId $id,
        private readonly EntryId $entryId,
        private readonly int $flightNumber,
        private readonly int $round
    ) {
        if ($flightNumber < 1) {
            throw new \InvalidArgumentException('Flight number must be positive');
        }
        if ($round < 1) {
            throw new \InvalidArgumentException('Round must be positive');
        }
    }

    public function id(): FlightId
    {
        return $this->id;
    }

    public function entryId(): EntryId
    {
        return $this->entryId;
    }

    public function flightNumber(): int
    {
        return $this->flightNumber;
    }

    public function round(): int
    {
        return $this->round;
    }

    public function equals(Flight $other): bool
    {
        return $this->id->equals($other->id)
            && $this->entryId->equals($other->entryId)
            && $this->flightNumber === $other->flightNumber
            && $this->round === $other->round;
    }
}
