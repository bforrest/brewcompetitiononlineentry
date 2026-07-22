<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Service;

/**
 * Verifies a CAPTCHA response submitted with a registration POST.
 * Bound in the DI container by prefsCAPTCHA (see container.php Task 8):
 * NullCaptchaVerifier when prefsCAPTCHA=0 (matches legacy's own bypass and
 * docker/03-e2e-fixtures.sql's prefsCAPTCHA=0 setting for e2e), a real
 * verifier otherwise.
 */
interface CaptchaVerifier
{
    /** @param array<string, mixed> $postData Raw $_POST data from the registration form */
    public function verify(array $postData, string $remoteAddr): bool;
}
