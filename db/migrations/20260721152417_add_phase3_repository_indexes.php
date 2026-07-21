<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Task 13, Part 1: indexes Phase 3's repositories will need on today's
 * highest-churn legacy tables. Purely additive (new indexes only - no
 * column/table changes), so this is safe to run against a live legacy
 * database mid-strangler-migration.
 *
 * Every column below was picked from an actual grep pass over
 * lib/common.lib.php and includes/db/*.db.php's WHERE/JOIN predicates, not
 * guessed - see .superpowers/sdd/task-13-report.md for the full citation
 * list per column. None of these tables has any secondary index today
 * beyond its PRIMARY KEY (confirmed against sql/bcoem_baseline_3.0.X.sql
 * before writing this migration), so none of the below duplicates an
 * existing index.
 *
 * One column the task brief's own starting list proposed -
 * baseline_judging_scores.comp_id - is deliberately NOT indexed here: that
 * column does not exist anywhere in the baseline schema. Every
 * `WHERE comp_id=...` reference against this table is gated behind
 * `if (SINGLE)` (includes/db/admin_judging_scores_bos.db.php:52 et al.),
 * and SINGLE is hardcoded FALSE in both paths.php and tests/bootstrap.php -
 * this is dead multi-tenancy code for a feature this install never
 * activates, not a live hot path. Indexing a column that doesn't exist
 * isn't possible in the first place; indexing a real column purely to
 * support code that can never execute isn't warranted either. See the
 * task-13 report for the full trail.
 */
final class AddPhase3RepositoryIndexes extends AbstractMigration
{
    public function change(): void
    {
        // baseline_brewing.brewBrewerID - by far the hottest predicate in
        // the whole app: dozens of `WHERE brewBrewerID='%s'` COUNT/SELECT
        // queries driving the entrant dashboard and admin entry-count
        // widgets (lib/common.lib.php:831,882,946,1012,1017,1107,1112,
        // 1208,1213,1303,1315,1320,1340,1363,2884,2937,3464,3653,3660;
        // includes/db/entries.db.php:7-9,47-49,59,120,148,214), plus the
        // join partner for baseline_brewer.uid in every scores/winners/
        // export query below.
        $this->table('brewing')->addIndex(['brewBrewerID'])->update();

        // baseline_judging_scores.eid - the join predicate
        // (`a.eid = b.id`, b=brewing) in essentially every scoring/results/
        // export query: includes/db/scores.db.php:12,17,20,28,30;
        // includes/db/scores_bestbrewer.db.php:6; includes/db/
        // output_bos_mat.db.php:3,5,9,15,17 (bos table, see below - this
        // one is judging_scores); includes/db/output_entries_export_winner.db.php
        // (6 occurrences); includes/db/winners_category.db.php:24-25;
        // includes/db/winners_subcategory.db.php:21-22.
        $this->table('judging_scores')->addIndex(['eid'])->update();

        // baseline_judging_scores.scoreTable - direct WHERE/ORDER BY target:
        // lib/common.lib.php:2113 (count_single_table), includes/db/
        // admin_judging_tables.db.php:29, includes/db/
        // admin_judging_scores_bos.db.php:33,69,84 (ORDER BY scoreTable).
        $this->table('judging_scores')->addIndex(['scoreTable'])->update();

        // baseline_judging_scores_bos.eid - both a direct WHERE target
        // (includes/db/output_entries_export_extend.db.php:12: `WHERE eid=`)
        // and the join predicate to brewing.id in includes/db/
        // output_results_download_bos.db.php:25,27 and includes/db/
        // scores_bestbrewer.db.php:12.
        $this->table('judging_scores_bos')->addIndex(['eid'])->update();

        // baseline_judging_scores_bos.bid - join predicate to
        // baseline_brewer.uid in includes/db/scores_bestbrewer.db.php:12
        // (`c.uid = a.bid`), the Best-of-Show equivalent of judging_scores'
        // own bid/uid join used throughout the winners/export pipeline.
        $this->table('judging_scores_bos')->addIndex(['bid'])->update();

        // baseline_brewer.uid - the other side of the single most common
        // join in the app's entire scores/winners/export/label rendering
        // pipeline (`c.uid = b.brewBrewerID` / `= a.bid`, dozens of sites
        // across includes/db/*.db.php - see brewBrewerID's own citation
        // list above, every one of those is a join with this column on the
        // other side), and also a direct WHERE/lookup target in its own
        // right: includes/db/brewer.db.php:29,52,243,251,258,265;
        // includes/db/common.db.php:443; lib/common.lib.php:2488,2965.
        $this->table('brewer')->addIndex(['uid'])->update();
    }
}
