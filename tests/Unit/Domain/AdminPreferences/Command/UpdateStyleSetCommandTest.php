<?php
declare(strict_types=1);

namespace Tests\Unit\Domain\AdminPreferences\Command;

use Bcoem\Domain\AdminPreferences\Command\UpdateStyleSetCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * UpdateStyleSetCommandTest verifies UpdateStyleSetCommand validation.
 */
final class UpdateStyleSetCommandTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = self::createValidator();
    }

    public function test_valid_style_set_command(): void
    {
        $command = new UpdateStyleSetCommand('BJCP2025');

        $violations = $this->validator->validate($command);
        self::assertCount(0, $violations);
    }

    public function test_valid_style_set_with_allowed_style_ids(): void
    {
        $command = new UpdateStyleSetCommand('BJCP2025', [1, 2, 3]);

        $violations = $this->validator->validate($command);
        self::assertCount(0, $violations);
    }

    public function test_valid_style_set_with_custom_exceptions(): void
    {
        $command = new UpdateStyleSetCommand('BJCP2025', [1, 2], [100, 101]);

        $violations = $this->validator->validate($command);
        self::assertCount(0, $violations);
    }

    public function test_invalid_empty_style_set(): void
    {
        $command = new UpdateStyleSetCommand('');

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_invalid_unknown_style_set(): void
    {
        $command = new UpdateStyleSetCommand('UNKNOWN');

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_all_valid_style_sets(): void
    {
        $validSets = ['BJCP2025', 'BJCP2021', 'BJCP2015', 'AABC2025', 'AABC2022', 'BA'];

        foreach ($validSets as $styleSet) {
            $command = new UpdateStyleSetCommand($styleSet);
            $violations = $this->validator->validate($command);
            self::assertCount(0, $violations, "Failed for style set: $styleSet");
        }
    }


    private static function createValidator(): ValidatorInterface
    {
        $builder = new \Symfony\Component\Validator\ValidatorBuilder();
        return $builder->enableAttributeMapping(true)->getValidator();
    }
}
