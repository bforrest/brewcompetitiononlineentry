<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Command;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * TransitionCompetitionStateCommand is the input DTO for transitioning competition state.
 *
 * Valid states: 'planning', 'active', 'closed'
 *
 * Valid transitions:
 * - planning → active, closed, planning (for development)
 * - active → planning (revert), closed
 * - closed → (terminal: cannot revert)
 */
final class TransitionCompetitionStateCommand
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['planning', 'active', 'closed'])]
    public string $newState;

    public function __construct(string $newState)
    {
        $this->newState = $newState;
    }
}
