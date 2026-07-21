<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\AdminPreferences\ValueObject\JudgingConfiguration;
use Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException;

class JudgingConfigurationTest extends TestCase
{
    private JudgingConfiguration $config;

    protected function setUp(): void
    {
        $this->config = new JudgingConfiguration();
    }

    public function test_create_with_defaults(): void
    {
        $this->assertTrue($this->config->isQueued());
        $this->assertSame(12, $this->config->maxFlightEntries());
        $this->assertSame(7, $this->config->maxBosPerStyle());
        $this->assertSame(3, $this->config->maxRounds());
    }

    public function test_create_with_custom_values(): void
    {
        $config = new JudgingConfiguration(
            isQueued: false,
            maxFlightEntries: 15,
            maxBosPerStyle: 5,
            maxRounds: 2
        );
        $this->assertFalse($config->isQueued());
        $this->assertSame(15, $config->maxFlightEntries());
        $this->assertSame(5, $config->maxBosPerStyle());
        $this->assertSame(2, $config->maxRounds());
    }

    public function test_negative_max_flight_entries_throws(): void
    {
        $this->expectException(InvalidConstraintException::class);
        new JudgingConfiguration(maxFlightEntries: -1);
    }

    public function test_zero_max_flight_entries_throws(): void
    {
        $this->expectException(InvalidConstraintException::class);
        new JudgingConfiguration(maxFlightEntries: 0);
    }

    public function test_max_flight_entries_over_999_throws(): void
    {
        $this->expectException(InvalidConstraintException::class);
        new JudgingConfiguration(maxFlightEntries: 1000);
    }

    public function test_negative_max_bos_per_style_throws(): void
    {
        $this->expectException(InvalidConstraintException::class);
        new JudgingConfiguration(maxBosPerStyle: -1);
    }

    public function test_negative_max_rounds_throws(): void
    {
        $this->expectException(InvalidConstraintException::class);
        new JudgingConfiguration(maxRounds: -1);
    }

    public function test_validate_flight_size_within_limit(): void
    {
        $config = new JudgingConfiguration(maxFlightEntries: 12);
        $this->assertTrue($config->validateFlightSize(0));
        $this->assertTrue($config->validateFlightSize(6));
        $this->assertTrue($config->validateFlightSize(12));
    }

    public function test_validate_flight_size_exceeds_limit(): void
    {
        $config = new JudgingConfiguration(maxFlightEntries: 12);
        $this->assertFalse($config->validateFlightSize(13));
        $this->assertFalse($config->validateFlightSize(100));
    }

    public function test_validate_flight_size_negative(): void
    {
        $this->assertFalse($this->config->validateFlightSize(-1));
    }

    public function test_with_max_flight_entries_returns_new_instance(): void
    {
        $newConfig = $this->config->withMaxFlightEntries(15);
        $this->assertNotSame($this->config, $newConfig);
        $this->assertSame(12, $this->config->maxFlightEntries());
        $this->assertSame(15, $newConfig->maxFlightEntries());
    }

    public function test_with_max_flight_entries_same_value_returns_same_instance(): void
    {
        $newConfig = $this->config->withMaxFlightEntries(12);
        $this->assertSame($this->config, $newConfig);
    }

    public function test_with_max_bos_per_style_returns_new_instance(): void
    {
        $newConfig = $this->config->withMaxBosPerStyle(5);
        $this->assertNotSame($this->config, $newConfig);
        $this->assertSame(7, $this->config->maxBosPerStyle());
        $this->assertSame(5, $newConfig->maxBosPerStyle());
    }

    public function test_with_max_rounds_returns_new_instance(): void
    {
        $newConfig = $this->config->withMaxRounds(2);
        $this->assertNotSame($this->config, $newConfig);
        $this->assertSame(3, $this->config->maxRounds());
        $this->assertSame(2, $newConfig->maxRounds());
    }

    public function test_with_is_queued_returns_new_instance(): void
    {
        $newConfig = $this->config->withIsQueued(false);
        $this->assertNotSame($this->config, $newConfig);
        $this->assertTrue($this->config->isQueued());
        $this->assertFalse($newConfig->isQueued());
    }

    public function test_with_is_queued_same_value_returns_same_instance(): void
    {
        $newConfig = $this->config->withIsQueued(true);
        $this->assertSame($this->config, $newConfig);
    }
}
