<?php
declare(strict_types=1);

namespace Tests\Unit\Domain\AdminPreferences\Command;

use Bcoem\Domain\AdminPreferences\Command\TransitionCompetitionStateCommand;
use PHPUnit\Framework\TestCase;

/**
 * TransitionCompetitionStateCommandTest verifies TransitionCompetitionStateCommand validation.
 */
final class TransitionCompetitionStateCommandTest extends TestCase
{
    private \Symfony\Component\Validator\Validator\ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = self::createValidator();
    }

    public function test_valid_planning_state(): void
    {
        $command = new TransitionCompetitionStateCommand('planning');

        $violations = $this->validator->validate($command);
        self::assertCount(0, $violations);
    }

    public function test_valid_active_state(): void
    {
        $command = new TransitionCompetitionStateCommand('active');

        $violations = $this->validator->validate($command);
        self::assertCount(0, $violations);
    }

    public function test_valid_closed_state(): void
    {
        $command = new TransitionCompetitionStateCommand('closed');

        $violations = $this->validator->validate($command);
        self::assertCount(0, $violations);
    }

    public function test_invalid_empty_state(): void
    {
        $command = new TransitionCompetitionStateCommand('');

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_invalid_unknown_state(): void
    {
        $command = new TransitionCompetitionStateCommand('unknown');

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    public function test_invalid_state_case_sensitive(): void
    {
        $command = new TransitionCompetitionStateCommand('ACTIVE');

        $violations = $this->validator->validate($command);
        self::assertGreaterThan(0, count($violations));
    }

    private static function createValidator(): \Symfony\Component\Validator\Validator\ValidatorInterface
    {
        $builder = new \Symfony\Component\Validator\ValidatorBuilder();
        return $builder->enableAttributeMapping(true)->getValidator();
    }
}
