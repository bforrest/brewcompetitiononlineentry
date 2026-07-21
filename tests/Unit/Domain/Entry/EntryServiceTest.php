<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Entry\Entry;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use Bcoem\Domain\Entry\ValueObject\BrewerId;
use Bcoem\Domain\Entry\ValueObject\StyleNumber;
use Bcoem\Domain\Entry\ValueObject\BrewerInfo;
use Bcoem\Domain\Entry\Command\CreateEntryCommand;
use Bcoem\Domain\Entry\Command\UpdateEntryCommand;
use Bcoem\Domain\Entry\Exception\EntryNotFoundException;
use Bcoem\Domain\Entry\Exception\EntryWindowClosedException;
use Bcoem\Domain\Entry\Exception\EntryLimitReachedException;
use Bcoem\Domain\Entry\Repository\EntryRepository;
use Bcoem\Domain\Entry\Service\EntryService;
use Bcoem\Domain\Entry\Service\EntryValidationService;
use Bcoem\Domain\Entry\Service\AuditLogger;
use Bcoem\Domain\Entry\Adapter\LegacyQueryAdapter;
use Bcoem\Security\Identity;
use Bcoem\Security\Role;

class EntryServiceTest extends TestCase
{
    private EntryService $service;
    private EntryRepository $repository;
    private EntryValidationService $validationService;
    private AuditLogger $auditLogger;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntryRepository::class);
        $this->validationService = $this->createMock(EntryValidationService::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);

        $this->service = new EntryService(
            $this->repository,
            $this->validationService,
            $this->auditLogger
        );
    }

    public function test_create_with_valid_command_returns_entry_id(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'user@example.com']);
        $command = new CreateEntryCommand([
            'brewName' => 'Test Beer',
            'brewCategorySort' => '1',
            'brewSubCategory' => 'A',
        ]);

        $this->validationService->expects($this->once())
            ->method('validateCreate');

        $this->repository->expects($this->once())
            ->method('insert')
            ->willReturn(123);

        $this->auditLogger->expects($this->once())
            ->method('record');

        $id = $this->service->create($command, $identity);

        $this->assertInstanceOf(EntryId::class, $id);
        $this->assertSame(123, $id->value());
    }

    public function test_create_calls_validation_service(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'user@example.com']);
        $command = new CreateEntryCommand([
            'brewName' => 'Test Beer',
            'brewCategorySort' => '1',
            'brewSubCategory' => 'A',
        ]);

        $this->validationService->expects($this->once())
            ->method('validateCreate')
            ->with($command, $identity);

        $this->repository->expects($this->once())
            ->method('insert')
            ->willReturn(456);

        $this->auditLogger->expects($this->once())
            ->method('record');

        $this->service->create($command, $identity);
    }

    public function test_update_with_valid_command_calls_repository(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'user@example.com']);
        $command = new UpdateEntryCommand([
            'id' => 123,
            'brewName' => 'Updated Beer',
            'brewCategorySort' => '1',
            'brewSubCategory' => 'A',
        ]);

        $this->validationService->expects($this->once())
            ->method('validateUpdate');

        $this->repository->expects($this->once())
            ->method('update');

        $this->auditLogger->expects($this->once())
            ->method('record');

        $this->service->update($command, $identity);
    }

    public function test_delete_calls_repository(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'user@example.com']);
        $entryId = new EntryId(123);

        $this->validationService->expects($this->once())
            ->method('validateDelete');

        $this->repository->expects($this->once())
            ->method('delete');

        $this->auditLogger->expects($this->once())
            ->method('record');

        $this->service->delete($entryId, $identity);
    }

    public function test_list_returns_entries_by_brewer(): void
    {
        $brewerId = new BrewerId(1);

        $mockEntry = new Entry(
            new EntryId(1),
            new BrewerId(1),
            new StyleNumber('1', 'A'),
            'Test Beer',
            new BrewerInfo('Test', 'Brewer', 'test@example.com'),
            true,
            true,
            true,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $this->repository->expects($this->once())
            ->method('listByBrewerId')
            ->with($brewerId, 50)
            ->willReturn([$mockEntry]);

        $entries = $this->service->listByBrewerId($brewerId, 50);

        $this->assertCount(1, $entries);
        $this->assertSame('Test Beer', $entries[0]->name());
    }
}
