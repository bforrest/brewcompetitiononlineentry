<?php
declare(strict_types=1);

namespace Tests\Unit\Domain\AdminPreferences\Command;

use Bcoem\Domain\AdminPreferences\Command\UpdateJudgingConfigCommand;
use PHPUnit\Framework\TestCase;

/**
 * UpdateJudgingConfigCommandTest verifies UpdateJudgingConfigCommand validation.
 */
final class UpdateJudgingConfigCommandTest extends TestCase
{
    private \Symfony\Component\Validator\Validator\ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = self::createValidator();
    }

    public function test_valid_judging_config_command(): void
    {
        $command = new UpdateJudgingConfigCommand(
            isQueued: true,
            maxFlightEntries: 12,
            maxBosPerStyle: 7,
            maxRounds: 3
        );

        $violations = $this->validator->validate($command);
        self::assertCount(0, $violations);
    }

    public function test_valid_non_queued_mode(): void
    {
        $command = new UpdateJudgingConfigCommand(
            isQueued: false,
            maxFlightEntries: 10,
            maxBosPerStyle: 5,
            maxRounds: 2
        );

        $violations = $this->validator->validate($command);
        self::assertCount(0, $violations);
    }

    public function test_invalid_flight_entries_zero(): void
    {
        $command = new UpdateJudgingConfigCommand(true, 0, 7, 3);

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_invalid_flight_entries_negative(): void
    {
        $command = new UpdateJudgingConfigCommand(true, -5, 7, 3);

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_invalid_flight_entries_exceeds_max(): void
    {
        $command = new UpdateJudgingConfigCommand(true, 1000, 7, 3);

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_invalid_bos_per_style_zero(): void
    {
        $command = new UpdateJudgingConfigCommand(true, 12, 0, 3);

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_invalid_bos_per_style_exceeds_max(): void
    {
        $command = new UpdateJudgingConfigCommand(true, 12, 1000, 3);

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_invalid_rounds_zero(): void
    {
        $command = new UpdateJudgingConfigCommand(true, 12, 7, 0);

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_invalid_rounds_exceeds_max(): void
    {
        $command = new UpdateJudgingConfigCommand(true, 12, 7, 1000);

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_valid_boundary_values(): void
    {
        // Min values
        $command1 = new UpdateJudgingConfigCommand(true, 1, 1, 1);
        $violations1 = $this->validator->validate($command1);
        self::assertCount(0, $violations1);

        // Max values
        $command2 = new UpdateJudgingConfigCommand(true, 999, 999, 999);
        $violations2 = $this->validator->validate($command2);
        self::assertCount(0, $violations2);
    }

    private static function createValidator(): \Symfony\Component\Validator\Validator\ValidatorInterface
    {
        $builder = new \Symfony\Component\Validator\ValidatorBuilder();
        return $builder->enableAttributeMapping(true)->getValidator();
    }
}
