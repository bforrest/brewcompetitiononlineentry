<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\AdminPreferences\ValueObject\CompetitionState;
use Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException;

class CompetitionStateTest extends TestCase
{
    public function test_planning_state_exists(): void
    {
        $this->assertSame('planning', CompetitionState::Planning->value);
    }

    public function test_active_state_exists(): void
    {
        $this->assertSame('active', CompetitionState::Active->value);
    }

    public function test_closed_state_exists(): void
    {
        $this->assertSame('closed', CompetitionState::Closed->value);
    }

    public function test_label_returns_human_readable_name(): void
    {
        $this->assertSame('Planning', CompetitionState::Planning->label());
        $this->assertSame('Active', CompetitionState::Active->label());
        $this->assertSame('Closed', CompetitionState::Closed->label());
    }

    public function test_description_returns_non_empty_string(): void
    {
        $this->assertNotEmpty(CompetitionState::Planning->description());
        $this->assertNotEmpty(CompetitionState::Active->description());
        $this->assertNotEmpty(CompetitionState::Closed->description());
    }

    public function test_can_change_preferences_in_planning(): void
    {
        $this->assertTrue(CompetitionState::Planning->canChangePreferences());
    }

    public function test_cannot_change_preferences_in_active(): void
    {
        $this->assertFalse(CompetitionState::Active->canChangePreferences());
    }

    public function test_cannot_change_preferences_in_closed(): void
    {
        $this->assertFalse(CompetitionState::Closed->canChangePreferences());
    }

    public function test_allows_new_entries_in_planning(): void
    {
        $this->assertTrue(CompetitionState::Planning->allowsNewEntries());
    }

    public function test_allows_new_entries_in_active(): void
    {
        $this->assertTrue(CompetitionState::Active->allowsNewEntries());
    }

    public function test_does_not_allow_new_entries_in_closed(): void
    {
        $this->assertFalse(CompetitionState::Closed->allowsNewEntries());
    }

    public function test_is_final_returns_false_for_planning(): void
    {
        $this->assertFalse(CompetitionState::Planning->isFinal());
    }

    public function test_is_final_returns_false_for_active(): void
    {
        $this->assertFalse(CompetitionState::Active->isFinal());
    }

    public function test_is_final_returns_true_for_closed(): void
    {
        $this->assertTrue(CompetitionState::Closed->isFinal());
    }

    public function test_css_class_returns_bootstrap_class(): void
    {
        $this->assertStringContainsString('badge', CompetitionState::Planning->cssClass());
        $this->assertStringContainsString('badge', CompetitionState::Active->cssClass());
        $this->assertStringContainsString('badge', CompetitionState::Closed->cssClass());
    }

    public function test_transition_from_planning_to_active(): void
    {
        $newState = CompetitionState::Planning->transitionTo(CompetitionState::Active);
        $this->assertSame(CompetitionState::Active, $newState);
    }

    public function test_transition_from_planning_to_closed(): void
    {
        $newState = CompetitionState::Planning->transitionTo(CompetitionState::Closed);
        $this->assertSame(CompetitionState::Closed, $newState);
    }

    public function test_transition_from_planning_to_planning(): void
    {
        $newState = CompetitionState::Planning->transitionTo(CompetitionState::Planning);
        $this->assertSame(CompetitionState::Planning, $newState);
    }

    public function test_transition_from_active_to_planning(): void
    {
        $newState = CompetitionState::Active->transitionTo(CompetitionState::Planning);
        $this->assertSame(CompetitionState::Planning, $newState);
    }

    public function test_transition_from_active_to_closed(): void
    {
        $newState = CompetitionState::Active->transitionTo(CompetitionState::Closed);
        $this->assertSame(CompetitionState::Closed, $newState);
    }

    public function test_invalid_transition_from_closed_throws(): void
    {
        $this->expectException(InvalidConstraintException::class);
        CompetitionState::Closed->transitionTo(CompetitionState::Planning);
    }

    public function test_invalid_transition_from_closed_to_active_throws(): void
    {
        $this->expectException(InvalidConstraintException::class);
        CompetitionState::Closed->transitionTo(CompetitionState::Active);
    }

    public function test_can_transition_to_checks_without_throwing(): void
    {
        $this->assertTrue(CompetitionState::Planning->canTransitionTo(CompetitionState::Active));
        $this->assertTrue(CompetitionState::Active->canTransitionTo(CompetitionState::Closed));
        $this->assertFalse(CompetitionState::Closed->canTransitionTo(CompetitionState::Active));
    }

    public function test_get_allowed_transitions_for_planning(): void
    {
        $allowed = CompetitionState::Planning->getAllowedTransitions();
        $this->assertContains(CompetitionState::Active, $allowed);
        $this->assertContains(CompetitionState::Closed, $allowed);
        $this->assertContains(CompetitionState::Planning, $allowed);
    }

    public function test_get_allowed_transitions_for_active(): void
    {
        $allowed = CompetitionState::Active->getAllowedTransitions();
        $this->assertContains(CompetitionState::Planning, $allowed);
        $this->assertContains(CompetitionState::Closed, $allowed);
        $this->assertCount(2, $allowed);
    }

    public function test_get_allowed_transitions_for_closed(): void
    {
        $allowed = CompetitionState::Closed->getAllowedTransitions();
        $this->assertEmpty($allowed);
    }
}
