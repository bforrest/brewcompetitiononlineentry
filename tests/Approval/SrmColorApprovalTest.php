<?php
/**
 * Approval (snapshot) tests for srm_color().
 *
 * srm_color($srm, $method) maps an SRM (or EBC, when method="ebc") value to
 * a CSS hex color string.  It contains 33 explicit elseif branches covering
 * integers 1–31 plus ">31", and a catch-all that returns white ("#ffffff")
 * for values below 1.
 *
 * Writing 33 individual assertSame() calls would be tedious to maintain and
 * hard to review.  Instead, a single snapshot of the full table captures the
 * entire mapping at once.  If a hex value is ever changed (intentionally or
 * by mistake), the snapshot diff pinpoints exactly which SRM band was altered.
 *
 * Snapshot strategy
 * ─────────────────
 * • testSrmColorFullTable — walks SRM 0 to 32 in integer steps and records
 *   "SRM=<n>  →  <hex>" for each, producing a human-readable lookup table.
 * • testEbcConversionMidRange — verifies that EBC inputs are converted to SRM
 *   via (1.97 × EBC) before the lookup, so the returned colour matches the
 *   equivalent SRM bucket.
 * • testBoundaryValues — pins the edge cases: 0 (below range → white),
 *   SRM=31 (last explicit bucket), SRM=31.5 (still in the ≥31 bucket),
 *   SRM=32 (triggers the >31 catch-all → black).
 *
 * These tests are pure (no DB access).
 */

declare(strict_types=1);

namespace BCOEM\Tests\Approval;

use PHPUnit\Framework\TestCase;

class SrmColorApprovalTest extends TestCase
{
    use SnapshotAssertions;

    // ── Full SRM lookup table ──────────────────────────────────────────────

    /**
     * Walk integer SRM values 0–32 and snapshot the complete mapping.
     * The output is a human-readable table:
     *   SRM= 0  →  #ffffff
     *   SRM= 1  →  #f3f993
     *   ...
     */
    public function testSrmColorFullTable(): void
    {
        $rows = [];
        for ($srm = 0; $srm <= 32; $srm++) {
            $hex    = srm_color($srm, '');
            $rows[] = sprintf("SRM=%2d  →  %s", $srm, $hex);
        }
        $table = implode("\n", $rows) . "\n";

        $this->assertMatchesSnapshot($table, 'srm_color_srm_full_table');
    }

    // ── EBC conversion ─────────────────────────────────────────────────────

    /**
     * When method="ebc" the function multiplies the input by 1.97 before
     * looking up the colour.  EBC=10 → SRM≈19.7 → falls in the 19–20 bucket.
     * Snapshot a representative set of EBC → hex pairs.
     */
    public function testEbcConversionTable(): void
    {
        // Selected EBC values and the SRM bucket they should land in
        $ebcInputs = [5, 10, 15, 20, 25, 30, 40, 50, 60];
        $rows = [];
        foreach ($ebcInputs as $ebc) {
            $hex    = srm_color($ebc, 'ebc');
            $srmEquiv = round(1.97 * $ebc, 2);
            $rows[] = sprintf("EBC=%2d  (SRM≈%5.2f)  →  %s", $ebc, $srmEquiv, $hex);
        }
        $table = implode("\n", $rows) . "\n";

        $this->assertMatchesSnapshot($table, 'srm_color_ebc_conversion_table');
    }

    // ── Edge / boundary cases ──────────────────────────────────────────────

    /**
     * Boundary values: below-range (0), the last named bucket (SRM=30),
     * and the catch-all above-31 (SRM=32).
     */
    public function testBoundaryValues(): void
    {
        // SRM=0 — below 1, should hit the else → white
        $this->assertSame('#ffffff', srm_color(0, ''),
            'SRM=0 (below range) should return white');

        // SRM=31 — falls in the ">=30 && <31" bucket
        $color31 = srm_color(31, '');
        $this->assertMatchesSnapshot($color31, 'srm_color_srm_31');

        // SRM=31.5 — still in the ">31" catch-all bucket
        $color31_5 = srm_color(31.5, '');
        $this->assertSame($color31_5, srm_color(32, ''),
            'SRM=31.5 and SRM=32 should both fall in the >31 bucket');

        // SRM=32 — exceeds the last explicit bracket, hits "> 31"
        $this->assertSame('#000000', srm_color(32, ''),
            'SRM=32 (>31) should return black');
    }

    // ── Method is irrelevant for SRM values ───────────────────────────────

    /**
     * Any non-"ebc" method value is ignored (the function only branches on
     * method=="ebc").  Confirm that '' and any other string give the same
     * result for an SRM input.
     */
    public function testNonEbcMethodIsIgnored(): void
    {
        $srm = 10;
        $this->assertSame(
            srm_color($srm, ''),
            srm_color($srm, 'srm'),
            'Non-"ebc" method strings should all behave identically to ""'
        );
        $this->assertSame(
            srm_color($srm, ''),
            srm_color($srm, 'anything'),
            'Non-"ebc" method strings should all behave identically to ""'
        );
    }
}
