<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\Exception;

final class TableAlreadyLockedException extends JudgingException
{
    public function getHttpStatus(): int
    {
        return 409;
    }

    public function isExpected(): bool
    {
        return true;
    }
}
