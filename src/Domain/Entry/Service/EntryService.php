<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\Service;

use Bcoem\Database\Connection;
use Bcoem\Domain\Entry\Command\CreateEntryCommand;
use Bcoem\Domain\Entry\Command\UpdateEntryCommand;
use Bcoem\Domain\Entry\Exception\AccessDeniedException;
use Bcoem\Domain\Entry\Exception\EntryNotFoundException;
use Bcoem\Domain\Entry\Repository\EntryRepository;
use Bcoem\Domain\Entry\ValueObject\BrewerId;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use Bcoem\Domain\Entry\ValueObject\StyleNumber;
use Bcoem\Security\Identity;
use Psr\Log\LoggerInterface;

/**
 * Core Entry workflow service. Orchestrates:
 * 1. Validation (via EntryValidationService)
 * 2. Repository operations (create/update/delete)
 * 3. Audit logging (before/after snapshots)
 * 4. Transaction management
 *
 * All writes are transactional and audited.
 */
final class EntryService
{
    public function __construct(
        private Connection $connection,
        private EntryRepository $repository,
        private EntryValidationService $validationService,
        private AuditLogger $auditLogger,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Create a new entry.
     *
     * @return EntryId
     * @throws \Exception from validation or transaction failure
     */
    public function create(CreateEntryCommand $cmd, Identity $identity): EntryId
    {
        // Step 1: Validate command
        $this->validationService->validateCreate($cmd, $identity);

        // Step 2: Begin transaction
        $this->connection->beginTransaction();

        try {
            // Step 3: Build Entry aggregate
            $brewerId = BrewerId::from((int) $cmd->brewBrewerId);
            $style = StyleNumber::from($cmd->brewCategorySort, $cmd->brewSubCategory);

            // TODO: Hydrate BrewerInfo from repository or database
            // For now, placeholder BrewerInfo
            $brewerInfo = new \Bcoem\Domain\Entry\ValueObject\BrewerInfo(
                uid: (int) $cmd->brewBrewerId,
                firstName: (string) $cmd->brewBrewerFirstName,
                lastName: (string) $cmd->brewBrewerLastName,
                email: '',
            );

            $entry = new \Bcoem\Domain\Entry\Entry(
                id: EntryId::from(0), // Will be assigned by repository
                brewerId: $brewerId,
                style: $style,
                name: $cmd->brewName,
                brewer: $brewerInfo,
                confirmed: false,
                paid: false,
                received: false,
            );

            // Step 4: Insert into database
            $entryId = $this->repository->insert($entry);

            // Step 5: Audit log
            $this->auditLogger->record(
                identity: $identity,
                action: 'create',
                entity: 'entry',
                entityId: $entryId->value(),
                beforeJson: null,
                afterJson: (string) json_encode($entry->toArray()),
                ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
            );

            // Step 6: Commit
            $this->connection->commit();

            $this->logger->info('Entry created', ['entryId' => $entryId->value()]);

            return $entryId;
        } catch (\Exception $e) {
            $this->connection->rollback();
            $this->logger->error('Entry creation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Update an existing entry.
     *
     * @throws EntryNotFoundException
     * @throws AccessDeniedException
     * @throws \Exception from validation or transaction failure
     */
    public function update(UpdateEntryCommand $cmd, Identity $identity): void
    {
        // Step 1: Validate command
        $this->validationService->validateUpdate($cmd, $identity);

        // Step 2: Load existing entry
        $entryId = EntryId::from($cmd->id);
        try {
            $existingEntry = $this->repository->getById($entryId);
        } catch (EntryNotFoundException $e) {
            throw $e;
        }

        // Step 3: Check ownership (TODO: once Identity has brewer)
        // For now, assume authorized (will be added to Identity in Phase 3.2)

        // Step 4: Begin transaction
        $this->connection->beginTransaction();

        try {
            // Step 5: Update entry
            $style = StyleNumber::from($cmd->brewCategorySort, $cmd->brewSubCategory);
            $brewerInfo = new \Bcoem\Domain\Entry\ValueObject\BrewerInfo(
                uid: (int) $cmd->brewBrewerId,
                firstName: (string) $cmd->brewBrewerFirstName,
                lastName: (string) $cmd->brewBrewerLastName,
                email: '',
            );

            $updatedEntry = new \Bcoem\Domain\Entry\Entry(
                id: $entryId,
                brewerId: $existingEntry->brewerId(),
                style: $style,
                name: $cmd->brewName,
                brewer: $brewerInfo,
                confirmed: $existingEntry->isConfirmed(),
                paid: $existingEntry->isPaid(),
                received: $existingEntry->isReceived(),
            );

            $this->repository->update($updatedEntry);

            // Step 6: Audit log
            $this->auditLogger->record(
                identity: $identity,
                action: 'update',
                entity: 'entry',
                entityId: $entryId->value(),
                beforeJson: (string) json_encode($existingEntry->toArray()),
                afterJson: (string) json_encode($updatedEntry->toArray()),
                ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
            );

            // Step 7: Commit
            $this->connection->commit();

            $this->logger->info('Entry updated', ['entryId' => $entryId->value()]);
        } catch (\Exception $e) {
            $this->connection->rollback();
            $this->logger->error('Entry update failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete an entry.
     *
     * @throws EntryNotFoundException
     * @throws AccessDeniedException
     * @throws \Exception from transaction failure
     */
    public function delete(EntryId $id, Identity $identity): void
    {
        // Step 1: Load entry (verify exists + check ownership)
        try {
            $entry = $this->repository->getById($id);
        } catch (EntryNotFoundException $e) {
            throw $e;
        }

        // TODO: Check ownership once Identity has brewer

        // Step 2: Begin transaction
        $this->connection->beginTransaction();

        try {
            // Step 3: Delete
            $this->repository->delete($id);

            // Step 4: Audit log
            $this->auditLogger->record(
                identity: $identity,
                action: 'delete',
                entity: 'entry',
                entityId: $id->value(),
                beforeJson: (string) json_encode($entry->toArray()),
                afterJson: null,
                ipAddress: $_SERVER['REMOTE_ADDR'] ?? null,
            );

            // Step 5: Commit
            $this->connection->commit();

            $this->logger->info('Entry deleted', ['entryId' => $id->value()]);
        } catch (\Exception $e) {
            $this->connection->rollback();
            $this->logger->error('Entry deletion failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * List entries for a brewer.
     *
     * @return array<int, \Bcoem\Domain\Entry\Entry>
     */
    public function listByBrewerId(BrewerId $brewerId, int $limit = 50, int $offset = 0): array
    {
        return $this->repository->listByBrewerId($brewerId, $limit, $offset);
    }
}
