<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\Exception;

final class InvalidArchiveException extends ExportException
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
