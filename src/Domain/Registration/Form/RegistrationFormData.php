<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Form;

/**
 * One render of a registration form, including attempted values and errors.
 */
final readonly class RegistrationFormData
{
    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $fieldErrors
     * @param list<string> $generalErrors
     */
    public function __construct(
        public array $values,
        public array $fieldErrors = [],
        public array $generalErrors = [],
    ) {
    }
}
