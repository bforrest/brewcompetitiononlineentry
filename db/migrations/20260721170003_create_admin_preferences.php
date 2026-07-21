<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Phase 3.3 Task 1 (corrected): create the tables AdminPreferencesRepository
 * actually reads/writes.
 *
 * The 20260721170001/170002 migrations added changedAt/changedBy audit
 * columns to the legacy `preferences` and `judging_preferences` tables, but
 * src/Domain/AdminPreferences/Repository/AdminPreferencesRepository.php
 * queries two different, dedicated tables (admin_preferences,
 * admin_preferences_events) that no migration ever created - so every real
 * call to getById()/save() would fail with "table doesn't exist". This
 * migration creates the tables that repository has been assuming exist
 * since it landed.
 *
 * Purely additive, per this app's forward-only strangler-migration rule -
 * touches nothing in the legacy schema.
 *
 * admin_preferences is a singleton (id = 1); AdminPreferencesRepository::
 * getById() lazily inserts the default row on first read if missing, so no
 * seed data is required here.
 */
final class CreateAdminPreferences extends AbstractMigration
{
    public function change(): void
    {
        $this->table('admin_preferences', ['id' => 'id', 'signed' => false])
            ->addColumn('competitionState', 'string', ['limit' => 20, 'null' => false, 'default' => 'planning', 'comment' => 'CompetitionState enum value: planning/active/closed'])
            ->addColumn('styleSet', 'string', ['limit' => 20, 'null' => false, 'default' => 'BJCP2025', 'comment' => 'StyleSet enum value, e.g. BJCP2025'])
            ->addColumn('allowedStyleIds', 'text', ['null' => true, 'comment' => 'JSON array of allowed style IDs'])
            ->addColumn('customStyleExceptions', 'text', ['null' => true, 'comment' => 'JSON array of custom style exceptions'])
            ->addColumn('globalEntryLimit', 'integer', ['null' => false, 'default' => 5, 'comment' => 'Max entries per brewer'])
            ->addColumn('perStyleLimits', 'text', ['null' => true, 'comment' => 'JSON object of styleId => limit'])
            ->addColumn('perTableLimit', 'text', ['null' => true, 'comment' => 'JSON-wrapped single per-table limit, mutually exclusive with perStyleLimits'])
            ->addColumn('subCategoryLimits', 'text', ['null' => true, 'comment' => 'JSON object of category => limit'])
            ->addColumn('isQueued', 'boolean', ['null' => false, 'default' => false, 'comment' => 'Judging queue mode enabled'])
            ->addColumn('maxFlightEntries', 'integer', ['null' => false, 'default' => 6, 'comment' => 'Max entries per judging flight'])
            ->addColumn('maxBosPerStyle', 'integer', ['null' => false, 'default' => 3, 'comment' => 'Max Best-of-Show places per style'])
            ->addColumn('maxRounds', 'integer', ['null' => false, 'default' => 2, 'comment' => 'Max judging rounds'])
            ->addColumn('changedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false, 'comment' => 'Timestamp of last change'])
            ->addColumn('changedBy', 'integer', ['null' => true, 'signed' => false, 'comment' => 'FK to users.id; user who made the change; null for system changes'])
            ->create();

        $this->table('admin_preferences_events', ['id' => 'id', 'signed' => false])
            ->addColumn('action', 'string', ['limit' => 64, 'null' => false, 'comment' => 'e.g. state_changed, entry_constraints_updated'])
            ->addColumn('beforeJson', 'text', ['null' => true, 'comment' => 'JSON snapshot of state before the change; null for the first event'])
            ->addColumn('afterJson', 'text', ['null' => false, 'comment' => 'JSON snapshot of full preferences state after the change'])
            ->addColumn('changedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['action'])
            ->addIndex(['changedAt'])
            ->create();
    }
}
