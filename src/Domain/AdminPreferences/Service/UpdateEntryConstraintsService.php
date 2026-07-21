<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Service;

use Bcoem\Domain\AdminPreferences\AdminPreferences;
use Bcoem\Domain\AdminPreferences\Command\UpdateEntryConstraintsCommand;
use Bcoem\Domain\AdminPreferences\Repository\AdminPreferencesRepository;
use Bcoem\Domain\AdminPreferences\ValueObject\EntryConstraints;
use Bcoem\Security\Identity;
use DateTime;

/**
 * UpdateEntryConstraintsService orchestrates entry constraint changes.
 *
 * Responsibilities:
 * - Validate command (ranges, mutually exclusive constraints)
 * - Check if preferences locked
 * - Create EntryConstraints with validation
 * - Call aggregate.updateEntryConstraints()
 * - Return updated aggregate
 */
final class UpdateEntryConstraintsService
{
    public function __construct(
        private readonly AdminPreferencesRepository $repository,
        private readonly PreferencesValidationService $validation
    ) {
    }

    /**
     * Execute entry constraints change with validation.
     *
     * @throws \Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException if constraints invalid
     * @throws \Bcoem\Domain\AdminPreferences\Exception\PreferencesLockedForCompetitionException if locked
     */
    public function execute(UpdateEntryConstraintsCommand $command, Identity $admin): AdminPreferences
    {
        // Validate command
        $this->validation->validateCommand($command);

        // Check for mutually exclusive constraints
        if (!empty($command->perStyleLimits) && $command->perTableLimit !== null) {
            throw new \Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException(
                'Cannot have both per-style limits and per-table limit (mutually exclusive)'
            );
        }

        // Fetch current preferences
        $preferences = $this->repository->getById(1);

        // Create new constraints object (will validate via constructor)
        $newConstraints = new EntryConstraints(
            $command->globalEntryLimit,
            $command->perStyleLimits,
            $command->perTableLimit,
            $command->subCategoryLimits
        );

        // Delegate to aggregate - checks locked state and records event
        $preferences->updateEntryConstraints($newConstraints, new DateTime());

        return $preferences;
    }
}
