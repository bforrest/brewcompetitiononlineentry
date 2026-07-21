<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration;

use Bcoem\Domain\Entry\Entry;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use Bcoem\Domain\Entry\ValueObject\BrewerId;
use Bcoem\Domain\Entry\ValueObject\StyleNumber;
use Bcoem\Domain\Entry\ValueObject\BrewerInfo;
use Bcoem\Database\Connection;
use Bcoem\Domain\Entry\Repository\EntryRepository;

class EntryRepositoryIntegrationTest extends IntegrationTestCase
{
    private EntryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = new Connection(self::$conn);
        $this->repository = new EntryRepository($connection);
    }

    public function test_insert_entry_and_retrieve_by_id(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $brewerId = new BrewerId($brewer['brewerId']);

        $entry = new Entry(
            new EntryId(0),
            $brewerId,
            new StyleNumber('1', 'A'),
            'Test Pale Ale',
            new BrewerInfo('Test', 'Brewer', 'entrant@test.example'),
            false,
            false,
            false,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        $id = $this->repository->insert($entry);

        $retrieved = $this->repository->getById(new EntryId($id));
        $this->assertNotNull($retrieved);
        $this->assertSame('Test Pale Ale', $retrieved->name());
        $this->assertSame($brewerId->value(), $retrieved->brewerId()->value());
    }

    public function test_get_by_id_and_brewer_id_returns_entry(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $brewerId = new BrewerId($brewer['brewerId']);
        $entryId = $this->insertEntry($brewer['brewerId'], '1A', 1, 1, 1);

        $entry = $this->repository->getByIdAndBrewerId(
            new EntryId($entryId),
            $brewerId
        );

        $this->assertNotNull($entry);
        $this->assertSame($entryId, $entry->id()->value());
    }

    public function test_list_by_brewer_id_returns_multiple_entries(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $brewerId = new BrewerId($brewer['brewerId']);

        $this->insertEntry($brewer['brewerId'], '1A');
        $this->insertEntry($brewer['brewerId'], '2B');
        $this->insertEntry($brewer['brewerId'], '3C');

        $entries = $this->repository->listByBrewerId($brewerId, 50);

        $this->assertCount(3, $entries);
        $this->assertSame('Test Beer', $entries[0]->name());
    }

    public function test_count_by_brewer_id(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $brewerId = new BrewerId($brewer['brewerId']);

        $this->insertEntry($brewer['brewerId'], '1A');
        $this->insertEntry($brewer['brewerId'], '2B');

        $count = $this->repository->countByBrewerId($brewerId);

        $this->assertSame(2, $count);
    }

    public function test_count_by_brewer_id_and_style(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $brewerId = new BrewerId($brewer['brewerId']);

        $this->insertEntry($brewer['brewerId'], '1A');
        $this->insertEntry($brewer['brewerId'], '1A');
        $this->insertEntry($brewer['brewerId'], '2B');

        $count = $this->repository->countByBrewerIdAndStyle(
            $brewerId,
            new StyleNumber('1', 'A')
        );

        $this->assertSame(2, $count);
    }

    public function test_update_entry_modifies_database(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $entryId = $this->insertEntry($brewer['brewerId'], '1A');

        $entry = $this->repository->getById(new EntryId($entryId));
        $updated = new Entry(
            $entry->id(),
            $entry->brewerId(),
            $entry->style(),
            'Updated Beer Name',
            $entry->brewer(),
            true, // confirmed
            $entry->isPaid(),
            $entry->isReceived(),
            $entry->createdAt(),
            new \DateTimeImmutable()
        );

        $this->repository->update($updated);

        $retrieved = $this->repository->getById(new EntryId($entryId));
        $this->assertSame('Updated Beer Name', $retrieved->name());
        $this->assertTrue($retrieved->isConfirmed());
    }

    public function test_delete_entry_removes_from_database(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $entryId = $this->insertEntry($brewer['brewerId'], '1A');

        $this->repository->delete(new EntryId($entryId));

        $entry = $this->repository->getById(new EntryId($entryId));
        $this->assertNull($entry);
    }

    public function test_list_by_brewer_id_with_pagination(): void
    {
        $brewer = $this->insertTestUser('entrant@test.example');
        $brewerId = new BrewerId($brewer['brewerId']);

        // Insert 3 entries
        $this->insertEntry($brewer['brewerId'], '1A');
        $this->insertEntry($brewer['brewerId'], '2B');
        $this->insertEntry($brewer['brewerId'], '3C');

        $page1 = $this->repository->listByBrewerId($brewerId, 2, 0);
        $page2 = $this->repository->listByBrewerId($brewerId, 2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);
    }
}
