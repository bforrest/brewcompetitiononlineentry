<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddJudgingScoresVersioning extends AbstractMigration
{
    public function change(): void
    {
        // Add version column to judging_scores for optimistic locking
        // This prevents concurrent modification conflicts when multiple judges score simultaneously.
        // When a judge updates a score, we check that version matches before update;
        // if another judge changed it, version will be higher, update fails, judge retries.
        $table = $this->table('judging_scores');
        $table
            ->addColumn('version', 'integer', [
                'default' => 1,
                'null' => false,
                'after' => 'scoreMiniBOS',
                'comment' => 'Version counter for optimistic locking: incremented on every update'
            ])
            ->addColumn('scoreUpdated', 'datetime', [
                'null' => true,
                'after' => 'version',
                'comment' => 'Timestamp when score was last updated (helps detect concurrent modifications)'
            ])
            ->update();

        // Add same columns to judging_scores_bos for consistency
        $table_bos = $this->table('judging_scores_bos');
        $table_bos
            ->addColumn('version', 'integer', [
                'default' => 1,
                'null' => false,
                'after' => 'scoreType',
                'comment' => 'Version counter for optimistic locking: incremented on every update'
            ])
            ->addColumn('scoreUpdated', 'datetime', [
                'null' => true,
                'after' => 'version',
                'comment' => 'Timestamp when score was last updated'
            ])
            ->update();
    }
}
