<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Judging\ValueObject\Flight;
use Bcoem\Domain\Judging\ValueObject\FlightId;
use Bcoem\Domain\Judging\ValueObject\FlightQueue;
use Bcoem\Domain\Entry\ValueObject\EntryId;

class FlightQueueTest extends TestCase
{
    public function test_create_empty_flight_queue(): void
    {
        $queue = new FlightQueue();
        $this->assertTrue($queue->isEmpty());
        $this->assertEquals(0, $queue->count());
    }

    public function test_create_queue_with_flights(): void
    {
        $f1 = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $f2 = new Flight(new FlightId(2), new EntryId(101), 2, 1);
        $queue = new FlightQueue([$f1, $f2]);
        $this->assertEquals(2, $queue->count());
        $this->assertFalse($queue->isEmpty());
    }

    public function test_duplicate_flight_number_in_round_throws(): void
    {
        $f1 = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $f2 = new Flight(new FlightId(2), new EntryId(101), 1, 1);
        $this->expectException(InvalidArgumentException::class);
        new FlightQueue([$f1, $f2]);
    }

    public function test_add_flight_returns_new_instance(): void
    {
        $queue1 = new FlightQueue();
        $f = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $queue2 = $queue1->add($f);

        $this->assertNotSame($queue1, $queue2);
        $this->assertEquals(0, $queue1->count());
        $this->assertEquals(1, $queue2->count());
    }

    public function test_flights_sorted_by_round_then_number(): void
    {
        $f1 = new Flight(new FlightId(1), new EntryId(100), 2, 1);
        $f2 = new Flight(new FlightId(2), new EntryId(101), 1, 1);
        $f3 = new Flight(new FlightId(3), new EntryId(102), 1, 2);
        $queue = new FlightQueue([$f1, $f2, $f3]);

        $all = $queue->all();
        $this->assertEquals(1, $all[0]->flightNumber());
        $this->assertEquals(1, $all[0]->round());
        $this->assertEquals(2, $all[1]->flightNumber());
        $this->assertEquals(1, $all[1]->round());
        $this->assertEquals(1, $all[2]->flightNumber());
        $this->assertEquals(2, $all[2]->round());
    }

    public function test_remove_flight_by_id(): void
    {
        $f1 = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $f2 = new Flight(new FlightId(2), new EntryId(101), 2, 1);
        $queue = new FlightQueue([$f1, $f2]);

        $queue2 = $queue->remove(new FlightId(1));
        $this->assertEquals(2, $queue->count());
        $this->assertEquals(1, $queue2->count());
        $this->assertEquals(101, $queue2->all()[0]->entryId()->value());
    }

    public function test_remove_nonexistent_flight_throws(): void
    {
        $f = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $queue = new FlightQueue([$f]);
        $this->expectException(InvalidArgumentException::class);
        $queue->remove(new FlightId(999));
    }

    public function test_get_flight_by_id(): void
    {
        $f1 = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $f2 = new Flight(new FlightId(2), new EntryId(101), 2, 1);
        $queue = new FlightQueue([$f1, $f2]);

        $found = $queue->getById(new FlightId(2));
        $this->assertNotNull($found);
        $this->assertEquals(101, $found->entryId()->value());
    }

    public function test_get_nonexistent_flight_returns_null(): void
    {
        $f = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $queue = new FlightQueue([$f]);
        $found = $queue->getById(new FlightId(999));
        $this->assertNull($found);
    }

    public function test_get_flights_by_round(): void
    {
        $f1 = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $f2 = new Flight(new FlightId(2), new EntryId(101), 2, 1);
        $f3 = new Flight(new FlightId(3), new EntryId(102), 1, 2);
        $queue = new FlightQueue([$f1, $f2, $f3]);

        $round1 = $queue->getByRound(1);
        $this->assertEquals(2, count($round1));

        $round2 = $queue->getByRound(2);
        $this->assertEquals(1, count($round2));

        $round3 = $queue->getByRound(3);
        $this->assertEquals(0, count($round3));
    }

    public function test_max_flight_number_in_round(): void
    {
        $f1 = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $f2 = new Flight(new FlightId(2), new EntryId(101), 5, 1);
        $f3 = new Flight(new FlightId(3), new EntryId(102), 2, 2);
        $queue = new FlightQueue([$f1, $f2, $f3]);

        $this->assertEquals(5, $queue->maxFlightNumberInRound(1));
        $this->assertEquals(2, $queue->maxFlightNumberInRound(2));
        $this->assertEquals(0, $queue->maxFlightNumberInRound(3));
    }

    public function test_iterate_flights(): void
    {
        $f1 = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $f2 = new Flight(new FlightId(2), new EntryId(101), 2, 1);
        $queue = new FlightQueue([$f1, $f2]);

        $count = 0;
        foreach ($queue as $flight) {
            $count++;
            $this->assertInstanceOf(Flight::class, $flight);
        }
        $this->assertEquals(2, $count);
    }

    public function test_countable_interface(): void
    {
        $queue = new FlightQueue();
        $this->assertCount(0, $queue);

        $f = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $queue2 = $queue->add($f);
        $this->assertCount(1, $queue2);
    }
}
