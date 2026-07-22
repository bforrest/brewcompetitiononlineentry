<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Exception;

/** Thrown when registration_open/judge_window_open reads as closed for this request. */
final class RegistrationClosedException extends RegistrationException
{
    public function getHttpStatus(): int
    {
        return 409;
    }
}
