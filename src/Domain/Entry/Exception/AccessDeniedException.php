<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\Exception;

final class AccessDeniedException extends EntryException
{
    public function getHttpStatus(): int
    {
        return 403;
    }

    public function isExpected(): bool
    {
        return true;
    }
}
