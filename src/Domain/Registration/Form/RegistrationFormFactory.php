<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Form;

/**
 * Maps a request payload to the state needed to re-render the registration
 * form. It deliberately has no transport, persistence, or session behavior.
 */
final class RegistrationFormFactory
{
    /** @var list<string> */
    private const REQUIRED_FIELDS = [
        'user_name',
        'password',
        'userQuestion',
        'userQuestionAnswer',
        'brewerFirstName',
        'brewerLastName',
        'brewerAddress',
        'brewerCity',
        'brewerZip',
        'brewerCountry',
        'brewerPhone1',
    ];

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $fieldErrors
     * @param list<string> $generalErrors
     */
    public function fromRequest(
        array $input,
        RegistrationFormOptions $options,
        array $fieldErrors = [],
        array $generalErrors = [],
        bool $validate = true,
    ): RegistrationFormData {
        $missingRequiredErrors = [];

        if ($validate) {
            foreach (self::REQUIRED_FIELDS as $name) {
                if (!isset($input[$name]) || $input[$name] === '') {
                    $missingRequiredErrors[$name] = 'This field is required.';
                }
            }
        }

        return new RegistrationFormData(
            values: $input,
            fieldErrors: array_merge($missingRequiredErrors, $fieldErrors),
            generalErrors: $generalErrors,
        );
    }
}
