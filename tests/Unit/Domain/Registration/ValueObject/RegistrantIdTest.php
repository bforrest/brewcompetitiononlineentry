<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\Registration\ValueObject;

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Registration\ValueObject\RegistrantId;

class RegistrantIdTest extends TestCase
{
    public function test_from_accepts_positive_int(): void
    {
        $id = RegistrantId::from(42);
        $this->assertSame(42, $id->value());
    }

    public function test_from_rejects_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RegistrantId::from(0);
    }

    public function test_from_rejects_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RegistrantId::from(-1);
    }
}
