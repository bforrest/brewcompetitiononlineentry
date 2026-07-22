<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\Registration\Service;

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Registration\Service\NullCaptchaVerifier;

class NullCaptchaVerifierTest extends TestCase
{
    public function test_always_verifies(): void
    {
        $verifier = new NullCaptchaVerifier();
        $this->assertTrue($verifier->verify([], '127.0.0.1'));
        $this->assertTrue($verifier->verify(['g-recaptcha-response' => ''], '127.0.0.1'));
    }
}
