<?php
declare(strict_types=1);

namespace Tests\Unit\Domain\AdminPreferences\Service;

use Bcoem\Domain\AdminPreferences\Command\UpdateStyleSetCommand;
use Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException;
use Bcoem\Domain\AdminPreferences\Service\PreferencesValidationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * PreferencesValidationServiceTest verifies PreferencesValidationService.validateCommand().
 */
final class PreferencesValidationServiceTest extends TestCase
{
    private PreferencesValidationService $service;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $builder = new \Symfony\Component\Validator\ValidatorBuilder();
        $this->validator = $builder->enableAttributeMapping(true)->getValidator();
        $this->service = new PreferencesValidationService($this->validator);
    }

    public function test_validate_command_with_valid_command(): void
    {
        $command = new UpdateStyleSetCommand('BJCP2025');

        // Should not throw
        $this->service->validateCommand($command);
        $this->expectNotToPerformAssertions();
    }

    public function test_validate_command_with_invalid_command_throws_exception(): void
    {
        $command = new UpdateStyleSetCommand('');

        $this->expectException(InvalidConstraintException::class);
        $this->service->validateCommand($command);
    }

    public function test_validate_command_error_includes_field_name(): void
    {
        $command = new UpdateStyleSetCommand('');

        try {
            $this->service->validateCommand($command);
            self::fail('Expected InvalidConstraintException to be thrown');
        } catch (InvalidConstraintException $e) {
            self::assertStringContainsString('styleSet', $e->getMessage());
        }
    }

    public function test_validate_command_error_message_helpful(): void
    {
        $command = new UpdateStyleSetCommand('INVALID_SET');

        try {
            $this->service->validateCommand($command);
            self::fail('Expected InvalidConstraintException to be thrown');
        } catch (InvalidConstraintException $e) {
            self::assertStringContainsString('validation failed', $e->getMessage());
        }
    }

    public function test_invalid_constraint_exception_has_422_status(): void
    {
        $exception = new InvalidConstraintException('Test');
        self::assertEquals(422, $exception->getHttpStatus());
    }

    public function test_invalid_constraint_exception_is_expected(): void
    {
        $exception = new InvalidConstraintException('Test');
        self::assertTrue($exception->isExpected());
    }
}
