<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Judging\JudgingTable;
use Bcoem\Domain\Judging\ValueObject\Flight;
use Bcoem\Domain\Judging\ValueObject\FlightId;
use Bcoem\Domain\Judging\ValueObject\FlightQueue;
use Bcoem\Domain\Judging\ValueObject\LocationId;
use Bcoem\Domain\Judging\ValueObject\TableId;
use Bcoem\Domain\Judging\ValueObject\TableState;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use DateTime;

class JudgingTableTest extends TestCase
{
    private JudgingTable $table;
    private DateTime $now;

    protected function setUp(): void
    {
        $this->now = new DateTime('2026-07-21 12:00:00');
        $this->table = new JudgingTable(
            new TableId(1),
            'Main Judging Table',
            TableState::Planning,
            new FlightQueue(),
            new LocationId(1),
            50,
            $this->now
        );
    }

    public function test_create_judging_table(): void
    {
        $this->assertEquals(1, $this->table->id()->value());
        $this->assertSame('Main Judging Table', $this->table->name());
        $this->assertSame(TableState::Planning, $this->table->state());
        $this->assertEquals(1, $this->table->location()->value());
        $this->assertEquals(50, $this->table->entryLimit());
    }

    public function test_table_starts_in_planning_state(): void
    {
        $this->assertSame(TableState::Planning, $this->table->state());
    }

    public function test_transition_to_state(): void
    {
        $later = new DateTime('2026-07-21 12:15:00');
        $this->table->transitionToState(TableState::Active, $later);
        $this->assertSame(TableState::Active, $this->table->state());
        $this->assertEquals($later, $this->table->stateChangedAt());
    }

    public function test_transition_to_same_state_is_noop(): void
    {
        $this->table->transitionToState(TableState::Planning, $this->now);
        $this->assertSame(TableState::Planning, $this->table->state());
        $this->assertEquals(0, count($this->table->events()));
    }

    public function test_invalid_state_transition_throws(): void
    {
        $this->table->transitionToState(TableState::Active, $this->now);
        $this->table->transitionToState(TableState::Judged, $this->now);
        $this->table->transitionToState(TableState::Locked, $this->now);

        $this->expectException(\Bcoem\Domain\Judging\Exception\InvalidStateTransitionException::class);
        $this->table->transitionToState(TableState::Active, $this->now);
    }

    public function test_add_flight_to_planning_table_transitions_to_active(): void
    {
        $flight = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $this->table->addFlight($flight, $this->now);

        $this->assertSame(TableState::Active, $this->table->state());
        $this->assertEquals(1, $this->table->flights()->count());
    }

    public function test_add_flight_to_active_table_stays_active(): void
    {
        $this->table->transitionToState(TableState::Active, $this->now);
        $flight = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $this->table->addFlight($flight, $this->now);

        $this->assertSame(TableState::Active, $this->table->state());
        $this->assertEquals(1, $this->table->flights()->count());
    }

    public function test_cannot_add_flight_to_locked_table(): void
    {
        $this->table->transitionToState(TableState::Active, $this->now);
        $this->table->transitionToState(TableState::Judged, $this->now);
        $this->table->transitionToState(TableState::Locked, $this->now);

        $flight = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $this->table->addFlight($flight, $this->now);

        $this->assertEquals(1, $this->table->flights()->count());
    }

    public function test_remove_flight(): void
    {
        $f1 = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $f2 = new Flight(new FlightId(2), new EntryId(101), 2, 1);

        $this->table->addFlight($f1, $this->now);
        $this->table->addFlight($f2, $this->now);
        $this->assertEquals(2, $this->table->flights()->count());

        $this->table->removeFlight(new FlightId(1), $this->now);
        $this->assertEquals(1, $this->table->flights()->count());
    }

    public function test_remove_nonexistent_flight_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->table->removeFlight(new FlightId(999), $this->now);
    }

    public function test_next_flight_number_in_round(): void
    {
        $f1 = new Flight(new FlightId(1), new EntryId(100), 2, 1);
        $f2 = new Flight(new FlightId(2), new EntryId(101), 3, 1);

        $this->table->addFlight($f1, $this->now);
        $this->table->addFlight($f2, $this->now);

        $this->assertEquals(4, $this->table->nextFlightNumberInRound(1));
        $this->assertEquals(1, $this->table->nextFlightNumberInRound(2));
    }

    public function test_is_ready_for_judging_only_in_active_state(): void
    {
        $this->assertFalse($this->table->isReadyForJudging());

        $this->table->transitionToState(TableState::Active, $this->now);
        $this->assertTrue($this->table->isReadyForJudging());

        $this->table->transitionToState(TableState::Judged, $this->now);
        $this->assertFalse($this->table->isReadyForJudging());

        $this->table->transitionToState(TableState::Locked, $this->now);
        $this->assertFalse($this->table->isReadyForJudging());
    }

    public function test_is_locked(): void
    {
        $this->assertFalse($this->table->isLocked());

        $this->table->transitionToState(TableState::Active, $this->now);
        $this->assertFalse($this->table->isLocked());

        $this->table->transitionToState(TableState::Judged, $this->now);
        $this->assertFalse($this->table->isLocked());

        $this->table->transitionToState(TableState::Locked, $this->now);
        $this->assertTrue($this->table->isLocked());
    }

    public function test_is_editable(): void
    {
        $this->assertTrue($this->table->isEditable());

        $this->table->transitionToState(TableState::Active, $this->now);
        $this->assertTrue($this->table->isEditable());

        $this->table->transitionToState(TableState::Judged, $this->now);
        $this->assertTrue($this->table->isEditable());

        $this->table->transitionToState(TableState::Locked, $this->now);
        $this->assertFalse($this->table->isEditable());
    }

    public function test_events_recorded_for_state_transitions(): void
    {
        $later = new DateTime('2026-07-21 12:15:00');
        $this->table->transitionToState(TableState::Active, $later);

        $events = $this->table->events();
        $this->assertEquals(1, count($events));
        $this->assertSame('state_changed', $events[0]['action']);
        $this->assertSame('judging_table', $events[0]['entity']);
        $this->assertSame('planning', $events[0]['before']['state']);
        $this->assertSame('active', $events[0]['after']['state']);
    }

    public function test_events_recorded_for_flight_operations(): void
    {
        $flight = new Flight(new FlightId(1), new EntryId(100), 1, 1);
        $this->table->addFlight($flight, $this->now);

        $events = $this->table->events();
        $this->assertGreaterThanOrEqual(2, count($events));

        $lastEvent = $events[count($events) - 1];
        $this->assertSame('flight_added', $lastEvent['action']);
        $this->assertSame('judging_flight', $lastEvent['entity']);
        $this->assertEquals(100, $lastEvent['after']['entry_id']);
    }
}
