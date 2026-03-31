<?php
/**
 * SnapshotAssertions — trait for file-based approval / snapshot testing.
 *
 * Usage
 * ─────
 * 1. Add `use SnapshotAssertions;` to your test class.
 * 2. Call `$this->assertMatchesSnapshot($actualOutput, 'my_test_key')`.
 * 3. First run: no snapshot exists → the output is saved to
 *    __snapshots__/<key>.snap and the test is marked incomplete
 *    ("snapshot created — run again to verify").
 * 4. Second run (and thereafter): output is compared to the saved file.
 *    Any difference fails the test with a descriptive message.
 * 5. To regenerate a snapshot after an intentional behaviour change:
 *       UPDATE_SNAPSHOTS=1 ./vendor/bin/phpunit --testsuite Approval
 *    This overwrites all snapshot files that are exercised during the run.
 *
 * Snapshot storage
 * ────────────────
 * Snapshots are stored alongside the test file in __DIR__ . '/__snapshots__/'.
 * They are plain text files that should be committed to version control —
 * they are the "approved" record of the current behaviour.
 *
 * Key naming
 * ──────────
 * Keys may contain letters, digits, underscores, and hyphens.  Any other
 * character is replaced with an underscore.  Keep keys short and descriptive:
 *   'style_convert_type7_rauchbier'
 *   'style_type_method1_mead'
 */

declare(strict_types=1);

namespace BCOEM\Tests\Approval;

trait SnapshotAssertions
{
    /**
     * Assert that $actual matches the stored snapshot for $key.
     *
     * On the first call with a new key, the snapshot is created and the test
     * is marked incomplete so the developer can review the saved output before
     * considering it "approved".
     *
     * Set the environment variable UPDATE_SNAPSHOTS=1 to force-overwrite an
     * existing snapshot (use after an intentional behaviour change).
     *
     * @param  string $actual  The string to compare or snapshot.
     * @param  string $key     A short, unique identifier for this snapshot.
     */
    protected function assertMatchesSnapshot(string $actual, string $key): void
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        $dir      = __DIR__ . '/__snapshots__/';
        $file     = $dir . $safeName . '.snap';

        // ── Force-update mode ──────────────────────────────────────────────
        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($file, $actual);
            $this->addToAssertionCount(1);
            return;
        }

        // ── First run: create and mark incomplete ──────────────────────────
        if (!file_exists($file)) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($file, $actual);
            $this->addToAssertionCount(1);
            $this->markTestIncomplete(
                "Snapshot created for [{$key}] at:\n  {$file}\n"
                . "Review the saved output, then run again to verify."
            );
            return;
        }

        // ── Compare against stored snapshot ───────────────────────────────
        $expected = file_get_contents($file);
        $this->assertSame(
            $expected,
            $actual,
            "Output does not match approved snapshot [{$key}].\n"
            . "  Snapshot: {$file}\n"
            . "  To approve the new output run:\n"
            . "    UPDATE_SNAPSHOTS=1 ./vendor/bin/phpunit --testsuite Approval"
        );
    }
}
