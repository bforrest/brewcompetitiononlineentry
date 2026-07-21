<?php

declare(strict_types=1);

namespace Bcoem\Tests\Integration\Domain\Export\Repository;

use Bcoem\Database\Connection;
use Bcoem\Domain\Export\Repository\BrewingExportRepository;
use Bcoem\Domain\Export\ValueObject\ExportFilter;
use PHPUnit\Framework\TestCase;

class BrewingExportRepositoryIntegrationTest extends TestCase
{
    private Connection $connection;
    private BrewingExportRepository $repository;
    private \mysqli $mysqli;

    protected function setUp(): void
    {
        // Get real mysqli connection from globals (set by bootstrap)
        $this->mysqli = $GLOBALS['connection'] ?? null;

        if (!$this->mysqli instanceof \mysqli) {
            $this->markTestSkipped('Database connection not available');
        }

        $this->connection = new Connection($this->mysqli);
        $this->repository = new BrewingExportRepository($this->connection);
    }

    public function testGetAllEntriesReturnsAllRows(): void
    {
        // Arrange: Verify database has data
        $result = $this->mysqli->query("SELECT COUNT(*) as cnt FROM baseline_brewing");
        $row = $result->fetch_assoc();
        $totalCount = (int)$row['cnt'];

        if ($totalCount === 0) {
            $this->markTestSkipped('No entries in database');
        }

        // Act
        $entries = $this->repository->getAllEntries();

        // Assert
        $this->assertIsArray($entries);
        $this->assertCount($totalCount, $entries);

        // Verify structure
        foreach ($entries as $entry) {
            $this->assertIsArray($entry);
            $this->assertArrayHasKey('id', $entry);
        }
    }

    public function testGetEntriesByFilterPaidReturnsOnlyPaidEntries(): void
    {
        // Arrange: Insert test data
        $this->mysqli->query(
            "INSERT INTO baseline_brewing (brewBrewerID, brewName, brewStyle, brewCategory, brewSubCategory, brewPaid, brewReceived, comp_id)
             VALUES (1, 'Test Paid', 'IPA', 'cat', 'sub', 1, 1, 1),
                    (1, 'Test Unpaid', 'IPA', 'cat', 'sub', 0, 0, 1)"
        );

        // Act
        $entries = $this->repository->getEntriesByFilter(
            ExportFilter::PAID,
            'default',
            1
        );

        // Assert
        foreach ($entries as $entry) {
            $this->assertSame(1, (int)$entry['brewPaid']);
            $this->assertSame(1, (int)$entry['brewReceived']);
        }

        // Cleanup
        $this->mysqli->query("DELETE FROM baseline_brewing WHERE brewName IN ('Test Paid', 'Test Unpaid')");
    }

    public function testGetEntriesByFilterNopayReturnsUnpaidEntries(): void
    {
        // Arrange
        $this->mysqli->query(
            "INSERT INTO baseline_brewing (brewBrewerID, brewName, brewStyle, brewCategory, brewSubCategory, brewPaid, comp_id)
             VALUES (1, 'Test Unpaid 1', 'IPA', 'cat', 'sub', 0, 1),
                    (1, 'Test Unpaid 2', 'Stout', 'cat', 'sub', NULL, 1)"
        );

        // Act
        $entries = $this->repository->getEntriesByFilter(
            ExportFilter::NOPAY,
            'default',
            1
        );

        // Assert
        foreach ($entries as $entry) {
            $paid = $entry['brewPaid'] ?? null;
            $this->assertTrue($paid === 0 || $paid === null);
        }

        // Cleanup
        $this->mysqli->query("DELETE FROM baseline_brewing WHERE brewName LIKE 'Test Unpaid%'");
    }

    public function testGetEntriesByFilterHandlesCompetitionIdFilter(): void
    {
        // Arrange: Insert test data with different comp_ids
        $this->mysqli->query(
            "INSERT INTO baseline_brewing (brewBrewerID, brewName, brewStyle, brewCategory, brewSubCategory, comp_id)
             VALUES (1, 'Test Comp 1', 'IPA', 'cat', 'sub', 1),
                    (1, 'Test Comp 2', 'IPA', 'cat', 'sub', 2)"
        );

        // Act
        $entries = $this->repository->getAllEntries(1);

        // Assert - results should only include comp_id=1
        $names = array_column($entries, 'brewName');
        $this->assertContains('Test Comp 1', $names);

        // Cleanup
        $this->mysqli->query("DELETE FROM baseline_brewing WHERE brewName LIKE 'Test Comp%'");
    }

    public function testValidateArchiveSuffixThrowsOnInvalidInput(): void
    {
        $this->expectException(\Bcoem\Domain\Export\Exception\InvalidArchiveException::class);

        // Try with invalid archive suffix (contains special characters)
        $this->repository->getEntriesByFilter(
            ExportFilter::ALL,
            'default',
            null,
            "2024'; DROP TABLE--"
        );
    }

    public function testGetEntriesByFilterReturnsCorrectColumns(): void
    {
        $entries = $this->repository->getEntriesByFilter(
            ExportFilter::ALL,
            'default',
            null
        );

        if (empty($entries)) {
            $this->markTestSkipped('No entries in database');
        }

        // Verify expected columns exist
        $firstEntry = $entries[0];
        $expectedColumns = [
            'id', 'brewBrewerFirstName', 'brewBrewerLastName', 'brewCategory',
            'brewSubCategory', 'brewName', 'brewInfo', 'brewStyle'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertArrayHasKey($column, $firstEntry, "Missing column: $column");
        }
    }
}
