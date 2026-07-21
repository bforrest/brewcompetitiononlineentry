<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\Exception;

final class BreweryNameRequiredException extends EntryException
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
