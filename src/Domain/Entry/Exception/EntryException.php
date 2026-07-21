<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\Exception;

/**
 * Base exception for all Entry domain exceptions.
 */
abstract class EntryException extends \DomainException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    abstract public function getHttpStatus(): int;

    abstract public function isExpected(): bool;
}
