<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Exception;

/**
 * Thrown when entry constraints are invalid or incompatible.
 *
 * Example: negative limits, perStyleLimits and perTableLimit both set, or invalid style IDs.
 */
final class InvalidConstraintException extends AdminPreferencesException
{
    public function getHttpStatus(): int
    {
        return 422;  // Unprocessable Entity
    }

    public function isExpected(): bool
    {
        return true;  // Admin provided invalid input
    }
}
