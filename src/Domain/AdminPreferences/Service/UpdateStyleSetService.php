<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Service;

use Bcoem\Domain\AdminPreferences\AdminPreferences;
use Bcoem\Domain\AdminPreferences\Command\UpdateStyleSetCommand;
use Bcoem\Domain\AdminPreferences\Repository\AdminPreferencesRepository;
use Bcoem\Domain\AdminPreferences\ValueObject\StyleSet;
use Bcoem\Domain\AdminPreferences\ValueObject\StyleSetConfiguration;
use Bcoem\Security\Identity;
use DateTime;

/**
 * UpdateStyleSetService orchestrates style set changes with validation and audit.
 *
 * Responsibilities:
 * - Validate command (style set exists, allowed styles valid)
 * - Check if preferences locked (throw PreferencesLockedForCompetitionException if Active/Closed)
 * - Create new StyleSetConfiguration with copy-on-write
 * - Call aggregate.updateStyleSet()
 * - Return updated aggregate (will be persisted by controller/command handler)
 */
final class UpdateStyleSetService
{
    public function __construct(
        private readonly AdminPreferencesRepository $repository,
        private readonly PreferencesValidationService $validation
    ) {
    }

    /**
     * Execute style set change with validation and audit trail.
     *
     * @throws \Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException if style set invalid
     * @throws \Bcoem\Domain\AdminPreferences\Exception\PreferencesLockedForCompetitionException if locked
     */
    public function execute(UpdateStyleSetCommand $command, Identity $admin): AdminPreferences
    {
        // Validate command
        $this->validation->validateCommand($command);

        // Parse and validate the style set enum
        try {
            $newStyleSet = StyleSet::from($command->styleSet);
        } catch (\ValueError $e) {
            throw new \Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException(
                sprintf('Invalid style set: %s', $command->styleSet)
            );
        }

        // Fetch current preferences (singleton ID = 1)
        $preferences = $this->repository->getById(1);

        // Delegate to aggregate - checks locked state and records event
        $newConfig = new StyleSetConfiguration(
            $newStyleSet,
            $command->allowedStyleIds,
            $command->customExceptions
        );

        $preferences->updateStyleSet($newConfig, new DateTime());

        return $preferences;
    }
}
