<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Service;

use Bcoem\Domain\AdminPreferences\AdminPreferences;
use Bcoem\Domain\AdminPreferences\Command\UpdateJudgingConfigCommand;
use Bcoem\Domain\AdminPreferences\Repository\AdminPreferencesRepository;
use Bcoem\Domain\AdminPreferences\ValueObject\JudgingConfiguration;
use Bcoem\Security\Identity;
use DateTime;

/**
 * UpdateJudgingConfigService orchestrates judging configuration changes.
 *
 * Responsibilities:
 * - Validate command
 * - Check if preferences locked
 * - Create JudgingConfiguration with validation
 * - Call aggregate.updateJudgingConfig()
 * - Return updated aggregate
 */
final class UpdateJudgingConfigService
{
    public function __construct(
        private readonly AdminPreferencesRepository $repository,
        private readonly PreferencesValidationService $validation
    ) {
    }

    /**
     * Execute judging configuration change with validation.
     *
     * @throws \Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException if config invalid
     * @throws \Bcoem\Domain\AdminPreferences\Exception\PreferencesLockedForCompetitionException if locked
     */
    public function execute(UpdateJudgingConfigCommand $command, Identity $admin): AdminPreferences
    {
        // Validate command
        $this->validation->validateCommand($command);

        // Fetch current preferences
        $preferences = $this->repository->getById(1);

        // Create new judging configuration (will validate via constructor)
        $newConfig = new JudgingConfiguration(
            $command->isQueued,
            $command->maxFlightEntries,
            $command->maxBosPerStyle,
            $command->maxRounds
        );

        // Delegate to aggregate - checks locked state and records event
        $preferences->updateJudgingConfig($newConfig, new DateTime());

        return $preferences;
    }
}
