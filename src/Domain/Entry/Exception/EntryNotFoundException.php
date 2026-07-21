<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\Exception;

final class EntryNotFoundException extends EntryException
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
