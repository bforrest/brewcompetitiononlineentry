<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Exception;

/**
 * Thrown when attempting to change preferences after competition is active/closed.
 *
 * Example: cannot change entry limits or judging config after entries have been accepted.
 */
final class PreferencesLockedForCompetitionException extends AdminPreferencesException
{
    public function getHttpStatus(): int
    {
        return 409;  // Conflict
    }

    public function isExpected(): bool
    {
        return true;  // Admin triggered this; not a system error
    }
}
