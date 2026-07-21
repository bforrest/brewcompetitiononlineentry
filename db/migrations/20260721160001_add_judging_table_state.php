<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddJudgingTableState extends AbstractMigration
{
    public function change(): void
    {
        // Add tableState column to judging_tables
        // Valid states: planning, active, judged, locked, archived
        // Default state: planning (admin creates table in planning mode before judging starts)
        $table = $this->table('judging_tables');
        $table
            ->addColumn('tableState', 'enum', [
                'values' => ['planning', 'active', 'judged', 'locked', 'archived'],
                'default' => 'planning',
                'null' => false,
                'after' => 'tableStewards',
                'comment' => 'State of judging table: planning=setup, active=judges scoring, judged=scoring complete, locked=final scores, archived=old competition'
            ])
            ->addColumn('tableStateChanged', 'datetime', [
                'null' => true,
                'after' => 'tableState',
                'comment' => 'Timestamp when state last changed (for audit trail)'
            ])
            ->update();
    }
}
