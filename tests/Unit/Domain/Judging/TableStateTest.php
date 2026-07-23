<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Judging\ValueObject\TableState;
use Bcoem\Domain\Judging\Exception\InvalidStateTransitionException;

class TableStateTest extends TestCase
{
    public function test_planning_can_transition_to_active(): void
    {
        $state = TableState::Planning;
        $this->assertTrue($state->canTransitionTo(TableState::Active));
    }

    public function test_planning_can_transition_to_archived(): void
    {
        $state = TableState::Planning;
        $this->assertTrue($state->canTransitionTo(TableState::Archived));
    }

    public function test_planning_cannot_transition_to_judged(): void
    {
        $state = TableState::Planning;
        $this->assertFalse($state->canTransitionTo(TableState::Judged));
    }

    public function test_planning_cannot_transition_to_locked(): void
    {
        $state = TableState::Planning;
        $this->assertFalse($state->canTransitionTo(TableState::Locked));
    }

    public function test_active_can_transition_to_planning(): void
    {
        $state = TableState::Active;
        $this->assertTrue($state->canTransitionTo(TableState::Planning));
    }

    public function test_active_can_transition_to_judged(): void
    {
        $state = TableState::Active;
        $this->assertTrue($state->canTransitionTo(TableState::Judged));
    }

    public function test_active_can_transition_to_archived(): void
    {
        $state = TableState::Active;
        $this->assertTrue($state->canTransitionTo(TableState::Archived));
    }

    public function test_active_cannot_transition_to_locked(): void
    {
        $state = TableState::Active;
        $this->assertFalse($state->canTransitionTo(TableState::Locked));
    }

    public function test_judged_can_transition_to_locked(): void
    {
        $state = TableState::Judged;
        $this->assertTrue($state->canTransitionTo(TableState::Locked));
    }

    public function test_judged_can_transition_to_archived(): void
    {
        $state = TableState::Judged;
        $this->assertTrue($state->canTransitionTo(TableState::Archived));
    }

    public function test_judged_cannot_transition_to_planning(): void
    {
        $state = TableState::Judged;
        $this->assertFalse($state->canTransitionTo(TableState::Planning));
    }

    public function test_judged_cannot_transition_to_active(): void
    {
        $state = TableState::Judged;
        $this->assertFalse($state->canTransitionTo(TableState::Active));
    }

    public function test_locked_can_transition_to_archived(): void
    {
        $state = TableState::Locked;
        $this->assertTrue($state->canTransitionTo(TableState::Archived));
    }

    public function test_locked_cannot_transition_to_judged(): void
    {
        $state = TableState::Locked;
        $this->assertFalse($state->canTransitionTo(TableState::Judged));
    }

    public function test_archived_cannot_transition_anywhere(): void
    {
        $state = TableState::Archived;
        $this->assertFalse($state->canTransitionTo(TableState::Planning));
        $this->assertFalse($state->canTransitionTo(TableState::Active));
        $this->assertFalse($state->canTransitionTo(TableState::Judged));
        $this->assertFalse($state->canTransitionTo(TableState::Locked));
    }

    public function test_transition_to_throws_on_invalid(): void
    {
        $this->expectException(InvalidStateTransitionException::class);
        TableState::Locked->transitionTo(TableState::Active);
    }

    public function test_transition_to_returns_target_state_on_valid(): void
    {
        $result = TableState::Active->transitionTo(TableState::Judged);
        $this->assertSame(TableState::Judged, $result);
    }

    public function test_same_state_transition_is_allowed(): void
    {
        $result = TableState::Active->transitionTo(TableState::Active);
        $this->assertSame(TableState::Active, $result);
    }

    public function test_allows_scoring_for_planning_and_active(): void
    {
        $this->assertTrue(TableState::Planning->allowsScoring());
        $this->assertTrue(TableState::Active->allowsScoring());
    }

    public function test_allows_scoring_false_for_judged_locked_archived(): void
    {
        $this->assertFalse(TableState::Judged->allowsScoring());
        $this->assertFalse(TableState::Locked->allowsScoring());
        $this->assertFalse(TableState::Archived->allowsScoring());
    }

    public function test_is_editable_for_planning_active_judged(): void
    {
        $this->assertTrue(TableState::Planning->isEditable());
        $this->assertTrue(TableState::Active->isEditable());
        $this->assertTrue(TableState::Judged->isEditable());
    }

    public function test_is_editable_false_for_locked_archived(): void
    {
        $this->assertFalse(TableState::Locked->isEditable());
        $this->assertFalse(TableState::Archived->isEditable());
    }

    public function test_label_returns_human_readable_string(): void
    {
        $this->assertSame('Planning', TableState::Planning->label());
        $this->assertSame('Active', TableState::Active->label());
        $this->assertSame('Judged', TableState::Judged->label());
        $this->assertSame('Locked', TableState::Locked->label());
        $this->assertSame('Archived', TableState::Archived->label());
    }

    public function test_label_class_returns_bootstrap3_label_suffix(): void
    {
        $this->assertSame('default', TableState::Planning->labelClass());
        $this->assertSame('primary', TableState::Active->labelClass());
        $this->assertSame('success', TableState::Judged->labelClass());
        $this->assertSame('danger', TableState::Locked->labelClass());
        $this->assertSame('default', TableState::Archived->labelClass());
    }
}
