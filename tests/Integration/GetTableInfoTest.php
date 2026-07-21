<?php
/**
 * Integration tests for get_table_info().
 *
 * get_table_info($input, $method, $table_id, $db_table, $param, $base_url)
 *
 * Relevant methods tested here:
 *
 *   "basic"    — returns table number, name, location id, table id, styles
 *                as a caret-delimited string.
 *                $table_id = the row id to look up; $param = "default"
 *
 *   "location" — returns judging location info for the given location id.
 *                $input = the judging_locations row id;
 *                $table_id and $param = "default"
 *
 * The function always uses $db_table = "default" for the current competition
 * (not archive tables).
 *
 * Return format for "basic":
 *   tableNumber ^ tableName ^ tableLocation ^ id ^ tableStyles
 *
 * Return format for "location":
 *   judgingDate ^ judgingDateEnd ^ judgingLocName ^ judgingLocation ^
 *   judgingLocType ^ judgingLocNotes
 */

declare(strict_types=1);

namespace BCOEM\Tests\Integration;

class GetTableInfoTest extends IntegrationTestCase
{
    // ── Method "basic" ─────────────────────────────────────────────────────────

    public function testBasicMethodReturnsCaretDelimitedTableInfo(): void
    {
        $locationId = $this->insert('judging_locations', [
            'judgingLocName'  => 'Main Hall',
            'judgingLocation' => '123 Brew St',
            'judgingDate'     => '2026-06-01',
            'judgingDateEnd'  => '2026-06-01',
            'judgingLocType'  => 1,
            'judgingLocNotes' => 'No notes',
        ]);

        $tableId = $this->insert('judging_tables', [
            'tableName'    => 'IPA Table',
            'tableNumber'  => 3,
            'tableLocation'=> $locationId,
            'tableStyles'  => '1,2,3',
        ]);

        $result = get_table_info('', 'basic', $tableId, 'default', 'default');

        // tableNumber ^ tableName ^ tableLocation ^ id ^ tableStyles
        $parts = explode('^', $result);
        $this->assertSame('3',         $parts[0], 'tableNumber at position 0');
        $this->assertSame('IPA Table', $parts[1], 'tableName at position 1');
        $this->assertSame((string)$locationId, $parts[2], 'tableLocation at position 2');
        $this->assertSame((string)$tableId,    $parts[3], 'id at position 3');
        $this->assertSame('1,2,3',     $parts[4], 'tableStyles at position 4');
    }

    public function testBasicMethodWithNonExistentTableIdReturnsNull(): void
    {
        // When the WHERE id='99999' query returns no rows, mysqli_fetch_assoc()
        // returns null.  The function never assigns $result and PHP returns null
        // from a function with no explicit return when control falls off the end.
        // Pin that actual behavior (null) here rather than the '' one might expect.
        $result = get_table_info('', 'basic', 99999, 'default', 'default');

        $this->assertNull($result, 'Non-existent table id should return null (no explicit return path reached)');
    }

    public function testBasicMethodLookupByLocationParam(): void
    {
        $locationId = $this->insert('judging_locations', [
            'judgingLocName'  => 'Side Room',
            'judgingLocation' => '456 Hop Ave',
            'judgingDate'     => '2026-06-01',
            'judgingDateEnd'  => '2026-06-01',
            'judgingLocType'  => 1,
        ]);

        $tableId = $this->insert('judging_tables', [
            'tableName'    => 'Stout Table',
            'tableNumber'  => 7,
            'tableLocation'=> $locationId,
            'tableStyles'  => '14,15',
        ]);

        // When $param != "default", the WHERE clause uses tableLocation instead of id
        $result = get_table_info('', 'basic', 'default', 'default', $locationId);

        $parts = explode('^', $result);
        $this->assertSame('7',           $parts[0], 'tableNumber when filtering by location');
        $this->assertSame('Stout Table', $parts[1], 'tableName when filtering by location');
    }

    // ── Method "location" ──────────────────────────────────────────────────────

    public function testLocationMethodReturnsCaretDelimitedLocationInfo(): void
    {
        $locationId = $this->insert('judging_locations', [
            'judgingLocName'  => 'Brewery Tap Room',
            'judgingLocation' => '789 Malt Blvd, Denver CO',
            'judgingDate'     => '2026-06-14',
            'judgingDateEnd'  => '2026-06-14',
            'judgingLocType'  => 2,
            'judgingLocNotes' => 'Parking available',
        ]);

        // $input = the location id; table_id and param don't matter for "location" method
        $result = get_table_info($locationId, 'location', 'default', 'default', 'default');

        // judgingDate ^ judgingDateEnd ^ judgingLocName ^ judgingLocation ^ judgingLocType ^ judgingLocNotes
        $parts = explode('^', $result);
        $this->assertSame('2026-06-14',           $parts[0], 'judgingDate at position 0');
        $this->assertSame('2026-06-14',           $parts[1], 'judgingDateEnd at position 1');
        $this->assertSame('Brewery Tap Room',     $parts[2], 'judgingLocName at position 2');
        $this->assertSame('789 Malt Blvd, Denver CO', $parts[3], 'judgingLocation at position 3');
        $this->assertSame('2',                    $parts[4], 'judgingLocType at position 4');
        $this->assertSame('Parking available',    $parts[5], 'judgingLocNotes at position 5');
    }

    public function testLocationMethodWithNonExistentLocationReturnsEmptyString(): void
    {
        $result = get_table_info(99999, 'location', 'default', 'default', 'default');

        $this->assertSame('', $result, 'Non-existent location id should return empty string');
    }

    public function testLocationMethodWhenNotesAreNull(): void
    {
        $locationId = $this->insert('judging_locations', [
            'judgingLocName'  => 'No-Notes Room',
            'judgingLocation' => '1 Brew Lane',
            'judgingDate'     => '2026-07-04',
            'judgingDateEnd'  => '2026-07-04',
            'judgingLocType'  => 1,
            // judgingLocNotes intentionally omitted → NULL
        ]);

        $result = get_table_info($locationId, 'location', 'default', 'default', 'default');
        $parts  = explode('^', $result);

        // NULL notes field → empty string in the caret-delimited output
        $this->assertSame('', $parts[5] ?? '', 'NULL notes should appear as empty string at position 5');
    }
}
