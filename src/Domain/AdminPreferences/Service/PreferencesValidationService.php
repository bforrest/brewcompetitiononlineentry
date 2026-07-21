<?php

declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Service;

use Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * PreferencesValidationService validates AdminPreferences command objects
 * using Symfony's attribute-based validator.
 */
final class PreferencesValidationService
{
    public function __construct(
        private readonly ValidatorInterface $validator
    ) {
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
