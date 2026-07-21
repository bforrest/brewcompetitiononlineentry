<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\Service;

use Bcoem\Domain\Entry\Adapter\LegacyQueryAdapter;
use Bcoem\Domain\Entry\Command\BulkUpdateEntryStatusCommand;
use Bcoem\Domain\Entry\Command\CreateEntryCommand;
use Bcoem\Domain\Entry\Command\UpdateEntryCommand;
use Bcoem\Domain\Entry\Exception\BreweryNameRequiredException;
use Bcoem\Domain\Entry\Exception\EntryLimitReachedException;
use Bcoem\Domain\Entry\Exception\EntryWindowClosedException;
use Bcoem\Domain\Entry\Exception\InvalidStyleSelectionException;
use Bcoem\Domain\Entry\Repository\EntryRepository;
use Bcoem\Domain\Entry\ValueObject\BrewerId;
use Bcoem\Domain\Entry\ValueObject\StyleNumber;
use Bcoem\Security\Identity;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validates entry commands against business rules before they reach EntryService.
 * Checks: entry windows, limits, style validity, brewery requirements.
 */
class EntryValidationService
{
    public function __construct(
        private EntryRepository $repository,
        private ValidatorInterface $validator,
        private StyleService $styleService,
    ) {
    }

    /**
     * Validate a CreateEntryCommand.
     *
     * @throws \Symfony\Component\Validator\Exception\ValidationFailedException
     * @throws InvalidStyleSelectionException
     * @throws EntryLimitReachedException
     * @throws BreweryNameRequiredException
     */
    public function validateCreate(CreateEntryCommand $cmd, Identity $identity): void
    {
        // First: symfony/validator constraints (field-level validation)
        $violations = $this->validator->validate($cmd);
        if (count($violations) > 0) {
            throw new \Symfony\Component\Validator\Exception\ValidationFailedException(
                $cmd,
                $violations
            );
        }

        // Business rules follow

        // 1. Entry window must be open
        if (!$this->isEntryWindowOpen()) {
            throw new EntryWindowClosedException('Entry submission window is closed');
        }

        // 2. Check style validity
        if (!$this->styleService->isStyleAvailable($cmd->brewCategorySort)) {
            throw new InvalidStyleSelectionException('Style ' . $cmd->brewCategorySort . ' is not available');
        }

        // 3. Pro edition: brewery name required
        if ($this->isProEdition() && empty($cmd->brewBrewerFirstName)) {
            throw new BreweryNameRequiredException(
                'Pro edition requires brewery name'
            );
        }

        // 4. Check brewer entry limit
        $brewerLimit = LegacyQueryAdapter::brewerLimits();
        if (!empty($cmd->brewBrewerId)) {
            $brewerEntryCount = $this->repository->countByBrewerId(new BrewerId($cmd->brewBrewerId));
            if ($brewerEntryCount >= $brewerLimit) {
                throw new EntryLimitReachedException(
                    'You have reached your entry limit (' . $brewerLimit . ' entries)'
                );
            }
        }

        // 5. Check subcategory limit
        $subCatLimit = (int) ($_SESSION['prefsUserSubCatLimit'] ?? 3);
        if (!empty($cmd->brewBrewerId)) {
            $subCatCount = $this->repository->countByBrewerIdAndStyle(
                new BrewerId($cmd->brewBrewerId),
                new StyleNumber($cmd->brewCategorySort, $cmd->brewSubCategory)
            );
            if ($subCatCount >= $subCatLimit) {
                throw new EntryLimitReachedException(
                    'You have reached the limit for this style subcategory'
                );
            }
        }
    }

    /**
     * Validate an UpdateEntryCommand.
     *
     * @throws \Symfony\Component\Validator\Exception\ValidationFailedException
     */
    public function validateUpdate(UpdateEntryCommand $cmd, Identity $identity): void
    {
        // Field-level validation
        $violations = $this->validator->validate($cmd);
        if (count($violations) > 0) {
            throw new \Symfony\Component\Validator\Exception\ValidationFailedException(
                $cmd,
                $violations
            );
        }

        // Entry window check
        if (!$this->isEntryWindowOpen()) {
            throw new EntryWindowClosedException('Entry editing window is closed');
        }

        // TODO: add ownership check once Identity has brewer association
    }

    /**
     * Validate a BulkUpdateEntryStatusCommand.
     *
     * @throws \Symfony\Component\Validator\Exception\ValidationFailedException
     */
    public function validateBulkUpdate(BulkUpdateEntryStatusCommand $cmd): void
    {
        $violations = $this->validator->validate($cmd);
        if (count($violations) > 0) {
            throw new \Symfony\Component\Validator\Exception\ValidationFailedException(
                $cmd,
                $violations
            );
        }
    }

    /**
     * Check if the entry submission window is currently open.
     */
    private function isEntryWindowOpen(): bool
    {
        // Session value: 1 = open, 0 = closed, 2 = past deadline
        $windowStatus = (int) ($_SESSION['entry_window_open'] ?? 0);
        return $windowStatus === 1;
    }

    /**
     * Check if this competition is the "pro" edition.
     */
    private function isProEdition(): bool
    {
        return (bool) ($_SESSION['prefsProEdition'] ?? false);
    }
}
