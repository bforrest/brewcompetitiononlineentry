<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration;

use Bcoem\Domain\Entry\Command\CreateEntryCommand;
use Bcoem\Domain\Entry\Command\UpdateEntryCommand;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use Bcoem\Domain\Entry\ValueObject\BrewerId;
use Bcoem\Domain\Entry\Exception\EntryNotFoundException;
use Bcoem\Domain\Entry\Repository\EntryRepository;
use Bcoem\Domain\Entry\Service\EntryService;
use Bcoem\Domain\Entry\Service\EntryValidationService;
use Bcoem\Domain\Entry\Service\AuditLogger;
use Bcoem\Domain\Entry\Service\StyleService;
use Bcoem\Domain\Entry\Adapter\LegacyQueryAdapter;
use Bcoem\Database\Connection;
use Bcoem\Security\Identity;
use Bcoem\Security\Role;

class EntryServiceIntegrationTest extends IntegrationTestCase
{
    private EntryService $service;
    private EntryRepository $repository;
    private EntryValidationService $validationService;
    private AuditLogger $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();

        // EntryValidationService defaults to closed when unset (fail-safe) -
        // this suite exercises create/update, which both require it open.
        $_SESSION['entry_window_open'] = 1;

        $connection = new Connection(self::$conn);
        $this->repository = new EntryRepository($connection);
        $this->validationService = new EntryValidationService(
            $this->repository,
            (new \Symfony\Component\Validator\ValidatorBuilder())
                ->addLoader(new \Symfony\Component\Validator\Mapping\Loader\AttributeLoader())
                ->getValidator(),
            new StyleService(),
        );
        $this->auditLogger = new AuditLogger($connection);
        $this->service = new EntryService(
            $connection,
            $this->repository,
            $this->validationService,
            $this->auditLogger,
            new \Psr\Log\NullLogger(),
        );
    }

    protected function tearDown(): void
    {
        unset($_SESSION['entry_window_open']);
        parent::tearDown();
    }

    public function test_create_entry_with_valid_command(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $identity = Identity::fromSession([
            'loginUsername' => 'entrant@test.example',
            'userLevel' => '2',
        ]);

        $command = new CreateEntryCommand([
            'brewBrewerId' => $brewer['brewerId'],
            'brewName' => 'New Test Ale',
            'brewCategorySort' => '1',
            'brewSubCategory' => 'A',
            'brewABV' => '5.5',
            'brewComments' => 'Test comments',
            'brewCoBrewer' => '',
        ]);

        $id = $this->service->create($command, $identity);

        $this->assertInstanceOf(EntryId::class, $id);
        $entry = $this->repository->getById($id);
        $this->assertNotNull($entry);
        $this->assertSame('New Test Ale', $entry->name());
    }

    public function test_create_entry_writes_audit_log(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $identity = Identity::fromSession([
            'loginUsername' => 'entrant@test.example',
            'userLevel' => '2',
        ]);

        $command = new CreateEntryCommand([
            'brewBrewerId' => $brewer['brewerId'],
            'brewName' => 'Test Beer',
            'brewCategorySort' => '1',
            'brewSubCategory' => 'A',
        ]);

        $id = $this->service->create($command, $identity);

        $auditRows = $this->select('audit_log', 'entity = "Entry" AND entity_id = ' . $id->value());
        $this->assertCount(1, $auditRows);
        $this->assertSame('create', $auditRows[0]['action']);
    }

    public function test_update_entry_with_valid_command(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $entryId = $this->insertEntry($brewer['brewerId'], '1A');

        $identity = Identity::fromSession([
            'loginUsername' => 'entrant@test.example',
            'userLevel' => '2',
        ]);

        $command = new UpdateEntryCommand([
            'id' => $entryId,
            'brewName' => 'Updated Pale Ale',
            'brewCategorySort' => '1',
            'brewSubCategory' => 'A',
        ]);

        $this->service->update($command, $identity);

        $entry = $this->repository->getById(new EntryId($entryId));
        $this->assertSame('Updated Pale Ale', $entry->name());
    }

    public function test_update_entry_writes_audit_log(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $entryId = $this->insertEntry($brewer['brewerId'], '1A');

        $identity = Identity::fromSession([
            'loginUsername' => 'entrant@test.example',
            'userLevel' => '2',
        ]);

        $command = new UpdateEntryCommand([
            'id' => $entryId,
            'brewName' => 'Updated Name',
            'brewCategorySort' => '1',
            'brewSubCategory' => 'A',
        ]);

        $this->service->update($command, $identity);

        $auditRows = $this->select('audit_log', 'entity = "Entry" AND entity_id = ' . $entryId);
        $updateRows = array_filter($auditRows, fn($row) => $row['action'] === 'update');
        $this->assertNotEmpty($updateRows);
    }

    public function test_delete_entry(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $entryId = $this->insertEntry($brewer['brewerId'], '1A');

        $identity = Identity::fromSession([
            'loginUsername' => 'entrant@test.example',
            'userLevel' => '2',
        ]);

        $this->service->delete(new EntryId($entryId), $identity);

        // getById() throws when not found, it never returns null.
        $this->expectException(EntryNotFoundException::class);
        $this->repository->getById(new EntryId($entryId));
    }

    public function test_delete_entry_writes_audit_log(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $entryId = $this->insertEntry($brewer['brewerId'], '1A');

        $identity = Identity::fromSession([
            'loginUsername' => 'entrant@test.example',
            'userLevel' => '2',
        ]);

        $this->service->delete(new EntryId($entryId), $identity);

        $auditRows = $this->select('audit_log', 'entity = "Entry" AND entity_id = ' . $entryId);
        $deleteRows = array_filter($auditRows, fn($row) => $row['action'] === 'delete');
        $this->assertNotEmpty($deleteRows);
    }

    public function test_list_entries_by_brewer(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $this->insertEntry($brewer['brewerId'], '1A');
        $this->insertEntry($brewer['brewerId'], '2B');

        $entries = $this->service->listByBrewerId(new BrewerId($brewer['brewerId']), 50);

        $this->assertCount(2, $entries);
    }
}
