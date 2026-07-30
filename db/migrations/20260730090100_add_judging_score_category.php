<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddJudgingScoreCategory extends AbstractMigration
{
    public function change(): void
    {
        // The existing `scoreType` column is legacy and documented in the
        // baseline schema as "Relational to id in style_types table" - a
        // style-type foreign key, unrelated to the modern Judging domain's
        // regular/mini-bos/bos score-category concept. Rather than overload
        // scoreType with a second, incompatible meaning, add a dedicated
        // column for it and leave scoreType untouched for its real purpose.
        $table = $this->table('judging_scores');
        $table
            ->addColumn('scoreCategory', 'string', [
                'limit' => 20,
                'null' => true,
                'after' => 'scoreType',
                'comment' => "Judging workflow score category: regular, mini-bos, or bos - distinct from the legacy scoreType style_types FK"
            ])
            ->update();
    }
}
