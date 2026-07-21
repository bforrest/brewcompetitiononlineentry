<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Task 1 of Phase 3.3 (Part 2): Ensure judging_preferences table exists with audit columns.
 *
 * The judging_preferences table stores competition-wide judging configuration
 * (flight size, number of rounds, scoring rules, table planning, etc.).
 * Like preferences, it is single-row by design (id=1 is canonical).
 *
 * This migration:
 * 1. If the judging_preferences table doesn't exist (fresh install), creates it
 *    with all known columns PLUS the two new audit columns (changedAt, changedBy).
 * 2. If it already exists (all live/existing installs), ALTERs it to ADD the two
 *    audit columns (if not already present) without touching existing data.
 *
 * The audit columns track which admin user changed judging preferences and when,
 * supporting AdminPreferences service in Phase 3.3.
 */
final class EnsureJudgingPreferencesAudit extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('judging_preferences', ['id' => 'id', 'signed' => false]);

        // Only create/add columns if the table doesn't already exist or needs audit columns
        if (!$this->hasTable('judging_preferences')) {
            // Fresh install: create the table with all known columns + audit columns
            $table
                ->addColumn('jPrefsQueued', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Judging queue enabled (Y/N)'])
                ->addColumn('jPrefsFlightEntries', 'integer', ['null' => true, 'comment' => 'Maximum entries per flight'])
                ->addColumn('jPrefsMaxBOS', 'integer', ['null' => true, 'comment' => 'Maximum places awarded for each BOS style type'])
                ->addColumn('jPrefsRounds', 'integer', ['null' => true, 'comment' => 'Maximum rounds per judging location'])
                ->addColumn('jPrefsCapJudges', 'integer', ['null' => true, 'limit' => 3, 'comment' => 'Maximum judge capacity'])
                ->addColumn('jPrefsCapStewards', 'integer', ['null' => true, 'limit' => 3, 'comment' => 'Maximum steward capacity'])
                ->addColumn('jPrefsBottleNum', 'integer', ['null' => true, 'limit' => 3, 'comment' => 'Bottle numbering system'])
                ->addColumn('jPrefsJudgingOpen', 'integer', ['null' => true, 'limit' => 15, 'comment' => 'Unix timestamp when judging opens'])
                ->addColumn('jPrefsJudgingClosed', 'integer', ['null' => true, 'limit' => 15, 'comment' => 'Unix timestamp when judging closes'])
                ->addColumn('jPrefsScoresheet', 'integer', ['null' => true, 'limit' => 2, 'comment' => 'Scoresheet format/version'])
                ->addColumn('jPrefsMinWords', 'integer', ['null' => true, 'limit' => 3, 'comment' => 'Minimum words in judge comments'])
                ->addColumn('jPrefsScoreDispMax', 'integer', ['null' => true, 'limit' => 2, 'comment' => 'Maximum disparity of entry scores between judges'])
                ->addColumn('jPrefsTablePlanning', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Table planning enabled (0/1)'])
                // Audit columns
                ->addColumn('changedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false, 'comment' => 'Timestamp of last change'])
                ->addColumn('changedBy', 'integer', ['null' => true, 'signed' => false, 'comment' => 'FK to users.id; user who made the change; null for system changes'])
                ->create();
        } else {
            // Existing install: just add the audit columns if they don't exist
            // Check if changedAt exists; if not, add both audit columns
            if (!$this->table('judging_preferences')->hasColumn('changedAt')) {
                $table
                    ->addColumn('changedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false, 'comment' => 'Timestamp of last change'])
                    ->addColumn('changedBy', 'integer', ['null' => true, 'signed' => false, 'comment' => 'FK to users.id; user who made the change; null for system changes'])
                    ->update();
            }
        }
    }
}
