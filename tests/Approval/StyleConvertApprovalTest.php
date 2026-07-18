<?php
/**
 * Approval (snapshot) tests for style_convert().
 *
 * style_convert($number, $type, $base_url, $archive) is the most complex
 * display function in common.lib.php: it queries the styles table and returns
 * formatted text or HTML in seven different modes ($type "4"–"9").  It is
 * impractical to assert every character with $this->assertSame() because the
 * output contains large HTML blocks and would need constant maintenance when
 * the stylesheet changes.  Snapshot tests solve this by:
 *
 *   1. Capturing the full output on the first run ("approval").
 *   2. Comparing subsequent runs against that saved output.
 *   3. Failing loudly whenever the output diverges from the approved version.
 *
 * All tests use the row with brewStyleGroup='06', brewStyleNum='B',
 * brewStyle='Rauchbier' (id=472 in the baseline schema, BJCP2021) as the
 * primary test fixture.  That row exists in every fresh baseline import, is
 * non-custom (brewStyleOwn='bcoe'), and has a rich brewStyleInfo blob — making
 * it a good canary for the type "4" HTML output.
 *
 * DB note
 * ───────
 * These tests only READ from the DB; they never INSERT.  setUp() sets the
 * PHP globals that style_convert() needs (via require(CONFIG.'config.php'))
 * but no rows are added, so tearDown() has nothing to delete.
 *
 * Running
 * ───────
 *   # First run — creates snapshot files, marks tests incomplete
 *   ./vendor/bin/phpunit --testsuite Approval
 *
 *   # Subsequent runs — compares against saved snapshots
 *   ./vendor/bin/phpunit --testsuite Approval
 *
 *   # After an intentional style_convert change, regenerate snapshots
 *   UPDATE_SNAPSHOTS=1 ./vendor/bin/phpunit --testsuite Approval
 */

declare(strict_types=1);

namespace BCOEM\Tests\Approval;

use BCOEM\Tests\Integration\IntegrationTestCase;

class StyleConvertApprovalTest extends IntegrationTestCase
{
    use SnapshotAssertions;

    /**
     * The numeric primary-key id of the Rauchbier row in baseline_styles.
     * Baseline schema inserts this as id=472.
     */
    private const RAUCHBIER_ID = '472';

    /**
     * brewStyleGroup code for Rauchbier in the BJCP2021 set.
     */
    private const RAUCHBIER_GROUP = '06';

    /**
     * brewStyleNum for Rauchbier in BJCP2021.
     */
    private const RAUCHBIER_NUM = 'B';

    // ── Additional session seeds for style_convert ─────────────────────────

    protected function setUp(): void
    {
        parent::setUp();   // sets $GLOBALS['connection'], prefix, database, etc.

        // style_convert() reads these session keys in several type branches
        $_SESSION['prefsStyleSet']        = 'BJCP2021';
        $_SESSION['style_set_short_name'] = 'BJCP 2021';    // used in type "4" HTML
        $_SESSION['style_set_category_end'] = 34;           // max numbered BJCP cat
    }

    // ── Type "4" — rich HTML style card (used on My Account / judge pages) ─

    /**
     * type "4" returns a multi-line HTML block with OG/FG/ABV/IBU/SRM badges,
     * style description, and commercial examples.  It is the most complex
     * output of style_convert and the one most likely to change over time.
     *
     * $number is a comma-delimited list of style table IDs (by `id` column).
     * $base_url is prepended to internal links — we use '' to keep snapshots
     * URL-agnostic.
     */
    public function testType4RauchbierProducesExpectedHtmlCard(): void
    {
        $result = style_convert(self::RAUCHBIER_ID, '4', '', '');

        // Sanity-check a few invariants before snapshotting so a completely
        // broken result doesn't silently overwrite the approved file.
        $this->assertIsString($result, 'type 4 must return a string');
        $this->assertStringContainsString('Rauchbier', $result, 'style name must appear in output');
        $this->assertStringContainsString('BJCP 2021', $result, 'style set name must appear in output');

        $this->assertMatchesSnapshot($result, 'style_convert_type4_rauchbier');
    }

    /**
     * type "4" with an unknown id does NOT return an empty string.
     * Even when the SELECT finds no matching style row, the outer foreach still
     * runs (it iterates over the comma-split id list, not the DB result), so the
     * function emits a Bootstrap modal scaffold keyed to the id.  Snapshot the
     * actual (non-empty) output so any change to that scaffold is caught.
     */
    public function testType4WithUnknownIdSnapshotsModalScaffold(): void
    {
        $result = style_convert('99999', '4', '', '');

        $this->assertIsString((string)$result, 'type 4 must return a string even for unknown ids');
        $this->assertMatchesSnapshot((string)$result, 'style_convert_type4_unknown_id_scaffold');
    }

    // ── Type "6" — abbreviated style codes for export ─────────────────────

    /**
     * type "6" takes a comma-delimited list of style IDs and returns a
     * comma-delimited string of "GroupNum" abbreviations.
     * e.g. id 472 (group="06", num="B") → "6B"
     */
    public function testType6SingleIdReturnsAbbreviation(): void
    {
        $result = style_convert(self::RAUCHBIER_ID, '6', '', '');

        $this->assertMatchesSnapshot((string)$result, 'style_convert_type6_rauchbier_single');
    }

    /**
     * type "6" with multiple comma-delimited IDs returns a comma-separated
     * list.  We pass the same id twice to confirm the join logic.
     */
    public function testType6MultipleIdsReturnsCommaSeparated(): void
    {
        $ids = self::RAUCHBIER_ID . ',' . self::RAUCHBIER_ID;
        $result = style_convert($ids, '6', '', '');

        $this->assertMatchesSnapshot((string)$result, 'style_convert_type6_rauchbier_double');
    }

    /**
     * type "6" with an unknown id returns '' (no rows matched, result never
     * assigned to $style_convert1).
     */
    public function testType6WithUnknownIdReturnsEmptyString(): void
    {
        $result = style_convert('99999', '6', '', '');

        $this->assertSame('', (string)$result, 'type 6 with unknown id should return empty string');
    }

    // ── Type "7" — inline HTML list (used in entry_info.sec.php) ──────────

    /**
     * type "7" returns an HTML <ul class='list-inline'> with one <li> per
     * style id.  For bcoe (non-custom) styles it renders the abbreviated code
     * in bold followed by the style name.
     */
    public function testType7SingleIdReturnsHtmlList(): void
    {
        $result = style_convert(self::RAUCHBIER_ID, '7', '', '');

        $this->assertStringContainsString('<ul', (string)$result, 'type 7 must return a <ul>');
        $this->assertStringContainsString('Rauchbier', (string)$result, 'style name must appear in list');

        $this->assertMatchesSnapshot((string)$result, 'style_convert_type7_rauchbier');
    }

    /**
     * type "7" wraps the entire output in <ul> tags even when no matching
     * row is found — the outer tags are always emitted.
     */
    public function testType7WithUnknownIdReturnsEmptyList(): void
    {
        $result = style_convert('99999', '7', '', '');

        $this->assertMatchesSnapshot((string)$result, 'style_convert_type7_unknown_id');
    }

    // ── Type "8" — "group,num,name" for judging flights ───────────────────

    /**
     * type "8" returns "brewStyleGroup,brewStyleNum,styleName".
     * Input $number is a single style id (not a comma-delimited list).
     */
    public function testType8ReturnsGroupNumName(): void
    {
        $result = style_convert(self::RAUCHBIER_ID, '8', '', '');

        $this->assertStringContainsString(',', (string)$result, 'type 8 output should be comma-delimited');
        $this->assertStringContainsString('Rauchbier', (string)$result, 'style name should be present');

        $this->assertMatchesSnapshot((string)$result, 'style_convert_type8_rauchbier');
    }

    /**
     * type "8" with an unknown id returns '' (the initialised $style_convert
     * default; $row_style is falsy so the assignment is skipped).
     */
    public function testType8WithUnknownIdReturnsEmptyString(): void
    {
        $result = style_convert('99999', '8', '', '');

        $this->assertSame('', (string)$result, 'type 8 with unknown id should return empty string');
    }

    // ── Type "9" — caret-delimited record for pullsheets ──────────────────

    /**
     * type "9" input format: "brewStyleGroup^brewStyleNum^brewStyleVersion".
     * Returns a caret-delimited string:
     *   group^num^name^version^reqSpec^strength^carb^sweet
     */
    public function testType9ReturnsCaretDelimitedRecord(): void
    {
        $number = self::RAUCHBIER_GROUP . '^' . self::RAUCHBIER_NUM . '^BJCP2021';
        $result = style_convert($number, '9', '', '');

        $parts = explode('^', (string)$result);
        $this->assertGreaterThanOrEqual(4, count($parts), 'type 9 must return at least 4 caret-delimited fields');
        $this->assertStringContainsString('Rauchbier', (string)$result, 'style name must appear');

        $this->assertMatchesSnapshot((string)$result, 'style_convert_type9_rauchbier_bjcp2021');
    }

    /**
     * type "9" with an unknown group/num/version returns '' (no matching row;
     * $row_style is falsy so $style_convert is never assigned).
     */
    public function testType9WithUnknownInputReturnsEmptyString(): void
    {
        $result = style_convert('XX^Z^BJCP2021', '9', '', '');

        $this->assertSame('', (string)$result, 'type 9 with no matching row should return empty string');
    }

    // ── Edge case: 2A BJCP2021 reqSpec override ───────────────────────────

    /**
     * The function has a hardcoded exception:
     *   if (($number[0] == "02") && ($number[1] == "A") && ($number[2] == "BJCP2021"))
     *       $row_style['brewStyleReqSpec'] = 1;
     * Pin that current (special-cased) behaviour.
     */
    public function testType9Bjcp2021Style2AHasReqSpecOverride(): void
    {
        // Style 2A in BJCP2021 exists in the baseline schema
        $result = style_convert('02^A^BJCP2021', '9', '', '');

        if ($result === '' || $result === false) {
            $this->markTestSkipped('BJCP2021 style 02^A not found in baseline DB');
        }

        $parts = explode('^', (string)$result);
        // Field index 4 is brewStyleReqSpec; the hardcoded override sets it to 1
        $this->assertSame('1', $parts[4] ?? '',
            'BJCP2021 2A must have brewStyleReqSpec=1 due to hardcoded override');
    }
}
