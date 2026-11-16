<?php
/**
 * Tests for the BJCP 2025 (cider) and BJCP 2026 (mead) style backport:
 * old-code -> new-code mapping functions, seed data files, and the
 * converter scripts. Seed/converter files need a DB to execute, so they are
 * checked structurally (lint + parsed data array), not run.
 */

use PHPUnit\Framework\TestCase;

class BjcpStyleBackportTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once LIB . 'convert.lib.php';
    }

    /** Extract the $data array literal from a seed file without executing the DB code. */
    private function seedRows(string $file): array
    {
        $src = file_get_contents(UPDATE . $file);
        $this->assertNotFalse($src);
        $start = strpos($src, '$data = array(');
        $end = strpos($src, "\n);", $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        eval(substr($src, $start, $end - $start + 3));
        /** @var array $data */
        return $data;
    }

    // ── bjcp_map_2021_2025 (cider) ──────────────────────────
    public function test_2025_map_method1_returns_new_code(): void
    {
        $this->assertSame('C4A', bjcp_map_2021_2025('C1D', 1, 'bcoem_', 5));
        $this->assertSame('C3A', bjcp_map_2021_2025('C2B', 1, 'bcoem_', 5));
    }

    public function test_2025_map_method2_returns_hyphenated_code(): void
    {
        $this->assertSame('C2-B', bjcp_map_2021_2025('C2C', 2, 'bcoem_', 5));
    }

    public function test_2025_map_method0_returns_update_sql(): void
    {
        $this->assertSame(
            "UPDATE bcoem_brewing SET brewCategory='C4', brewCategorySort='C4', brewSubCategory='B', brewStyle='Heirloom Perry' WHERE id='7'",
            bjcp_map_2021_2025('C1E', 0, 'bcoem_', 7)
        );
    }

    public function test_2025_map_unmapped_style_falls_through_for_method1(): void
    {
        $this->assertSame('01A', bjcp_map_2021_2025('01A', 1, 'bcoem_', 1));
        $this->assertSame('', bjcp_map_2021_2025('01A', 0, 'bcoem_', 1));
    }

    public function test_2025_map_casts_id_to_int(): void
    {
        $sql = bjcp_map_2021_2025('C1A', 0, 'bcoem_', "1' OR '1'='1");
        $this->assertStringEndsWith("WHERE id='1'", $sql);
    }

    // ── bjcp_map_2021_2026 (mead) ───────────────────────────
    /** @dataProvider meadMap */
    public function test_2026_map_method1(string $old, string $new): void
    {
        $this->assertSame($new, bjcp_map_2021_2026($old, 1, 'bcoem_', 1));
    }

    public static function meadMap(): array
    {
        return [
            'M1A unchanged' => ['M1A', 'M1A'],
            'M2E renamed same slot' => ['M2E', 'M2E'],
            'M3A moves to M3C' => ['M3A', 'M3C'],
            'M3B becomes Metheglin M3A' => ['M3B', 'M3A'],
            'M4A unchanged' => ['M4A', 'M4A'],
            'M4B retires to M4F' => ['M4B', 'M4F'],
            'M4C retires to M4F' => ['M4C', 'M4F'],
        ];
    }

    public function test_2026_map_method0_returns_update_sql(): void
    {
        $this->assertSame(
            "UPDATE bcoem_brewing SET brewCategory='M3', brewCategorySort='M3', brewSubCategory='A', brewStyle='Metheglin' WHERE id='9'",
            bjcp_map_2021_2026('M3B', 0, 'bcoem_', 9)
        );
    }

    public function test_2026_map_beer_falls_through(): void
    {
        $this->assertSame('21A', bjcp_map_2021_2026('21A', 1, 'bcoem_', 1));
    }

    public function test_2026_map_casts_id_to_int(): void
    {
        $this->assertStringEndsWith("WHERE id='3'", bjcp_map_2021_2026('M1A', 0, 'bcoem_', "3; DROP TABLE x"));
    }

    // ── seed data files ─────────────────────────────────────
    public function test_2025_seed_rows_are_all_bjcp2025_cider(): void
    {
        $rows = $this->seedRows('styles_bjcp_2025_update.php');
        $this->assertCount(16, $rows);
        foreach ($rows as $r) {
            $this->assertSame('BJCP2025', $r['brewStyleVersion']);
            $this->assertSame('2', $r['brewStyleType']);
        }
    }

    public function test_2026_seed_rows_are_all_bjcp2026_mead(): void
    {
        $rows = $this->seedRows('styles_bjcp_2026_update.php');
        $this->assertCount(17, $rows);
        foreach ($rows as $r) {
            $this->assertSame('BJCP2026', $r['brewStyleVersion']);
            $this->assertSame('3', $r['brewStyleType']);
        }
        $codes = array_map(fn($r) => $r['brewStyleGroup'] . $r['brewStyleNum'], $rows);
        $this->assertContains('M2E', $codes);
        $this->assertContains('M4F', $codes);
    }

    /** @dataProvider seedFiles */
    public function test_seed_files_use_27_update_output_variable(string $file): void
    {
        $src = file_get_contents(UPDATE . $file);
        $this->assertStringContainsString('$output_run_update', $src);
        $this->assertStringNotContainsString('$v3000_update', $src);
        $this->assertStringNotContainsString('$v3200_update', $src);
    }

    /** @dataProvider seedFiles */
    public function test_seed_files_match_on_version_when_skipping_duplicates(string $file): void
    {
        $this->assertStringContainsString('brewStyleVersion', substr(file_get_contents(UPDATE . $file), strpos(file_get_contents(UPDATE . $file), "\n);")));
    }

    public static function seedFiles(): array
    {
        return [['styles_bjcp_2025_update.php'], ['styles_bjcp_2026_update.php']];
    }

    // ── converter scripts + guard helper ────────────────────
    /** @dataProvider converterFiles */
    public function test_converter_exists_and_lints(string $file): void
    {
        $path = INCLUDES . 'convert/' . $file;
        $this->assertFileExists($path);
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $rc);
        $this->assertSame(0, $rc, implode("\n", $out));
    }

    public static function converterFiles(): array
    {
        return [['convert_bjcp_2025.inc.php'], ['convert_bjcp_2026.inc.php']];
    }

    public function test_check_new_style_version_helper_exists(): void
    {
        // update.lib.php can't be loaded under the test bootstrap (it redeclares
        // check_setup()), so check the source.
        $src = file_get_contents(LIB . 'update.lib.php');
        $this->assertMatchesRegularExpression('/function check_new_style_version\(\$style1, \$style2, \$style3, \$version/', $src);
        $this->assertStringContainsString("brewStyleVersion='%s'", $src);
    }
}
