<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Service;

final class NullCaptchaVerifier implements CaptchaVerifier
{
    public function verify(array $postData, string $remoteAddr): bool
    {
        return true;
    }
}
