<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences;

use Bcoem\Domain\AdminPreferences\ValueObject\CompetitionState;
use Bcoem\Domain\AdminPreferences\ValueObject\EntryConstraints;
use Bcoem\Domain\AdminPreferences\ValueObject\JudgingConfiguration;
use Bcoem\Domain\AdminPreferences\ValueObject\PreferencesId;
use Bcoem\Domain\AdminPreferences\ValueObject\StyleSetConfiguration;
use Bcoem\Domain\AdminPreferences\Exception\PreferencesLockedForCompetitionException;
use DateTime;

/**
 * AdminPreferences is the aggregate root for competition-wide settings.
 *
 * Responsibilities:
 * - Manage style set configuration (which guidelines, allowed styles, exceptions)
 * - Manage entry constraints (limits per brewer, per style, etc.)
 * - Manage judging configuration (queued mode, flight size, BOS settings, rounds)
 * - Track competition state (Planning → Active → Closed)
 * - Enforce state machine invariants and business rules
 * - Record all changes in audit trail for compliance
 *
 * Key invariants:
 * - Preferences are singleton (always ID 1)
 * - State transitions are unidirectional: Planning → Active → Closed (with Planning ↔ Active for development)
 * - Preferences cannot be changed once Active/Closed (unless reverting to Planning)
 * - All state changes are recorded in events array for audit trail
 *
 * Immutable except for controlled mutations via methods.
 */
final class AdminPreferences
{
    /**
     * Domain events recorded during the lifecycle of preferences.
     * Used for audit trail and event sourcing.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $events = [];

    public function __construct(
        private readonly PreferencesId $id,
        private StyleSetConfiguration $styleSetConfig,
        private EntryConstraints $entryConstraints,
        private JudgingConfiguration $judgingConfig,
        private CompetitionState $competitionState,
        private DateTime $stateChangedAt,
    ) {
    }

    public function id(): PreferencesId
    {
        return $this->id;
    }

    public function styleSetConfig(): StyleSetConfiguration
    {
        return $this->styleSetConfig;
    }

    public function entryConstraints(): EntryConstraints
    {
        return $this->entryConstraints;
    }

    public function judgingConfig(): JudgingConfiguration
    {
        return $this->judgingConfig;
    }

    public function competitionState(): CompetitionState
    {
        return $this->competitionState;
    }

    public function stateChangedAt(): DateTime
    {
        return $this->stateChangedAt;
    }

    /**
     * Can preferences be changed in the current state?
     *
     * Delegates to the competition state's permission check.
     */
    public function canChangePreferences(): bool
    {
        return $this->competitionState->canChangePreferences();
    }

    /**
     * Transition competition to a new state.
     *
     * Records the transition in the event log for audit trail.
     *
     * @throws PreferencesLockedForCompetitionException if moving to Active/Closed state
     * @throws \Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException if transition is invalid
     */
    public function transitionToState(CompetitionState $newState, DateTime $now): void
    {
        if ($newState === $this->competitionState) {
            return;
        }

        // Validate the transition
        $validatedState = $this->competitionState->transitionTo($newState);

        $oldState = $this->competitionState;
        $this->competitionState = $validatedState;
        $this->stateChangedAt = $now;

        $this->recordEvent('state_changed', 'admin_preferences', [
            'state' => $oldState->value,
        ], [
            'state' => $newState->value,
        ], $now);
    }

    /**
     * Update entry constraints.
     *
     * Validates that preferences are not locked before applying the update.
     *
     * @throws PreferencesLockedForCompetitionException if preferences are locked
     */
    public function updateEntryConstraints(EntryConstraints $new, DateTime $now): void
    {
        if (!$this->canChangePreferences()) {
            throw new PreferencesLockedForCompetitionException(
                'Cannot change entry constraints: competition is not in Planning state'
            );
        }

        $before = [
            'global_limit' => $this->entryConstraints->globalEntryLimit(),
            'per_style_limits' => $this->entryConstraints->perStyleLimits(),
            'per_table_limit' => $this->entryConstraints->perTableLimit(),
            'sub_category_limits' => $this->entryConstraints->subCategoryLimits(),
        ];

        $this->entryConstraints = $new;

        $after = [
            'global_limit' => $new->globalEntryLimit(),
            'per_style_limits' => $new->perStyleLimits(),
            'per_table_limit' => $new->perTableLimit(),
            'sub_category_limits' => $new->subCategoryLimits(),
        ];

        $this->recordEvent('entry_constraints_updated', 'admin_preferences', $before, $after, $now);
    }

    /**
     * Update style set configuration.
     *
     * Validates that preferences are not locked before applying the update.
     *
     * @throws PreferencesLockedForCompetitionException if preferences are locked
     */
    public function updateStyleSet(StyleSetConfiguration $new, DateTime $now): void
    {
        if (!$this->canChangePreferences()) {
            throw new PreferencesLockedForCompetitionException(
                'Cannot change style set: competition is not in Planning state'
            );
        }

        $before = [
            'style_set' => $this->styleSetConfig->styleSet()->value,
            'allowed_style_ids' => $this->styleSetConfig->allowedStyleIds(),
            'custom_exceptions' => $this->styleSetConfig->customExceptions(),
        ];

        $this->styleSetConfig = $new;

        $after = [
            'style_set' => $new->styleSet()->value,
            'allowed_style_ids' => $new->allowedStyleIds(),
            'custom_exceptions' => $new->customExceptions(),
        ];

        $this->recordEvent('style_set_updated', 'admin_preferences', $before, $after, $now);
    }

    /**
     * Update judging configuration.
     *
     * Validates that preferences are not locked before applying the update.
     *
     * @throws PreferencesLockedForCompetitionException if preferences are locked
     */
    public function updateJudgingConfig(JudgingConfiguration $new, DateTime $now): void
    {
        if (!$this->canChangePreferences()) {
            throw new PreferencesLockedForCompetitionException(
                'Cannot change judging configuration: competition is not in Planning state'
            );
        }

        $before = [
            'is_queued' => $this->judgingConfig->isQueued(),
            'max_flight_entries' => $this->judgingConfig->maxFlightEntries(),
            'max_bos_per_style' => $this->judgingConfig->maxBosPerStyle(),
            'max_rounds' => $this->judgingConfig->maxRounds(),
        ];

        $this->judgingConfig = $new;

        $after = [
            'is_queued' => $new->isQueued(),
            'max_flight_entries' => $new->maxFlightEntries(),
            'max_bos_per_style' => $new->maxBosPerStyle(),
            'max_rounds' => $new->maxRounds(),
        ];

        $this->recordEvent('judging_config_updated', 'admin_preferences', $before, $after, $now);
    }

    /**
     * Get all recorded events for audit trail.
     *
     * @return array<int, array<string, mixed>>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Record an event in the audit trail.
     *
     * @param string $action The action that occurred
     * @param string $entity The entity type
     * @param array<string, mixed> $before Previous state
     * @param array<string, mixed> $after New state
     * @param DateTime $timestamp When the change occurred
     */
    private function recordEvent(
        string $action,
        string $entity,
        array $before,
        array $after,
        DateTime $timestamp
    ): void {
        $this->events[] = [
            'action' => $action,
            'entity' => $entity,
            'before' => $before,
            'after' => $after,
            'timestamp' => $timestamp,
        ];
    }
}
