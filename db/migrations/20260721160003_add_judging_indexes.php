<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddJudgingIndexes extends AbstractMigration
{
    public function change(): void
    {
        // Index for efficient state queries on judging_tables
        // Typical query: SELECT * FROM judging_tables WHERE tableState='active' ORDER BY tableLocation
        $judging_tables = $this->table('judging_tables');
        $judging_tables
            ->addIndex(['tableState'], [
                'name' => 'idx_judging_tables_state',
                'unique' => false
            ])
            ->addIndex(['tableLocation', 'tableState'], [
                'name' => 'idx_judging_tables_location_state',
                'unique' => false
            ])
            ->update();

        // Indexes for judging_scores queries with optimistic locking
        // Primary query patterns:
        // 1. GET score by table + entry: SELECT * FROM judging_scores WHERE scoreTable=? AND eid=?
        // 2. UPDATE with version check: UPDATE ... WHERE id=? AND version=?
        // 3. LIST scores for table: SELECT * FROM judging_scores WHERE scoreTable=? ORDER BY eid
        // Unique constraint: one score per entry per table
        // Also covers: find all tables where a given entry was scored (eid alone)
        // Also covers optimistic locking: WHERE scoreTable=? AND version=?
        $judging_scores = $this->table('judging_scores');
        $judging_scores
            ->addIndex(['scoreTable', 'eid'], [
                'name' => 'idx_judging_scores_table_entry',
                'unique' => true,
            ])
            ->addIndex(['scoreTable'], [
                'name' => 'idx_judging_scores_table',
                'unique' => false
            ])
            ->addIndex(['eid'], [
                'name' => 'idx_judging_scores_entry',
                'unique' => false,
            ])
            ->addIndex(['scoreTable', 'version'], [
                'name' => 'idx_judging_scores_table_version',
                'unique' => false,
            ])
            ->update();

        // Indexes for judging_flights (flight queue ordering)
        // Query pattern: SELECT * FROM judging_flights WHERE flightTable=? ORDER BY flightRound, flightNumber
        // Also covers: find all flights for a given entry (flightEntryID alone)
        $judging_flights = $this->table('judging_flights');
        $judging_flights
            ->addIndex(['flightTable', 'flightRound', 'flightNumber'], [
                'name' => 'idx_judging_flights_queue',
                'unique' => false,
            ])
            ->addIndex(['flightEntryID'], [
                'name' => 'idx_judging_flights_entry',
                'unique' => false,
            ])
            ->update();

        // Indexes for judging_assignments
        // Query pattern: SELECT * FROM judging_assignments WHERE assignTable=? AND assignRound=?
        // Also covers: find all assignments for a given judge/brewer (bid alone)
        $judging_assignments = $this->table('judging_assignments');
        $judging_assignments
            ->addIndex(['assignTable', 'assignRound'], [
                'name' => 'idx_judging_assignments_table_round',
                'unique' => false,
            ])
            ->addIndex(['bid'], [
                'name' => 'idx_judging_assignments_judge',
                'unique' => false,
            ])
            ->update();
    }
}
