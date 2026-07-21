<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Command;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * UpdateStyleSetCommand is the input DTO for updating the active style set.
 *
 * Properties:
 * - styleSet: The new style set to use (BJCP2025, BJCP2021, AABC2025, etc.)
 * - allowedStyleIds: Optional filter of which official styles can be entered
 * - customExceptions: Optional IDs of custom/local styles to allow as exceptions
 */
final class UpdateStyleSetCommand
{
    #[Assert\NotBlank(message: 'Style set is required')]
    #[Assert\Choice(choices: ['BJCP2025', 'BJCP2021', 'BJCP2015', 'AABC2025', 'AABC2022', 'BA'], message: 'Invalid style set')]
    public string $styleSet;

    #[Assert\Type(type: 'array')]
    public array $allowedStyleIds = [];

    #[Assert\Type(type: 'array')]
    public array $customExceptions = [];

    public function __construct(string $styleSet, array $allowedStyleIds = [], array $customExceptions = [])
    {
        $this->styleSet = $styleSet;
        $this->allowedStyleIds = $allowedStyleIds;
        $this->customExceptions = $customExceptions;
    }
}
