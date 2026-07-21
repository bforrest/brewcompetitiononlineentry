<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\ValueObject;

use Bcoem\Domain\Judging\Exception\InvalidStateTransitionException;

/**
 * TableState represents the lifecycle state of a judging table.
 *
 * Valid transitions:
 * - Planning    → Active, Archived
 * - Active      → Planning (revert), Judged, Archived
 * - Judged      → Locked, Archived
 * - Locked      → Archived (terminal: cannot revert)
 * - Archived    → (terminal: cannot transition)
 *
 * State semantics:
 * - Planning: Admin is setting up table, assigning styles/judges; no scoring yet
 * - Active: Judges have started scoring; first score transitions table here automatically
 * - Judged: All judging complete at this table; scores are final but not locked
 * - Locked: Scores are immutable; cannot be changed; best-of-show can reference these scores
 * - Archived: Old competition; table is historical only
 */
enum TableState: string
{
    case Planning = 'planning';
    case Active = 'active';
    case Judged = 'judged';
    case Locked = 'locked';
    case Archived = 'archived';

    /**
     * Validate that a state transition is allowed.
     *
     * @throws InvalidStateTransitionException if transition is invalid
     */
    public function transitionTo(TableState $target): self
    {
        if ($this === $target) {
            return $this;
        }

        if (!$this->canTransitionTo($target)) {
            throw new InvalidStateTransitionException(
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
    public function canTransitionTo(TableState $target): bool
    {
        return match ($this) {
            TableState::Planning => in_array($target, [TableState::Active, TableState::Archived], true),
            TableState::Active => in_array($target, [TableState::Planning, TableState::Judged, TableState::Archived], true),
            TableState::Judged => in_array($target, [TableState::Locked, TableState::Archived], true),
            TableState::Locked => in_array($target, [TableState::Archived], true),
            TableState::Archived => false,
        };
    }

    /**
     * Human-readable label for this state.
     */
    public function label(): string
    {
        return match ($this) {
            TableState::Planning => 'Planning',
            TableState::Active => 'Active',
            TableState::Judged => 'Judged',
            TableState::Locked => 'Locked',
            TableState::Archived => 'Archived',
        };
    }

    /**
     * Description for UI display.
     */
    public function description(): string
    {
        return match ($this) {
            TableState::Planning => 'Setting up table; assigning judges and styles',
            TableState::Active => 'Judges are actively scoring entries',
            TableState::Judged => 'All judging complete; scores are final',
            TableState::Locked => 'Scores are immutable; no further changes allowed',
            TableState::Archived => 'Historical table from archived competition',
        };
    }

    /**
     * CSS class for UI styling.
     */
    public function cssClass(): string
    {
        return match ($this) {
            TableState::Planning => 'badge-secondary',
            TableState::Active => 'badge-primary',
            TableState::Judged => 'badge-success',
            TableState::Locked => 'badge-danger',
            TableState::Archived => 'badge-dark',
        };
    }

    /**
     * Can judges still modify scores at this table?
     */
    public function allowsScoring(): bool
    {
        return match ($this) {
            TableState::Planning, TableState::Active => true,
            TableState::Judged, TableState::Locked, TableState::Archived => false,
        };
    }

    /**
     * Is this table still editable by admin?
     */
    public function isEditable(): bool
    {
        return match ($this) {
            TableState::Planning, TableState::Active, TableState::Judged => true,
            TableState::Locked, TableState::Archived => false,
        };
    }
}
