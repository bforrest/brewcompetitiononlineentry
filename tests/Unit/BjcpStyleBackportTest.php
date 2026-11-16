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

    // ── style_set_version_sql(): composite style sets ───────
    public function test_style_set_version_sql_simple_set(): void
    {
        $this->assertSame("brewStyleVersion='BJCP2021'", style_set_version_sql('BJCP2021'));
    }

    public function test_style_set_version_sql_escapes_quotes(): void
    {
        $this->assertSame("brewStyleVersion='a\\'b'", style_set_version_sql("a'b"));
    }

    public function test_style_set_version_sql_bjcp2025_takes_cider_from_2025_rest_from_2021(): void
    {
        $sql = style_set_version_sql('BJCP2025');
        $this->assertStringContainsString("(brewStyleVersion='BJCP2025' AND brewStyleType='2')", $sql);
        $this->assertStringContainsString("(brewStyleVersion='BJCP2021' AND brewStyleType NOT IN ('2'))", $sql);
        $this->assertStringNotContainsString('BJCP2026', $sql);
    }

    public function test_style_set_version_sql_bjcp2026_takes_mead_from_2026_cider_from_2025_beer_from_2021(): void
    {
        $sql = style_set_version_sql('BJCP2026');
        $this->assertStringContainsString("(brewStyleVersion='BJCP2026' AND brewStyleType='3')", $sql);
        $this->assertStringContainsString("(brewStyleVersion='BJCP2025' AND brewStyleType='2')", $sql);
        $this->assertStringContainsString("(brewStyleVersion='BJCP2021' AND brewStyleType NOT IN ('2','3'))", $sql);
    }

    public function test_style_set_version_sql_composite_is_parenthesised_for_safe_AND_OR_composition(): void
    {
        foreach (['BJCP2025', 'BJCP2026'] as $set) {
            $sql = style_set_version_sql($set);
            $this->assertSame('(', $sql[0]);
            $this->assertSame(')', substr($sql, -1));
        }
    }

    public function test_style_set_version_sql_alias_prefixes_every_column(): void
    {
        $this->assertSame("b.brewStyleVersion='BJCP2021'", style_set_version_sql('BJCP2021', 'b.'));
        $sql = style_set_version_sql('BJCP2026', 'b.');
        $this->assertSame(6, substr_count($sql, 'b.brewStyle'));
        $this->assertSame(0, preg_match("/[( ]brewStyle(Version|Type)/", $sql));
    }

    // ── style_set_version_clause(): bound-parameter form ────
    public function test_style_set_version_clause_plain_set_binds_the_value(): void
    {
        $c = style_set_version_clause("x' OR '1'='1");
        $this->assertSame('brewStyleVersion=?', $c['sql']);
        $this->assertSame(["x' OR '1'='1"], $c['params']);
    }

    public function test_style_set_version_clause_composite_has_no_params_and_no_user_data(): void
    {
        foreach (['BJCP2025', 'BJCP2026'] as $set) {
            $c = style_set_version_clause($set);
            $this->assertSame([], $c['params']);
            $this->assertStringNotContainsString('?', $c['sql']);
            $this->assertSame(style_set_version_sql($set), $c['sql']);
        }
    }

    public function test_style_set_version_clause_alias(): void
    {
        $this->assertSame('b.brewStyleVersion=?', style_set_version_clause('BJCP2021', 'b.')['sql']);
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

    public function test_2025_converter_maps_style_ids_with_the_variable_it_assigns(): void
    {
        // Upstream bug: the loop assigned $mapped_style_to_2021 but stored
        // $mapped_style_to_2025 (undefined), so every judge like/dislike and
        // table style id mapped to null.
        $src = file_get_contents(INCLUDES . 'convert/convert_bjcp_2025.inc.php');
        $this->assertSame(0, preg_match('/\$mapped_style_to_2021/', $src), 'stale 2021-named variable in the 2025 converter');
        $this->assertSame(1, preg_match('/\$mapped_style_to_2025\s*=\s*bjcp_map_2021_2025\(/', $src), 'the 2025 converter must assign what it later reads');
    }

    public function test_prefs_jump_from_2021_straight_to_2026_also_converts_cider(): void
    {
        // BJCP2026 takes cider from BJCP2025 rows. An admin going 2021 -> 2026 in one save
        // must therefore run the 2025 (cider) conversion before the 2026 (mead) one.
        $src = file_get_contents(PROCESS . 'process_prefs.inc.php');
        $start = strpos($src, 'if ($prefsStyleSet == "BJCP2026") {');
        $this->assertNotFalse($start);
        $branch = substr($src, $start, 1200);
        $p25 = strpos($branch, "convert/convert_bjcp_2025.inc.php");
        $p26 = strpos($branch, "convert/convert_bjcp_2026.inc.php");
        $this->assertNotFalse($p25, 'the BJCP2026 branch never runs the cider conversion');
        $this->assertLessThan($p26, $p25, 'cider conversion must run before the mead conversion');
        $this->assertMatchesRegularExpression('/\$_SESSION\[\'prefsStyleSet\'\]\s*==\s*"BJCP2021"\)\s*\{?\s*include \(INCLUDES\.\'convert\/convert_bjcp_2025/', $branch);
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
