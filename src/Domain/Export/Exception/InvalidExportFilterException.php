<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\Exception;

final class InvalidExportFilterException extends ExportException
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
