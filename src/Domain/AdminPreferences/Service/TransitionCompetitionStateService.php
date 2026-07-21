<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Service;

use Bcoem\Domain\AdminPreferences\AdminPreferences;
use Bcoem\Domain\AdminPreferences\Command\TransitionCompetitionStateCommand;
use Bcoem\Domain\AdminPreferences\Repository\AdminPreferencesRepository;
use Bcoem\Domain\AdminPreferences\ValueObject\CompetitionState;
use Bcoem\Security\Identity;
use DateTime;

/**
 * TransitionCompetitionStateService orchestrates competition state transitions.
 *
 * Responsibilities:
 * - Parse new state (CompetitionState enum)
 * - Validate transition is allowed
 * - Call aggregate.transitionToState()
 * - Return updated aggregate
 */
final class TransitionCompetitionStateService
{
    public function __construct(
        private readonly AdminPreferencesRepository $repository,
        private readonly PreferencesValidationService $validation
    ) {
    }

    /**
     * Execute state transition with validation.
     *
     * Valid transitions:
     * - planning → active, closed, planning (for development)
     * - active → planning (revert), closed
     * - closed → (terminal: cannot transition)
     *
     * @throws \Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException if transition invalid
     */
    public function execute(TransitionCompetitionStateCommand $command, Identity $admin): AdminPreferences
    {
        // Validate command
        $this->validation->validateCommand($command);

        // Parse and validate the new state enum
        try {
            $newState = CompetitionState::from($command->newState);
        } catch (\ValueError $e) {
            throw new \Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException(
                sprintf('Invalid competition state: %s', $command->newState)
            );
        }

        // Fetch current preferences
        $preferences = $this->repository->getById(1);

        // Delegate to aggregate - validates transition and records event
        $preferences->transitionToState($newState, new DateTime());

        return $preferences;
    }
}
