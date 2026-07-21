<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\AdminPreferences\ValueObject\StyleSet;
use Bcoem\Domain\AdminPreferences\ValueObject\StyleSetConfiguration;

class StyleSetConfigurationTest extends TestCase
{
    private StyleSetConfiguration $config;

    protected function setUp(): void
    {
        $this->config = new StyleSetConfiguration(StyleSet::BJCP2025);
    }

    public function test_create_with_default_values(): void
    {
        $this->assertSame(StyleSet::BJCP2025, $this->config->styleSet());
        $this->assertSame([], $this->config->allowedStyleIds());
        $this->assertSame([], $this->config->customExceptions());
    }

    public function test_is_style_allowed_with_no_restrictions(): void
    {
        // With empty allowedStyleIds, all styles are allowed
        $this->assertTrue($this->config->isStyleAllowed(1));
        $this->assertTrue($this->config->isStyleAllowed(999));
    }

    public function test_is_style_allowed_with_restrictions(): void
    {
        $config = new StyleSetConfiguration(StyleSet::BJCP2025, [1, 2, 3]);
        $this->assertTrue($config->isStyleAllowed(1));
        $this->assertTrue($config->isStyleAllowed(2));
        $this->assertFalse($config->isStyleAllowed(4));
        $this->assertFalse($config->isStyleAllowed(999));
    }

    public function test_is_style_allowed_with_custom_exceptions(): void
    {
        $config = new StyleSetConfiguration(StyleSet::BJCP2025, [1, 2], [999, 1000]);
        $this->assertTrue($config->isStyleAllowed(1));  // In allowed
        $this->assertTrue($config->isStyleAllowed(999));  // Custom exception
        $this->assertFalse($config->isStyleAllowed(3));  // Not in allowed or exceptions
    }

    public function test_add_exception_returns_new_instance(): void
    {
        // With restrictions on allowed styles, add exception
        $config = new StyleSetConfiguration(StyleSet::BJCP2025, [1, 2]);
        $newConfig = $config->addException(999);
        $this->assertNotSame($config, $newConfig);
        $this->assertFalse($config->isStyleAllowed(999));  // Not allowed before exception
        $this->assertTrue($newConfig->isStyleAllowed(999));  // Allowed after exception
    }

    public function test_add_duplicate_exception_returns_same_instance(): void
    {
        $config = new StyleSetConfiguration(StyleSet::BJCP2025, [], [999]);
        $newConfig = $config->addException(999);
        $this->assertSame($config, $newConfig);
    }

    public function test_remove_exception_returns_new_instance(): void
    {
        // With restrictions on allowed styles and an exception, remove exception
        $config = new StyleSetConfiguration(StyleSet::BJCP2025, [1, 2], [999]);
        $newConfig = $config->removeException(999);
        $this->assertNotSame($config, $newConfig);
        $this->assertTrue($config->isStyleAllowed(999));  // Allowed before removal (exception)
        $this->assertFalse($newConfig->isStyleAllowed(999));  // Not allowed after removal
    }

    public function test_remove_nonexistent_exception_returns_same_instance(): void
    {
        $newConfig = $this->config->removeException(999);
        $this->assertSame($this->config, $newConfig);
    }

    public function test_with_style_set_returns_new_instance(): void
    {
        $newConfig = $this->config->withStyleSet(StyleSet::AABC2025);
        $this->assertNotSame($this->config, $newConfig);
        $this->assertSame(StyleSet::BJCP2025, $this->config->styleSet());
        $this->assertSame(StyleSet::AABC2025, $newConfig->styleSet());
    }

    public function test_with_style_set_clears_exceptions(): void
    {
        $config = new StyleSetConfiguration(StyleSet::BJCP2025, [], [999]);
        $newConfig = $config->withStyleSet(StyleSet::AABC2025);
        $this->assertSame([], $newConfig->customExceptions());
    }

    public function test_with_style_set_same_set_returns_same_instance(): void
    {
        $newConfig = $this->config->withStyleSet(StyleSet::BJCP2025);
        $this->assertSame($this->config, $newConfig);
    }

    public function test_with_allowed_style_ids_returns_new_instance(): void
    {
        $newConfig = $this->config->withAllowedStyleIds([1, 2, 3]);
        $this->assertNotSame($this->config, $newConfig);
        $this->assertTrue($newConfig->isStyleAllowed(1));
        $this->assertFalse($newConfig->isStyleAllowed(999));
    }

    public function test_with_allowed_style_ids_validates_types(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->config->withAllowedStyleIds([1, 'two', 3]);
    }

    public function test_copy_on_write_semantics(): void
    {
        $config1 = new StyleSetConfiguration(StyleSet::BJCP2025, [], [100]);
        $config2 = $config1->addException(200);
        $config3 = $config2->removeException(100);

        $this->assertSame([100], $config1->customExceptions());
        $this->assertSame([100, 200], $config2->customExceptions());
        $this->assertSame([200], $config3->customExceptions());
    }
}
