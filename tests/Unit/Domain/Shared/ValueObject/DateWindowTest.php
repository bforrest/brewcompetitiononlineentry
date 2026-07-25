<?php

declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\Shared\ValueObject;

use Bcoem\Domain\Shared\ValueObject\DateWindow;
use Bcoem\Domain\Shared\ValueObject\WindowStatus;
use PHPUnit\Framework\TestCase;

final class DateWindowTest extends TestCase
{
    public static function states(): iterable
    {
        yield 'before opening' => [999, WindowStatus::Upcoming];
        yield 'at opening' => [1000, WindowStatus::Open];
        yield 'inside window' => [1500, WindowStatus::Open];
        yield 'at closing' => [2000, WindowStatus::Closed];
        yield 'after closing' => [2001, WindowStatus::Closed];
    }

    /** @dataProvider states */
    public function test_status_matches_half_open_boundaries(int $now, WindowStatus $expected): void
    {
        $window = new DateWindow(1000, 2000);

        self::assertSame($expected, $window->statusAt($now));
        self::assertSame($expected === WindowStatus::Open, $window->isOpenAt($now));
    }

    public function test_rejects_an_inverted_window(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DateWindow(2000, 1000);
    }
}
