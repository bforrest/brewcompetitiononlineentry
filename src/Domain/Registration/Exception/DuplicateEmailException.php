<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Exception;

/** Thrown when the submitted email already exists in the users table. */
final class DuplicateEmailException extends RegistrationException
{
    public function getHttpStatus(): int
    {
        return 409;
    }
}
