<?php
/**
 * Integration tests for display_place().
 *
 * display_place($place, $method) is classified as an integration test because
 * the function calls require(CONFIG.'config.php') at the very top, before any
 * method check — so a DB connection must exist even for the pure-format methods.
 *
 * None of the methods actually run SQL queries, so the tests are not seeding
 * any data; they only verify the output formatting once the connection guard
 * in config.php allows the require() to proceed without dying.
 *
 * Discovered bug documented here: method "4" has two `case "4":` entries.
 * The second (forest-green) overrides the first (purple), so place "4" with
 * method "4" renders forest-green rather than purple. The test pins this
 * current (buggy) behavior.
 */

declare(strict_types=1);

namespace BCOEM\Tests\Integration;

class DisplayPlaceTest extends IntegrationTestCase
{
    // ── Method "0" — plain ordinal ─────────────────────────────────────────────

    public function testMethod0PlacesReturnOrdinals(): void
    {
        $cases = [
            '1'  => '1st',
            '2'  => '2nd',
            '3'  => '3rd',
            '4'  => '4th',
            '5'  => '5th',
            '11' => '11th',
            '21' => '21st',
        ];
        foreach ($cases as $place => $expected) {
            $this->assertSame(
                $expected,
                display_place((string)$place, '0'),
                "Method 0, place '{$place}'"
            );
        }
    }

    // ── Method "1" — abbreviated competition places ────────────────────────────

    public function testMethod1PlacesOneToFourReturnOrdinals(): void
    {
        $this->assertSame('1st', display_place('1', '1'));
        $this->assertSame('2nd', display_place('2', '1'));
        $this->assertSame('3rd', display_place('3', '1'));
        $this->assertSame('4th', display_place('4', '1'));
    }

    public function testMethod1PlaceFiveReturnsHM(): void
    {
        $this->assertSame('HM', display_place('5', '1'));
    }

    public function testMethod1PlaceHMReturnsHM(): void
    {
        $this->assertSame('HM', display_place('HM', '1'));
    }

    public function testMethod1UnrecognisedPlaceReturnsNA(): void
    {
        $this->assertSame('N/A', display_place('99', '1'));
        $this->assertSame('N/A', display_place('X',  '1'));
    }

    // ── Method "2" — places with trophy icon, N/A default ─────────────────────

    public function testMethod2PlaceOneHasGoldTrophy(): void
    {
        $result = display_place('1', '2');
        $this->assertStringContainsString('text-gold',  $result);
        $this->assertStringContainsString('fa-trophy',  $result);
        $this->assertStringContainsString('1st',        $result);
    }

    public function testMethod2PlaceTwoHasSilverTrophy(): void
    {
        $result = display_place('2', '2');
        $this->assertStringContainsString('text-silver', $result);
        $this->assertStringContainsString('2nd',         $result);
    }

    public function testMethod2PlaceThreeHasBronzeTrophy(): void
    {
        $result = display_place('3', '2');
        $this->assertStringContainsString('text-bronze', $result);
        $this->assertStringContainsString('3rd',         $result);
    }

    public function testMethod2PlaceFourHasPurpleTrophy(): void
    {
        $result = display_place('4', '2');
        $this->assertStringContainsString('text-purple', $result);
        $this->assertStringContainsString('4th',         $result);
    }

    public function testMethod2PlaceFiveHasGreenHMTrophy(): void
    {
        $result = display_place('5', '2');
        $this->assertStringContainsString('text-forest-green', $result);
        $this->assertStringContainsString('HM',                $result);
    }

    public function testMethod2PlaceHMHasGreenHMTrophy(): void
    {
        $result = display_place('HM', '2');
        $this->assertStringContainsString('text-forest-green', $result);
        $this->assertStringContainsString('HM',                $result);
    }

    public function testMethod2UnrecognisedPlaceReturnsNA(): void
    {
        $this->assertSame('N/A', display_place('99', '2'));
    }

    // ── Method "3" — like method 2 but default shows grey trophy ─────────────

    public function testMethod3UnrecognisedPlaceHasGreyTrophy(): void
    {
        $result = display_place('99', '3');
        $this->assertStringContainsString('text-grey',  $result);
        $this->assertStringContainsString('fa-trophy',  $result);
        $this->assertStringContainsString('99th',       $result);
    }

    public function testMethod3PlaceOneHasGoldTrophy(): void
    {
        $result = display_place('1', '3');
        $this->assertStringContainsString('text-gold', $result);
        $this->assertStringContainsString('1st',       $result);
    }

    // ── Method "4" — BUG: duplicate case "4" in the source ───────────────────

    /**
     * @see characterization-test-findings.md — OBS / discovered bug.
     *
     * The source has two consecutive case blocks for place "4":
     *   case "4": $place = "..text-purple.."          ← first match
     *   case "4": $place = "..text-forest-green.."    ← dead code (never reached)
     *
     * PHP's switch statement evaluates cases top-to-bottom and stops at the
     * FIRST match (because of the trailing `break`).  The second case "4" with
     * forest-green is therefore unreachable dead code.  Place "4" renders
     * text-purple (the first case), not text-forest-green.
     *
     * The bug is the duplicate case itself; the visible symptom is that the
     * forest-green branch can never be triggered for place "4".
     */
    public function testMethod4PlaceFourRendersPurpleDueToDuplicateCase(): void
    {
        $result = display_place('4', '4');
        // Current (buggy) behavior — purple wins because PHP switch hits the
        // first matching case; the second case "4" (forest-green) is dead code.
        $this->assertStringContainsString('text-purple', $result,
            'BUG: duplicate case "4" — first match (purple) wins; forest-green branch is dead code');
        $this->assertStringNotContainsString('text-forest-green', $result);
    }

    public function testMethod4PlaceOneHasGoldTrophy(): void
    {
        $result = display_place('1', '4');
        $this->assertStringContainsString('text-gold', $result);
        $this->assertStringContainsString('1st',       $result);
    }

    public function testMethod4UnrecognisedPlaceHasGreyTrophy(): void
    {
        $result = display_place('99', '4');
        $this->assertStringContainsString('text-grey', $result);
        $this->assertStringContainsString('99th',      $result);
    }
}
