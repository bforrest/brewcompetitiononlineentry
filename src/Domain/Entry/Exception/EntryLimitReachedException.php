<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\Exception;

final class EntryLimitReachedException extends EntryException
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
