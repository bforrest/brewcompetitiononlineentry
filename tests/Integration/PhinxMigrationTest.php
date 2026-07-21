<?php
/**
 * Integration tests for Task 13's Phinx migrations.
 *
 * These do NOT invoke Phinx themselves - the migrations are expected to
 * have already been applied against the shared Docker dev database, either
 * by docker/entrypoint.sh (real container startup - the actual mechanism
 * Part 1 wires up) or by running `vendor/bin/phinx migrate` by hand. This
 * mirrors how the rest of the Integration tier already treats the baseline
 * schema itself (loaded once via docker-entrypoint-initdb.d, not re-created
 * per test) - see IntegrationTestCase's own docblock. What's under test
 * here is the SCHEMA EFFECT of the migrations (the tables/columns/indexes
 * they were supposed to create), not Phinx's own migration-running
 * machinery.
 */

declare(strict_types=1);

namespace BCOEM\Tests\Integration;

class PhinxMigrationTest extends IntegrationTestCase
{
    private function columns(string $table): array
    {
        $result = self::$conn->query(sprintf(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s'",
            self::$conn->real_escape_string(self::$db),
            self::$conn->real_escape_string(self::$pfx . $table)
        ));
        $cols = [];
        while ($row = $result->fetch_assoc()) {
            $cols[] = $row['COLUMN_NAME'];
        }
        return $cols;
    }

    private function indexedColumns(string $table): array
    {
        $result = self::$conn->query(sprintf(
            "SELECT DISTINCT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND INDEX_NAME != 'PRIMARY'",
            self::$conn->real_escape_string(self::$db),
            self::$conn->real_escape_string(self::$pfx . $table)
        ));
        $cols = [];
        while ($row = $result->fetch_assoc()) {
            $cols[] = $row['COLUMN_NAME'];
        }
        return $cols;
    }

    // ── First migration: audit_log table ────────────────────────────────────

    public function testAuditLogTableExistsWithExpectedColumns(): void
    {
        $expected = [
            'id', 'user_id', 'action', 'entity', 'entity_id',
            'before_json', 'after_json', 'ip', 'created_at',
        ];
        $actual = $this->columns('audit_log');

        $this->assertNotEmpty(
            $actual,
            "{$this->prefixedName('audit_log')} does not exist - has `vendor/bin/phinx migrate` "
            . "(or docker/entrypoint.sh, which runs it automatically) been run against this database?"
        );

        foreach ($expected as $column) {
            $this->assertContains($column, $actual, "audit_log is missing expected column '{$column}'");
        }
    }

    public function testAuditLogTableDoesNotTouchLegacySchema(): void
    {
        // The migration is additive-only - confirm it didn't somehow add
        // columns to (or otherwise touch) an existing legacy table under
        // the same migration run. brewing's column list should be exactly
        // what the baseline schema defines, untouched, other than the
        // brewBrewerID index added by the second migration (which is an
        // index, not a column).
        $columns = $this->columns('brewing');
        $this->assertContains('brewBrewerID', $columns);
        $this->assertContains('id', $columns);
        // Sanity: no stray audit-log-shaped column leaked onto this table.
        $this->assertNotContains('before_json', $columns);
    }

    // ── Second migration: Phase 3 repository indexes ────────────────────────

    public function testBrewingHasBrewBrewerIdIndex(): void
    {
        $this->assertContains('brewBrewerID', $this->indexedColumns('brewing'));
    }

    public function testJudgingScoresHasEidAndScoreTableIndexes(): void
    {
        $indexed = $this->indexedColumns('judging_scores');
        $this->assertContains('eid', $indexed);
        $this->assertContains('scoreTable', $indexed);
    }

    public function testJudgingScoresBosHasEidAndBidIndexes(): void
    {
        $indexed = $this->indexedColumns('judging_scores_bos');
        $this->assertContains('eid', $indexed);
        $this->assertContains('bid', $indexed);
    }

    public function testBrewerHasUidIndex(): void
    {
        $this->assertContains('uid', $this->indexedColumns('brewer'));
    }

    private function prefixedName(string $table): string
    {
        return self::$pfx . $table;
    }
}
