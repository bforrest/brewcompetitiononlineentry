<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\ValueObject;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * FlightQueue is an immutable, ordered collection of Flight objects.
 *
 * Flights are maintained in order by (round, flightNumber).
 * Operations like add() and remove() return new instances (copy-on-write).
 * This prevents accidental mutation and makes state transitions explicit.
 *
 * @implements IteratorAggregate<int, Flight>
 */
final class FlightQueue implements IteratorAggregate, Countable
{
    /**
     * Flights stored in order: [Flight, Flight, ...]
     * Invariant: flights always sorted by (round, flightNumber)
     *
     * @var array<int, Flight>
     */
    private readonly array $flights;

    /**
     * @param array<int, Flight> $flights
     */
    public function __construct(array $flights = [])
    {
        $this->validateAndSort($flights);
        $this->flights = $flights;
    }

    /**
     * Validate flights and ensure they're sorted by (round, flightNumber).
     *
     * @param array<int, Flight> $flights
     */
    private function validateAndSort(array &$flights): void
    {
        $seen = [];
        foreach ($flights as $flight) {
            $key = $flight->round() . ':' . $flight->flightNumber();
            if (isset($seen[$key])) {
                throw new InvalidArgumentException(
                    sprintf('Duplicate flight number %d in round %d', $flight->flightNumber(), $flight->round())
                );
            }
            $seen[$key] = true;
        }

        usort($flights, static function (Flight $a, Flight $b): int {
            if ($a->round() !== $b->round()) {
                return $a->round() <=> $b->round();
            }
            return $a->flightNumber() <=> $b->flightNumber();
        });
    }

    /**
     * Add a flight to the queue. Returns new instance (copy-on-write).
     *
     * @throws InvalidArgumentException if flight number already exists in that round
     */
    public function add(Flight $flight): self
    {
        $flights = $this->flights;
        $flights[] = $flight;
        return new self($flights);
    }

    /**
     * Remove a flight by ID. Returns new instance.
     *
     * @throws InvalidArgumentException if flight not found
     */
    public function remove(FlightId $flightId): self
    {
        $flights = [];
        $found = false;
        foreach ($this->flights as $flight) {
            if ($flight->id()->equals($flightId)) {
                $found = true;
                continue;
            }
            $flights[] = $flight;
        }

        if (!$found) {
            throw new InvalidArgumentException(sprintf('Flight %s not found', $flightId));
        }

        return new self($flights);
    }

    /**
     * Get flight by ID.
     *
     * @return Flight|null
     */
    public function getById(FlightId $flightId): ?Flight
    {
        foreach ($this->flights as $flight) {
            if ($flight->id()->equals($flightId)) {
                return $flight;
            }
        }
        return null;
    }

    /**
     * Get flights for a specific round.
     *
     * @return array<int, Flight>
     */
    public function getByRound(int $round): array
    {
        return array_filter($this->flights, static fn(Flight $f) => $f->round() === $round);
    }

    /**
     * Get all flights.
     *
     * @return array<int, Flight>
     */
    public function all(): array
    {
        return $this->flights;
    }

    /**
     * Check if queue is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->flights) === 0;
    }

    /**
     * Count flights in queue.
     */
    public function count(): int
    {
        return count($this->flights);
    }

    /**
     * Get highest flight number in a round (for auto-incrementing next flight).
     */
    public function maxFlightNumberInRound(int $round): int
    {
        $max = 0;
        foreach ($this->flights as $flight) {
            if ($flight->round() === $round && $flight->flightNumber() > $max) {
                $max = $flight->flightNumber();
            }
        }
        return $max;
    }

    /**
     * Iterate flights in order.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->flights);
    }
}
