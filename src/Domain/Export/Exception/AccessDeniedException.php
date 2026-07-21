<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\Exception;

final class AccessDeniedException extends ExportException
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
