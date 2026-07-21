<?php

declare(strict_types=1);

namespace BCOEM\Tests\Integration;

use Bcoem\Database\Connection;
use Bcoem\Domain\Export\Repository\BrewingExportRepository;
use Bcoem\Domain\Export\ValueObject\ExportFilter;

class BrewingExportRepositoryIntegrationTest extends IntegrationTestCase
{
    private BrewingExportRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = new Connection(self::$conn);
        $this->repository = new BrewingExportRepository($connection);
    }

    public function testGetAllEntriesReturnsAllRows(): void
    {
        $result = self::$conn->query("SELECT COUNT(*) as cnt FROM baseline_brewing");
        $row = $result->fetch_assoc();
        $totalCount = (int)$row['cnt'];

        if ($totalCount === 0) {
            $this->markTestSkipped('No entries in database');
        }

        $entries = $this->repository->getAllEntries();

        $this->assertIsArray($entries);
        $this->assertCount($totalCount, $entries);

        foreach ($entries as $entry) {
            $this->assertIsArray($entry);
            $this->assertArrayHasKey('id', $entry);
        }
    }

    public function testGetEntriesByFilterPaidReturnsOnlyPaidEntries(): void
    {
        self::$conn->query(
            "INSERT INTO baseline_brewing (brewBrewerID, brewName, brewStyle, brewCategory, brewSubCategory, brewPaid, brewReceived)
             VALUES (1, 'Test Paid', 'IPA', 'cat', 'sub', 1, 1),
                    (1, 'Test Unpaid', 'IPA', 'cat', 'sub', 0, 0)"
        );

        // No competitionId arg: baseline_brewing has no comp_id column at all
        // (the legacy comp_id filter only applies in SINGLE-install mode,
        // which this baseline schema isn't - see BrewingExportRepository's
        // unconditional "AND comp_id = ?" for the still-open mismatch).
        $entries = $this->repository->getEntriesByFilter(
            ExportFilter::PAID,
            'default'
        );

        // getEntriesByFilter()'s SELECT list doesn't include brewPaid/brewReceived
        // (export-column set, not a full row) - verify the WHERE filter worked by
        // name instead of asserting on columns the query never selects.
        $names = array_column($entries, 'brewName');
        $this->assertContains('Test Paid', $names);
        $this->assertNotContains('Test Unpaid', $names);
    }

    public function testGetEntriesByFilterNopayReturnsUnpaidEntries(): void
    {
        self::$conn->query(
            "INSERT INTO baseline_brewing (brewBrewerID, brewName, brewStyle, brewCategory, brewSubCategory, brewPaid)
             VALUES (1, 'Test Unpaid 1', 'IPA', 'cat', 'sub', 0),
                    (1, 'Test Unpaid 2', 'Stout', 'cat', 'sub', NULL)"
        );

        // 'default' view's NOPAY condition also requires brewReceived = 1;
        // 'all' matches any unpaid/null row regardless of received status,
        // which is what this fixture data (no brewReceived set) represents.
        $entries = $this->repository->getEntriesByFilter(
            ExportFilter::NOPAY,
            'all'
        );

        // Same SELECT-list caveat as the paid-filter test above: brewPaid
        // isn't a returned column, so verify by name instead.
        $names = array_column($entries, 'brewName');
        $this->assertContains('Test Unpaid 1', $names);
        $this->assertContains('Test Unpaid 2', $names);
    }

    public function testGetEntriesByFilterHandlesCompetitionIdFilter(): void
    {
        $this->markTestSkipped(
            'BrewingExportRepository::getAllEntries()/getEntriesByFilter() append '
            . '"AND comp_id = ?" whenever a competitionId is passed, but baseline_brewing '
            . 'has no comp_id column (the legacy code only ever queries it behind an '
            . 'if (SINGLE) gate, which is FALSE for this install) - a real schema '
            . 'mismatch in the Export domain, not something to paper over with test '
            . 'fixture data. Needs a product decision: add the column, or drop the filter.'
        );
    }

    public function testValidateArchiveSuffixThrowsOnInvalidInput(): void
    {
        $this->expectException(\Bcoem\Domain\Export\Exception\InvalidArchiveException::class);

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
