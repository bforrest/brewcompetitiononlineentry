<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Exception;

abstract class RegistrationException extends \RuntimeException
{
    abstract public function getHttpStatus(): int;
}
