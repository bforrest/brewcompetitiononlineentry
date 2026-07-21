<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\ValueObject;

use Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException;

/**
 * CompetitionState represents the lifecycle state of the competition.
 *
 * Valid transitions:
 * - Planning    → Active, Closed (can be reset to Planning for development)
 * - Active      → Planning (revert), Closed
 * - Closed      → (terminal: cannot revert)
 *
 * State semantics:
 * - Planning: No entries yet; preferences can be freely changed; no judging scheduled
 * - Active: Entries accepted; judging scheduled; preferences locked
 * - Closed: Competition over; results finalized; read-only mode
 */
enum CompetitionState: string
{
    case Planning = 'planning';
    case Active = 'active';
    case Closed = 'closed';

    /**
     * Validate that a state transition is allowed.
     *
     * @throws InvalidConstraintException if transition is invalid
     */
    public function transitionTo(CompetitionState $target): self
    {
        if ($this === $target) {
            return $this;
        }

        if (!$this->canTransitionTo($target)) {
            throw new InvalidConstraintException(
                sprintf(
                    'Cannot transition from %s to %s',
                    $this->label(),
                    $target->label()
                )
            );
        }

        return $target;
    }

    /**
     * Check if transition is allowed without throwing.
     */
    public function canTransitionTo(CompetitionState $target): bool
    {
        return match ($this) {
            CompetitionState::Planning => in_array($target, [CompetitionState::Active, CompetitionState::Closed, CompetitionState::Planning], true),
            CompetitionState::Active => in_array($target, [CompetitionState::Planning, CompetitionState::Closed], true),
            CompetitionState::Closed => false,  // Terminal state
        };
    }

    /**
     * Human-readable label for this state.
     */
    public function label(): string
    {
        return match ($this) {
            CompetitionState::Planning => 'Planning',
            CompetitionState::Active => 'Active',
            CompetitionState::Closed => 'Closed',
        };
    }

    /**
     * Description for UI display and documentation.
     */
    public function description(): string
    {
        return match ($this) {
            CompetitionState::Planning => 'Setting up competition; preferences can be changed',
            CompetitionState::Active => 'Entries accepted; judging in progress; preferences locked',
            CompetitionState::Closed => 'Competition over; results finalized; read-only',
        };
    }

    /**
     * Can admin change competition preferences in this state?
     *
     * Only true for Planning state. Once Active or Closed, preferences are locked.
     */
    public function canChangePreferences(): bool
    {
        return match ($this) {
            CompetitionState::Planning => true,
            CompetitionState::Active => false,
            CompetitionState::Closed => false,
        };
    }

    /**
     * Are new entries allowed to be submitted in this state?
     */
    public function allowsNewEntries(): bool
    {
        return match ($this) {
            CompetitionState::Planning => true,
            CompetitionState::Active => true,
            CompetitionState::Closed => false,
        };
    }

    /**
     * Is this state the final/terminal state?
     */
    public function isFinal(): bool
    {
        return match ($this) {
            CompetitionState::Planning => false,
            CompetitionState::Active => false,
            CompetitionState::Closed => true,
        };
    }

    /**
     * CSS class for UI styling (Bootstrap badge classes).
     */
    public function cssClass(): string
    {
        return match ($this) {
            CompetitionState::Planning => 'badge-secondary',
            CompetitionState::Active => 'badge-primary',
            CompetitionState::Closed => 'badge-success',
        };
    }

    /**
     * Get all allowed target states for this state.
     *
     * @return array<CompetitionState>
     */
    public function getAllowedTransitions(): array
    {
        return match ($this) {
            CompetitionState::Planning => [CompetitionState::Active, CompetitionState::Closed, CompetitionState::Planning],
            CompetitionState::Active => [CompetitionState::Planning, CompetitionState::Closed],
            CompetitionState::Closed => [],
        };
    }
}
