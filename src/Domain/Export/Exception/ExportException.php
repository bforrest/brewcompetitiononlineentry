<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\Exception;

abstract class ExportException extends \RuntimeException
{
    abstract public function getHttpStatus(): int;

    abstract public function isExpected(): bool;
}
