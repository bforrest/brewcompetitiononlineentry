<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\AdminPreferences\ValueObject\StyleSet;

class StyleSetTest extends TestCase
{
    public function test_bjcp2025_enum_case(): void
    {
        $this->assertSame('BJCP2025', StyleSet::BJCP2025->value);
    }

    public function test_aabc2025_enum_case(): void
    {
        $this->assertSame('AABC2025', StyleSet::AABC2025->value);
    }

    public function test_ba_enum_case(): void
    {
        $this->assertSame('BA', StyleSet::BA->value);
    }

    public function test_label_returns_human_readable_name(): void
    {
        $this->assertSame('BJCP 2025 Guidelines', StyleSet::BJCP2025->label());
        $this->assertSame('BJCP 2021 Guidelines', StyleSet::BJCP2021->label());
        $this->assertSame('AABC 2025 Guidelines', StyleSet::AABC2025->label());
    }

    public function test_is_active_returns_true_for_current_standards(): void
    {
        $this->assertTrue(StyleSet::BJCP2025->isActive());
        $this->assertTrue(StyleSet::AABC2025->isActive());
    }

    public function test_is_active_returns_false_for_older_standards(): void
    {
        $this->assertFalse(StyleSet::BJCP2021->isActive());
        $this->assertFalse(StyleSet::BJCP2015->isActive());
        $this->assertFalse(StyleSet::AABC2022->isActive());
        $this->assertFalse(StyleSet::BA->isActive());
    }

    public function test_supported_styles_returns_positive_count(): void
    {
        $this->assertGreaterThan(0, StyleSet::BJCP2025->supportedStyles());
        $this->assertGreaterThan(0, StyleSet::AABC2025->supportedStyles());
        $this->assertGreaterThan(0, StyleSet::BA->supportedStyles());
    }

    public function test_supported_styles_bjcp2025_returns_32(): void
    {
        $this->assertSame(32, StyleSet::BJCP2025->supportedStyles());
    }

    public function test_description_returns_non_empty_string(): void
    {
        foreach (StyleSet::cases() as $set) {
            $this->assertNotEmpty($set->description());
        }
    }
}
