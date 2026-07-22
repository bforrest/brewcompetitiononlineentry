<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Exception;

/** Thrown when CAPTCHA verification fails or is missing while required. */
final class CaptchaVerificationFailedException extends RegistrationException
{
    public function getHttpStatus(): int
    {
        return 422;
    }
}
