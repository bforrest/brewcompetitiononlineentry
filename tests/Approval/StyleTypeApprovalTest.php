<?php
/**
 * Approval (snapshot) tests for style_type().
 *
 * style_type($type, $method, $source) maps between type names and numeric
 * codes used internally by BCOEM.  Two of its four branches are pure
 * (no database access):
 *
 *   method "1"            → names → codes  (Beer/Ale/Lager → "1", Cider → "2", Mead → "3")
 *   method "2", "bcoe"    → codes → names  ("1" → "Beer", "2" → "Cider", "3" → "Mead")
 *
 * The other two branches (method "2" + source "custom", and method "3")
 * query the style_types table and are therefore skipped here (covered by
 * the Integration suite if/when StyleTypeIntegrationTest is written).
 *
 * Snapshot strategy
 * ─────────────────
 * Because method "1" and method "2"+"bcoe" each have a small, fixed set of
 * inputs, a single snapshot per variant captures all of them at once as a
 * human-readable table.  If the mapping ever changes (e.g., a new type is
 * added), the snapshot diff will make the change obvious.
 *
 * These tests do not need a database connection.
 */

declare(strict_types=1);

namespace BCOEM\Tests\Approval;

use PHPUnit\Framework\TestCase;

class StyleTypeApprovalTest extends TestCase
{
    use SnapshotAssertions;

    // ── Method "1" — name → numeric code ──────────────────────────────────

    /**
     * method "1" converts human-readable type names to internal numeric codes.
     * Snapshot the full mapping table so any added/removed case is caught.
     */
    public function testMethod1NameToCodeMappingTable(): void
    {
        $inputs = [
            'Mead'   => '3',
            'Cider'  => '2',
            'Mixed'  => '1',
            'Ale'    => '1',
            'Lager'  => '1',
            'Beer'   => 'Beer',  // falls through to default: $type = $type (unchanged)
            ''       => '',
            'Custom' => 'Custom',
        ];

        $rows = [];
        foreach ($inputs as $input => $expectedCode) {
            $actual = style_type($input, '1', '');
            $rows[] = sprintf("%-12s → %s", $input !== '' ? $input : '(empty)', $actual);
        }

        $table = implode("\n", $rows) . "\n";
        $this->assertMatchesSnapshot($table, 'style_type_method1_all_inputs');
    }

    /**
     * Individual spot-checks for method "1" to provide clear failure messages
     * if a specific mapping breaks.
     */
    public function testMethod1Mead(): void
    {
        $this->assertSame('3', style_type('Mead', '1', ''), 'Mead should map to code 3');
    }

    public function testMethod1Cider(): void
    {
        $this->assertSame('2', style_type('Cider', '1', ''), 'Cider should map to code 2');
    }

    public function testMethod1BeerAleAndLager(): void
    {
        // 'Beer' is not an explicit case in the switch, so default: applies:
        //   default: $type = $type  → returns the input unchanged ('Beer', not '1').
        $this->assertSame('Beer',  style_type('Beer',  '1', ''), 'Beer: default branch returns unchanged input');
        $this->assertSame('1', style_type('Ale',   '1', ''), 'Ale should map to code 1');
        $this->assertSame('1', style_type('Lager', '1', ''), 'Lager should map to code 1');
        $this->assertSame('1', style_type('Mixed', '1', ''), 'Mixed should map to code 1');
    }

    public function testMethod1UnrecognisedInputIsPassedThrough(): void
    {
        // The default: branch does $type = $type, so the value is returned unchanged.
        $this->assertSame('Sour', style_type('Sour', '1', ''),
            'Unrecognised type should pass through unchanged (default: $type = $type)');
    }

    // ── Method "2" + source "bcoe" — numeric code → display name ──────────

    /**
     * method "2" with source "bcoe" converts numeric codes to type names.
     * Snapshot the full mapping table.
     */
    public function testMethod2BcoeCodeToNameMappingTable(): void
    {
        $inputs = ['1', '2', '3', 'Ale', 'Lager', 'Mixed', 'Beer', '4', 'Custom'];

        $rows = [];
        foreach ($inputs as $code) {
            $actual = style_type($code, '2', 'bcoe');
            $rows[] = sprintf("%-12s → %s", $code, $actual);
        }

        $table = implode("\n", $rows) . "\n";
        $this->assertMatchesSnapshot($table, 'style_type_method2_bcoe_all_inputs');
    }

    public function testMethod2BcoeCode1ReturnsBeer(): void
    {
        $this->assertSame('Beer', style_type('1', '2', 'bcoe'));
    }

    public function testMethod2BcoeCode2ReturnsCider(): void
    {
        $this->assertSame('Cider', style_type('2', '2', 'bcoe'));
    }

    public function testMethod2BcoeCode3ReturnsMead(): void
    {
        $this->assertSame('Mead', style_type('3', '2', 'bcoe'));
    }

    public function testMethod2BcoeAleAndLagerReturnBeer(): void
    {
        $this->assertSame('Beer', style_type('Ale',   '2', 'bcoe'));
        $this->assertSame('Beer', style_type('Lager', '2', 'bcoe'));
        $this->assertSame('Beer', style_type('Mixed', '2', 'bcoe'));
        $this->assertSame('Beer', style_type('Beer',  '2', 'bcoe'));
    }

    public function testMethod2BcoeUnrecognisedCodePassesThrough(): void
    {
        // Unrecognised codes fall to default: $type = $type
        $this->assertSame('4',      style_type('4',      '2', 'bcoe'));
        $this->assertSame('Custom', style_type('Custom', '2', 'bcoe'));
    }

    // ── Methods "2"+"custom" and "3" are DB-dependent — skipped here ───────

    public function testMethod2CustomAndMethod3RequireDbAndAreSkipped(): void
    {
        // style_type() methods "2"+"custom" and "3" call require(CONFIG.'config.php')
        // and run a SELECT against the style_types table.  They are skipped in
        // this pure-function suite; add them to an IntegrationTestCase subclass
        // in tests/Integration/StyleTypeTest.php when Tier-2 coverage is extended.
        $this->markTestSkipped(
            'style_type() methods "2"+"custom" and "3" require a live DB connection — '
            . 'add to tests/Integration/StyleTypeTest.php'
        );
    }
}
