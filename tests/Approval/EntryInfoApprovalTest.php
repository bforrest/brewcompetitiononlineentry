<?php
/**
 * Approval (snapshot) tests for entry_info().
 *
 * entry_info($id) queries the `brewing` table for the row with the given id
 * and returns a caret-delimited (^) string of seven fields:
 *
 *   0  brewName
 *   1  brewCategorySort
 *   2  brewSubCategory
 *   3  brewStyle
 *   4  brewCoBrewer
 *   5  brewCategory
 *   6  brewJudgingNumber
 *
 * Snapshot tests are used here instead of per-field assertSame() calls to
 * pin the exact field order and delimiter.  If a field is added, removed, or
 * reordered, the snapshot diff makes the change immediately visible.
 *
 * DB note
 * ───────
 * These tests extend IntegrationTestCase so they inherit the DB connection
 * and the ID-based tearDown() cleanup.  Each test inserts one or two rows
 * and snapshots the full returned string.
 *
 * Running
 * ───────
 *   # First run — creates snapshot files, marks tests incomplete
 *   ./vendor/bin/phpunit --testsuite Approval
 *
 *   # Subsequent runs — compares against saved snapshots
 *   ./vendor/bin/phpunit --testsuite Approval
 *
 *   # To regenerate after an intentional change
 *   UPDATE_SNAPSHOTS=1 ./vendor/bin/phpunit --testsuite Approval
 */

declare(strict_types=1);

namespace BCOEM\Tests\Approval;

use BCOEM\Tests\Integration\IntegrationTestCase;

class EntryInfoApprovalTest extends IntegrationTestCase
{
    use SnapshotAssertions;

    // ── Fully-populated entry ─────────────────────────────────────────────

    /**
     * A complete entry with all tracked fields set — including a co-brewer and
     * a judging number.  Snapshot the raw caret-string to pin field order.
     */
    public function testFullyPopulatedEntrySnapshotsCaret(): void
    {
        $ids = $this->insertTestUser('entry.snapshot@test.example', 'Alice', 'Maltz');

        $entryId = $this->insert('brewing', [
            'brewBrewerID'     => $ids['userId'],
            'brewName'         => 'Autumn Rauchbier',
            'brewStyle'        => '6B',
            'brewCategory'     => '06',
            'brewCategorySort' => '06',
            'brewSubCategory'  => 'B',
            'brewCoBrewer'     => 'Bob Kettle',
            'brewJudgingNumber'=> 'T-06B-001',
            'brewPaid'         => 1,
            'brewReceived'     => 1,
            'brewConfirmed'    => 1,
        ]);

        $result = entry_info($entryId);

        $this->assertIsString($result, 'entry_info must return a string');
        $this->assertStringContainsString('Autumn Rauchbier', $result, 'entry name must be present');
        $this->assertStringContainsString('^', $result, 'result must be caret-delimited');

        $this->assertMatchesSnapshot($result, 'entry_info_fully_populated');
    }

    /**
     * Individual field checks to complement the snapshot.
     * Uses the same fixture to make failures easier to diagnose.
     */
    public function testFieldPositions(): void
    {
        $ids = $this->insertTestUser('fields.check@test.example', 'Field', 'Check');

        $entryId = $this->insert('brewing', [
            'brewBrewerID'     => $ids['userId'],
            'brewName'         => 'Session IPA',
            'brewStyle'        => '21A',
            'brewCategory'     => '21',
            'brewCategorySort' => '21',
            'brewSubCategory'  => 'A',
            'brewCoBrewer'     => 'Co-Brewer Name',
            'brewJudgingNumber'=> 'T-21A-007',
            'brewPaid'         => 1,
            'brewReceived'     => 1,
            'brewConfirmed'    => 1,
        ]);

        $result = entry_info($entryId);
        $parts  = explode('^', $result);

        $this->assertSame('Session IPA',    $parts[0], 'position 0: brewName');
        $this->assertSame('21',             $parts[1], 'position 1: brewCategorySort');
        $this->assertSame('A',              $parts[2], 'position 2: brewSubCategory');
        $this->assertSame('21A',            $parts[3], 'position 3: brewStyle');
        $this->assertSame('Co-Brewer Name', $parts[4], 'position 4: brewCoBrewer');
        $this->assertSame('21',             $parts[5], 'position 5: brewCategory');
        $this->assertSame('T-21A-007',      $parts[6], 'position 6: brewJudgingNumber');
    }

    // ── Entry with NULL optional fields ───────────────────────────────────

    /**
     * Minimal entry — co-brewer and judging number left null.
     * PHP will render NULL as an empty string in the concatenation.
     * Snapshot pins that current behaviour.
     */
    public function testMinimalEntryNullFieldsRenderedAsEmpty(): void
    {
        $ids = $this->insertTestUser('minimal.entry@test.example', 'Min', 'Entry');

        $entryId = $this->insert('brewing', [
            'brewBrewerID'     => $ids['userId'],
            'brewName'         => 'Plain Pale Ale',
            'brewStyle'        => '1A',
            'brewCategory'     => '01',
            'brewCategorySort' => '01',
            'brewSubCategory'  => 'A',
            'brewPaid'         => 1,
            'brewReceived'     => 1,
            'brewConfirmed'    => 1,
            // brewCoBrewer and brewJudgingNumber intentionally omitted (NULL)
        ]);

        $result = entry_info($entryId);
        $parts  = explode('^', $result);

        // co-brewer (position 4) and judging number (position 6) should be empty
        $this->assertSame('', $parts[4], 'null brewCoBrewer concatenates as empty string');
        $this->assertSame('', $parts[6], 'null brewJudgingNumber concatenates as empty string');

        $this->assertMatchesSnapshot($result, 'entry_info_minimal_nulls');
    }

    // ── Non-existent entry id ─────────────────────────────────────────────

    /**
     * When no row matches the given id, mysqli_fetch_assoc() returns null.
     * The function then concatenates null values: "^^^^..." .
     * Pin that actual (all-empty) string behaviour.
     */
    public function testNonExistentIdReturnsCaretDelimitedNulls(): void
    {
        $result = entry_info(999999);

        // The function still returns a string (it concatenates null fields)
        $this->assertIsString($result,
            'entry_info with unknown id should still return a string (null concatenation)');

        $this->assertMatchesSnapshot($result, 'entry_info_nonexistent_id');
    }
}
