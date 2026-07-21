<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\Exception;

final class TableNotFoundException extends JudgingException
{
    public function getHttpStatus(): int
    {
        return 404;
    }

    public function isExpected(): bool
    {
        return true;
    }
}
