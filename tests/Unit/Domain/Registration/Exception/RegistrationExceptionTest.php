<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\Registration\Exception;

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Registration\Exception\RegistrationClosedException;
use Bcoem\Domain\Registration\Exception\DuplicateEmailException;
use Bcoem\Domain\Registration\Exception\CaptchaVerificationFailedException;
use Bcoem\Domain\Registration\Exception\RegistrationException;

class RegistrationExceptionTest extends TestCase
{
    public function test_registration_closed_is_409(): void
    {
        $e = new RegistrationClosedException('closed');
        $this->assertInstanceOf(RegistrationException::class, $e);
        $this->assertSame(409, $e->getHttpStatus());
    }

    public function test_duplicate_email_is_409(): void
    {
        $e = new DuplicateEmailException('dup');
        $this->assertSame(409, $e->getHttpStatus());
    }

    public function test_captcha_failure_is_422(): void
    {
        $e = new CaptchaVerificationFailedException('bad captcha');
        $this->assertSame(422, $e->getHttpStatus());
    }
}
