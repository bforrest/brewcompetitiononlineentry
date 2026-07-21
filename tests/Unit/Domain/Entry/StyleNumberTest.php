<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Entry\ValueObject\StyleNumber;

class StyleNumberTest extends TestCase
{
    public function test_create_style_number_with_valid_group_and_num(): void
    {
        $style = new StyleNumber('1', 'A');
        $this->assertSame('1', $style->group());
        $this->assertSame('A', $style->num());
    }

    public function test_format_returns_combined_string(): void
    {
        $style = new StyleNumber('1', 'A');
        $this->assertSame('1A', $style->format());
    }

    public function test_format_with_two_digit_group(): void
    {
        $style = new StyleNumber('28', 'C');
        $this->assertSame('28C', $style->format());
    }

    public function test_equals_same_style(): void
    {
        $style1 = new StyleNumber('1', 'A');
        $style2 = new StyleNumber('1', 'A');
        $this->assertTrue($style1->equals($style2));
    }

    public function test_equals_different_group(): void
    {
        $style1 = new StyleNumber('1', 'A');
        $style2 = new StyleNumber('2', 'A');
        $this->assertFalse($style1->equals($style2));
    }

    public function test_equals_different_num(): void
    {
        $style1 = new StyleNumber('1', 'A');
        $style2 = new StyleNumber('1', 'B');
        $this->assertFalse($style1->equals($style2));
    }
}
