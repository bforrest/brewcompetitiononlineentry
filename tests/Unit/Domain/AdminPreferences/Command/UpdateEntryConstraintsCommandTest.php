<?php
declare(strict_types=1);

namespace Tests\Unit\Domain\AdminPreferences\Command;

use Bcoem\Domain\AdminPreferences\Command\UpdateEntryConstraintsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * UpdateEntryConstraintsCommandTest verifies UpdateEntryConstraintsCommand validation.
 */
final class UpdateEntryConstraintsCommandTest extends TestCase
{
    private \Symfony\Component\Validator\Validator\ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = self::createValidator();
    }

    public function test_valid_global_entry_limit(): void
    {
        $command = new UpdateEntryConstraintsCommand(5);

        $violations = $this->validator->validate($command);
        self::assertCount(0, $violations);
    }

    public function test_valid_with_per_style_limits(): void
    {
        $command = new UpdateEntryConstraintsCommand(
            10,
            [1 => 3, 2 => 5]
        );

        $violations = $this->validator->validate($command);
        self::assertCount(0, $violations);
    }

    public function test_valid_with_per_table_limit(): void
    {
        $command = new UpdateEntryConstraintsCommand(
            10,
            [],
            3
        );

        $violations = $this->validator->validate($command);
        self::assertCount(0, $violations);
    }

    public function test_valid_with_sub_category_limits(): void
    {
        $command = new UpdateEntryConstraintsCommand(
            10,
            [],
            null,
            ['IPA' => 2, 'Stout' => 3]
        );

        $violations = $this->validator->validate($command);
        self::assertCount(0, $violations);
    }

    public function test_invalid_global_limit_zero(): void
    {
        $command = new UpdateEntryConstraintsCommand(0);

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_invalid_global_limit_negative(): void
    {
        $command = new UpdateEntryConstraintsCommand(-1);

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_invalid_global_limit_exceeds_max(): void
    {
        $command = new UpdateEntryConstraintsCommand(1000);

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_valid_boundary_limits(): void
    {
        $command1 = new UpdateEntryConstraintsCommand(1);  // Min
        $violations1 = $this->validator->validate($command1);
        self::assertCount(0, $violations1);

        $command2 = new UpdateEntryConstraintsCommand(999);  // Max
        $violations2 = $this->validator->validate($command2);
        self::assertCount(0, $violations2);
    }

    public function test_invalid_per_table_limit_zero(): void
    {
        $command = new UpdateEntryConstraintsCommand(10, [], 0);

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_invalid_per_table_limit_exceeds_max(): void
    {
        $command = new UpdateEntryConstraintsCommand(10, [], 1000);

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_valid_per_table_limit_null(): void
    {
        $command = new UpdateEntryConstraintsCommand(10, [], null);

        $violations = $this->validator->validate($command);
        self::assertCount(0, $violations);
    }

    private static function createValidator(): \Symfony\Component\Validator\Validator\ValidatorInterface
    {
        $builder = new \Symfony\Component\Validator\ValidatorBuilder();
        return $builder->enableAttributeMapping(true)->getValidator();
    }
}
