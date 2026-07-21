<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\AdminPreferences\AdminPreferences;
use Bcoem\Domain\AdminPreferences\ValueObject\PreferencesId;
use Bcoem\Domain\AdminPreferences\ValueObject\StyleSet;
use Bcoem\Domain\AdminPreferences\ValueObject\StyleSetConfiguration;
use Bcoem\Domain\AdminPreferences\ValueObject\EntryConstraints;
use Bcoem\Domain\AdminPreferences\ValueObject\JudgingConfiguration;
use Bcoem\Domain\AdminPreferences\ValueObject\CompetitionState;
use Bcoem\Domain\AdminPreferences\Exception\PreferencesLockedForCompetitionException;
use DateTime;

class AdminPreferencesTest extends TestCase
{
    private AdminPreferences $prefs;
    private DateTime $now;
    private PreferencesId $id;
    private StyleSetConfiguration $styleConfig;
    private EntryConstraints $constraints;
    private JudgingConfiguration $judgingConfig;

    protected function setUp(): void
    {
        $this->now = new DateTime('2026-07-21 12:00:00');
        $this->id = new PreferencesId(1);
        $this->styleConfig = new StyleSetConfiguration(StyleSet::BJCP2025);
        $this->constraints = new EntryConstraints(globalEntryLimit: 5);
        $this->judgingConfig = new JudgingConfiguration(isQueued: true);

        $this->prefs = new AdminPreferences(
            $this->id,
            $this->styleConfig,
            $this->constraints,
            $this->judgingConfig,
            CompetitionState::Planning,
            $this->now
        );
    }

    public function test_create_admin_preferences(): void
    {
        $this->assertEquals($this->id, $this->prefs->id());
        $this->assertSame($this->styleConfig, $this->prefs->styleSetConfig());
        $this->assertSame($this->constraints, $this->prefs->entryConstraints());
        $this->assertSame($this->judgingConfig, $this->prefs->judgingConfig());
        $this->assertSame(CompetitionState::Planning, $this->prefs->competitionState());
    }

    public function test_starts_in_planning_state(): void
    {
        $this->assertSame(CompetitionState::Planning, $this->prefs->competitionState());
    }

    public function test_can_change_preferences_in_planning(): void
    {
        $this->assertTrue($this->prefs->canChangePreferences());
    }

    public function test_cannot_change_preferences_when_active(): void
    {
        $later = new DateTime('2026-07-21 13:00:00');
        $this->prefs->transitionToState(CompetitionState::Active, $later);
        $this->assertFalse($this->prefs->canChangePreferences());
    }

    public function test_transition_to_active(): void
    {
        $later = new DateTime('2026-07-21 13:00:00');
        $this->prefs->transitionToState(CompetitionState::Active, $later);
        $this->assertSame(CompetitionState::Active, $this->prefs->competitionState());
        $this->assertEquals($later, $this->prefs->stateChangedAt());
    }

    public function test_transition_to_same_state_records_no_event(): void
    {
        $this->prefs->transitionToState(CompetitionState::Planning, $this->now);
        $this->assertSame(0, count($this->prefs->events()));
    }

    public function test_transition_records_event(): void
    {
        $later = new DateTime('2026-07-21 13:00:00');
        $this->prefs->transitionToState(CompetitionState::Active, $later);

        $events = $this->prefs->events();
        $this->assertCount(1, $events);
        $this->assertSame('state_changed', $events[0]['action']);
        $this->assertSame('admin_preferences', $events[0]['entity']);
        $this->assertSame('planning', $events[0]['before']['state']);
        $this->assertSame('active', $events[0]['after']['state']);
    }

    public function test_invalid_state_transition_throws(): void
    {
        $later = new DateTime('2026-07-21 13:00:00');
        $this->prefs->transitionToState(CompetitionState::Closed, $later);

        $this->expectException(\Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException::class);
        $this->prefs->transitionToState(CompetitionState::Active, $later);
    }

    public function test_update_entry_constraints_in_planning(): void
    {
        $newConstraints = $this->constraints->withGlobalLimit(10);
        $this->prefs->updateEntryConstraints($newConstraints, $this->now);

        $this->assertSame(10, $this->prefs->entryConstraints()->globalEntryLimit());
    }

    public function test_update_entry_constraints_records_event(): void
    {
        $newConstraints = $this->constraints->withGlobalLimit(10);
        $this->prefs->updateEntryConstraints($newConstraints, $this->now);

        $events = $this->prefs->events();
        $this->assertCount(1, $events);
        $this->assertSame('entry_constraints_updated', $events[0]['action']);
        $this->assertSame(5, $events[0]['before']['global_limit']);
        $this->assertSame(10, $events[0]['after']['global_limit']);
    }

    public function test_cannot_update_entry_constraints_when_active(): void
    {
        $later = new DateTime('2026-07-21 13:00:00');
        $this->prefs->transitionToState(CompetitionState::Active, $later);

        $newConstraints = $this->constraints->withGlobalLimit(10);

        $this->expectException(PreferencesLockedForCompetitionException::class);
        $this->prefs->updateEntryConstraints($newConstraints, $later);
    }

    public function test_cannot_update_entry_constraints_when_closed(): void
    {
        $later = new DateTime('2026-07-21 13:00:00');
        $this->prefs->transitionToState(CompetitionState::Active, $later);
        $this->prefs->transitionToState(CompetitionState::Closed, $later);

        $newConstraints = $this->constraints->withGlobalLimit(10);

        $this->expectException(PreferencesLockedForCompetitionException::class);
        $this->prefs->updateEntryConstraints($newConstraints, $later);
    }

    public function test_update_style_set_in_planning(): void
    {
        $newConfig = $this->styleConfig->withStyleSet(StyleSet::AABC2025);
        $this->prefs->updateStyleSet($newConfig, $this->now);

        $this->assertSame(StyleSet::AABC2025, $this->prefs->styleSetConfig()->styleSet());
    }

    public function test_update_style_set_records_event(): void
    {
        $newConfig = $this->styleConfig->withStyleSet(StyleSet::AABC2025);
        $this->prefs->updateStyleSet($newConfig, $this->now);

        $events = $this->prefs->events();
        $this->assertCount(1, $events);
        $this->assertSame('style_set_updated', $events[0]['action']);
        $this->assertSame('BJCP2025', $events[0]['before']['style_set']);
        $this->assertSame('AABC2025', $events[0]['after']['style_set']);
    }

    public function test_cannot_update_style_set_when_active(): void
    {
        $later = new DateTime('2026-07-21 13:00:00');
        $this->prefs->transitionToState(CompetitionState::Active, $later);

        $newConfig = $this->styleConfig->withStyleSet(StyleSet::AABC2025);

        $this->expectException(PreferencesLockedForCompetitionException::class);
        $this->prefs->updateStyleSet($newConfig, $later);
    }

    public function test_update_judging_config_in_planning(): void
    {
        $newConfig = $this->judgingConfig->withMaxFlightEntries(15);
        $this->prefs->updateJudgingConfig($newConfig, $this->now);

        $this->assertSame(15, $this->prefs->judgingConfig()->maxFlightEntries());
    }

    public function test_update_judging_config_records_event(): void
    {
        $newConfig = $this->judgingConfig->withMaxFlightEntries(15);
        $this->prefs->updateJudgingConfig($newConfig, $this->now);

        $events = $this->prefs->events();
        $this->assertCount(1, $events);
        $this->assertSame('judging_config_updated', $events[0]['action']);
        $this->assertSame(12, $events[0]['before']['max_flight_entries']);
        $this->assertSame(15, $events[0]['after']['max_flight_entries']);
    }

    public function test_cannot_update_judging_config_when_active(): void
    {
        $later = new DateTime('2026-07-21 13:00:00');
        $this->prefs->transitionToState(CompetitionState::Active, $later);

        $newConfig = $this->judgingConfig->withMaxFlightEntries(15);

        $this->expectException(PreferencesLockedForCompetitionException::class);
        $this->prefs->updateJudgingConfig($newConfig, $later);
    }

    public function test_multiple_updates_accumulate_events(): void
    {
        $later1 = new DateTime('2026-07-21 13:00:00');
        $later2 = new DateTime('2026-07-21 14:00:00');
        $later3 = new DateTime('2026-07-21 15:00:00');

        $this->prefs->updateEntryConstraints(
            $this->constraints->withGlobalLimit(10),
            $later1
        );
        $this->prefs->updateStyleSet(
            $this->styleConfig->withStyleSet(StyleSet::AABC2025),
            $later2
        );
        $this->prefs->updateJudgingConfig(
            $this->judgingConfig->withMaxFlightEntries(15),
            $later3
        );

        $events = $this->prefs->events();
        $this->assertCount(3, $events);
        $this->assertSame('entry_constraints_updated', $events[0]['action']);
        $this->assertSame('style_set_updated', $events[1]['action']);
        $this->assertSame('judging_config_updated', $events[2]['action']);
    }

    public function test_state_transition_to_active_then_back_to_planning(): void
    {
        $later = new DateTime('2026-07-21 13:00:00');
        $this->prefs->transitionToState(CompetitionState::Active, $later);
        $this->assertSame(CompetitionState::Active, $this->prefs->competitionState());

        $this->prefs->transitionToState(CompetitionState::Planning, $later);
        $this->assertSame(CompetitionState::Planning, $this->prefs->competitionState());
        $this->assertTrue($this->prefs->canChangePreferences());
    }

    public function test_complete_lifecycle(): void
    {
        $t1 = new DateTime('2026-07-21 12:00:00');
        $t2 = new DateTime('2026-07-21 13:00:00');
        $t3 = new DateTime('2026-07-21 14:00:00');
        $t4 = new DateTime('2026-07-21 15:00:00');

        // Planning: can change preferences
        $this->assertTrue($this->prefs->canChangePreferences());
        $this->prefs->updateEntryConstraints($this->constraints->withGlobalLimit(10), $t1);

        // Transition to Active
        $this->prefs->transitionToState(CompetitionState::Active, $t2);
        $this->assertFalse($this->prefs->canChangePreferences());

        // Try to change (should fail)
        $this->expectException(PreferencesLockedForCompetitionException::class);
        $this->prefs->updateEntryConstraints($this->constraints->withGlobalLimit(8), $t3);
    }

    public function test_event_timestamps_are_preserved(): void
    {
        $t1 = new DateTime('2026-07-21 12:00:00');
        $t2 = new DateTime('2026-07-21 13:00:00');

        $this->prefs->updateEntryConstraints($this->constraints->withGlobalLimit(10), $t1);
        $this->prefs->transitionToState(CompetitionState::Active, $t2);

        $events = $this->prefs->events();
        $this->assertEquals($t1, $events[0]['timestamp']);
        $this->assertEquals($t2, $events[1]['timestamp']);
    }
}
