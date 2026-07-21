<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Entry\Entry;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use Bcoem\Domain\Entry\ValueObject\BrewerId;
use Bcoem\Domain\Entry\ValueObject\StyleNumber;
use Bcoem\Domain\Entry\ValueObject\BrewerInfo;
use Bcoem\Database\Connection;
use Bcoem\Domain\Entry\Repository\EntryRepository;

class EntryRepositoryTest extends TestCase
{
    private EntryRepository $repository;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->repository = new EntryRepository($this->connection);
    }

    public function test_row_to_entry_hydrates_entry_from_database_row(): void
    {
        $row = [
            'brewID' => 1,
            'brewBrewerId' => 10,
            'uid' => 10,
            'brewStyle' => '1A',
            'brewCategorySort' => '1',
            'brewSubCategory' => 'A',
            'brewName' => 'Test Beer',
            'brewABV' => '5.5',
            'brewComments' => 'Test comments',
            'brewConfirmed' => 1,
            'brewPaid' => 1,
            'brewReceived' => 1,
            'first_name' => 'Test',
            'last_name' => 'Brewer',
            'email' => 'test@example.com',
            'brewCreated' => '2026-01-01 00:00:00',
            'brewUpdated' => '2026-01-02 00:00:00',
        ];

        // Use reflection to call the private method
        $reflection = new ReflectionClass($this->repository);
        $method = $reflection->getMethod('rowToEntry');
        $method->setAccessible(true);

        $entry = $method->invoke($this->repository, $row);

        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertSame(1, $entry->id()->value());
        $this->assertSame(10, $entry->brewerId()->value());
        $this->assertSame('Test Beer', $entry->name());
        $this->assertTrue($entry->isConfirmed());
        $this->assertTrue($entry->isPaid());
    }

    public function test_count_by_brewer_calls_connection(): void
    {
        $brewerId = new BrewerId(10);

        $this->connection->expects($this->once())
            ->method('selectOne')
            ->willReturn(['count' => 5]);

        $count = $this->repository->countByBrewerId($brewerId);

        $this->assertSame(5, $count);
    }

    public function test_count_by_brewer_and_style_calls_connection(): void
    {
        $brewerId = new BrewerId(10);
        $style = new StyleNumber('1', 'A');

        $this->connection->expects($this->once())
            ->method('selectOne')
            ->willReturn(['count' => 2]);

        $count = $this->repository->countByBrewerIdAndStyle($brewerId, $style);

        $this->assertSame(2, $count);
    }

    public function test_insert_calls_connection_execute(): void
    {
        $entry = new Entry(
            new EntryId(0),
            new BrewerId(10),
            new StyleNumber('1', 'A'),
            'Test Beer',
            new BrewerInfo(10, 'Test', 'Brewer', 'test@example.com'),
            false,
            false,
            false,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $this->connection->expects($this->once())
            ->method('execute')
            ->willReturn(1);

        $this->connection->expects($this->once())
            ->method('lastInsertId')
            ->willReturn(123);

        $id = $this->repository->insert($entry);

        $this->assertSame(123, $id->value());
    }

    public function test_update_calls_connection_execute(): void
    {
        $entry = new Entry(
            new EntryId(123),
            new BrewerId(10),
            new StyleNumber('1', 'A'),
            'Updated Beer',
            new BrewerInfo(10, 'Test', 'Brewer', 'test@example.com'),
            true,
            true,
            true,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );

        $this->connection->expects($this->once())
            ->method('execute')
            ->willReturn(1);

        $this->repository->update($entry);

        // If no exception, update succeeded
        $this->assertTrue(true);
    }

    public function test_delete_calls_connection_execute(): void
    {
        $entryId = new EntryId(123);

        $this->connection->expects($this->once())
            ->method('execute')
            ->willReturn(1);

        $this->repository->delete($entryId);

        // If no exception, delete succeeded
        $this->assertTrue(true);
    }
}
