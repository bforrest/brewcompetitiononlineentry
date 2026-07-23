<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Form;

/**
 * Read-side choices and availability needed to render the standard entrant
 * registration form. Population belongs to RegistrationOptionsRepository.
 */
final readonly class RegistrationFormOptions
{
    /**
     * @param array<string, string> $countryChoices
     * @param array<string, string> $stateChoices
     * @param array<string, string> $clubChoices
     * @param array<string, string> $dropOffChoices
     * @param list<string> $securityQuestions
     * @param array<string, bool> $availability
     */
    public function __construct(
        public string $title,
        public string $guidance,
        public array $countryChoices = [],
        public array $stateChoices = [],
        public array $clubChoices = [],
        public array $dropOffChoices = [],
        public array $securityQuestions = [],
        public array $availability = [],
        public bool $registrationOpen = true,
    ) {
    }
}
