<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\Exception;

/**
 * Thrown when attempting an invalid state transition on a judging table.
 *
 * Example: cannot transition from Locked back to Active.
 */
final class InvalidStateTransitionException extends JudgingException
{
    public function getHttpStatus(): int
    {
        return 409;  // Conflict
    }

    public function isExpected(): bool
    {
        return true;  // Admin or judge triggered this; not a system error
    }
}
