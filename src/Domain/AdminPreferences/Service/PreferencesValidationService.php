<?php

declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Service;

use Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * PreferencesValidationService validates admin preference values and commands.
 *
 * Responsibilities:
 * - Validate preference keys
 * - Validate preference values against type/format rules
 * - Check for required preferences
 * - Validate command objects with Symfony validator
 */
final class PreferencesValidationService
{
    public function __construct(
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Validate a preference key.
     *
     * @param string $key Preference key
     * @return bool True if valid, throws exception otherwise
     */
    public function validateKey(string $key): bool
    {
        // TODO: Implement in Task 3
        return true;
    }

    /**
     * Validate a preference value.
     *
     * @param string $key Preference key
     * @param mixed $value Preference value
     * @return bool True if valid, throws exception otherwise
     */
    public function validateValue(string $key, mixed $value): bool
    {
        // TODO: Implement in Task 3
        return true;
    }

    /**
     * Validate all required preferences are present.
     *
     * @param array<string, mixed> $preferences Preferences to validate
     * @return bool True if valid, throws exception otherwise
     */
    public function validateRequired(array $preferences): bool
    {
        // TODO: Implement in Task 3
        return true;
    }

    /**
     * Validate a command object using Symfony validator.
     *
     * Runs all #[Assert\...] constraints on the command object's properties.
     * Throws InvalidConstraintException if validation fails with field-level error messages.
     *
     * @param object $command The command object to validate
     * @throws InvalidConstraintException if validation fails
     */
    public function validateCommand(object $command): void
    {
        $violations = $this->validator->validate($command);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $field = $violation->getPropertyPath();
                $message = $violation->getMessage();
                $errors[$field] = $message;
            }

            throw new InvalidConstraintException(
                sprintf(
                    'Command validation failed: %s',
                    json_encode($errors, JSON_UNESCAPED_SLASHES)
                )
            );
        }
    }
}
