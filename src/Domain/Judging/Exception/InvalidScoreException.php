<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\Exception;

final class InvalidScoreException extends JudgingException
{
    public function getHttpStatus(): int
    {
        return 422;
    }

    public function isExpected(): bool
    {
        return true;
    }
}
