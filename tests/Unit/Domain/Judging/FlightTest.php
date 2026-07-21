<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Judging\ValueObject\Flight;
use Bcoem\Domain\Judging\ValueObject\FlightId;
use Bcoem\Domain\Entry\ValueObject\EntryId;

class FlightTest extends TestCase
{
    public function test_create_flight_with_valid_data(): void
    {
        $flight = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $this->assertEquals(1, $flight->id()->value());
        $this->assertEquals(100, $flight->entryId()->value());
        $this->assertEquals(1, $flight->flightNumber());
        $this->assertEquals(1, $flight->round());
    }

    public function test_flight_number_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Flight(new FlightId(1), new EntryId(100), 0, 1);
    }

    public function test_round_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Flight(new FlightId(1), new EntryId(100), 1, 0);
    }

    public function test_equals_same_flight(): void
    {
        $f1 = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $f2 = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $this->assertTrue($f1->equals($f2));
    }

    public function test_equals_different_id(): void
    {
        $f1 = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $f2 = new Flight(new FlightId(2), new EntryId(100), 1, 1);
        $this->assertFalse($f1->equals($f2));
    }

    public function test_equals_different_entry(): void
    {
        $f1 = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $f2 = new Flight(new FlightId(1), new EntryId(101), 1, 1);
        $this->assertFalse($f1->equals($f2));
    }
}
