<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\Registration\ValueObject;

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Registration\ValueObject\Email;

class EmailTest extends TestCase
{
    public function test_from_normalizes_to_lowercase(): void
    {
        $email = Email::from('Entrant@Example.COM');
        $this->assertSame('entrant@example.com', $email->value());
        $this->assertSame('entrant@example.com', (string) $email);
    }

    public function test_from_trims_whitespace(): void
    {
        $email = Email::from('  entrant@example.com  ');
        $this->assertSame('entrant@example.com', $email->value());
    }

    public function test_from_rejects_invalid_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Email::from('not-an-email');
    }

    public function test_from_rejects_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Email::from('');
    }
}
