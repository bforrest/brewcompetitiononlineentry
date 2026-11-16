<?php
/**
 * Manual integration test: runs the backported BJCP 2025/2026 seed + converter scripts against the
 * throwaway `bcoem_scratch` database and ASSERTS the results. Never touches `bcoem`.
 *
 *   sh tests/manual/reset-bjcp-scratch-db.sh
 *   docker cp tests/manual/bjcp-converters-scratch.php bluebonnet-testing-web-1:/tmp/bjcp-converters-scratch.php
 *   docker exec bluebonnet-testing-web-1 php /tmp/bjcp-converters-scratch.php
 *
 * Exits non-zero if any assertion fails. Re-run the reset script before every run (the converters
 * are one-way).
 *
 * Fixtures (see reset-bjcp-scratch-db.sh): 9 cider/mead entries, judging table 116, judge uid 1's
 * likes/dislikes - including ids (9998/9999) that belong to no mapped style, which must survive.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
$root = '/var/www/html/';
define('ROOT', $root); define('LIB', $root.'lib/'); define('INCLUDES', $root.'includes/'); define('UPDATE', $root.'update/');
define('CONFIG', '/tmp/scratch_cfg/'); define('HOSTED', FALSE);

// Minimal config pointing at the scratch DB (the real site/config.php needs the whole app bootstrap).
@mkdir('/tmp/scratch_cfg');
file_put_contents('/tmp/scratch_cfg/config.php', "<?php\n\$hostname='db'; \$username='bcoem'; \$password='bcoem_password'; \$database='bcoem_scratch'; \$prefix=''; \$connection = new mysqli(\$hostname,\$username,\$password,\$database,3306); mysqli_query(\$connection,\"SET sql_mode=''\");\n");
if (!file_exists('/tmp/scratch_cfg/MysqliDb.php')) copy($root.'site/MysqliDb.php', '/tmp/scratch_cfg/MysqliDb.php');
require CONFIG.'config.php';
require CONFIG.'MysqliDb.php';
require_once LIB.'update.lib.php';
include LIB.'convert.lib.php';
// style_set_version_clause() lives in common.lib.php, which needs the full app bootstrap - pull just the function.
$common_src = file_get_contents(LIB.'common.lib.php');
preg_match('/function style_set_version_clause\\(.*?\\n}\\n/s', $common_src, $m);
eval($m[0]);

$db_conn = new MysqliDb($connection);
if (!function_exists('sterilize')) { function sterilize($v) { return $v; } }
$output = ""; $output_run_update = ""; $error_count = 0; $prefix = ''; $prefix_session = 'scratch';

$failures = 0;
function check($label, $actual, $expected) {
    global $failures;
    if ($actual === $expected) { echo "  PASS  $label\n"; return; }
    $failures++;
    echo "  FAIL  $label\n        expected: ".var_export($expected, true)."\n        actual:   ".var_export($actual, true)."\n";
}
function q($sql) { global $db_conn; return $db_conn->rawQuery($sql); }
function id_of($group, $num, $version) {
    global $db_conn;
    $r = $db_conn->rawQueryOne("SELECT id FROM styles WHERE brewStyleGroup=? AND brewStyleNum=? AND brewStyleVersion=? AND brewStyleOwn!='custom'", array($group, $num, $version));
    return $r ? (string) $r['id'] : null;
}
function entries() {
    $out = array();
    foreach (q("SELECT brewCategorySort c, brewSubCategory s, brewStyle n FROM brewing WHERE brewName LIKE 't %' ORDER BY id") as $r) $out[] = $r['c'].$r['s'].' '.$r['n'];
    return $out;
}
function table116() { $t = q("SELECT tableStyles s FROM judging_tables WHERE id=116"); return $t[0]['s']; }
function judge1() { $b = q("SELECT brewerJudgeLikes l, brewerJudgeDislikes d FROM brewer WHERE uid=1"); return array($b[0]['l'], $b[0]['d']); }
function active_count($version, $type) {
    $r = q("SELECT COUNT(*) c FROM styles WHERE brewStyleVersion='$version' AND brewStyleType='$type' AND brewStyleOwn!='custom' AND brewStyleActive='Y'");
    return (int) $r[0]['c'];
}
function other_sets_active() {
    $out = array();
    foreach (q("SELECT brewStyleVersion v, SUM(brewStyleActive='Y') a FROM styles WHERE brewStyleVersion NOT LIKE 'BJCP%' AND brewStyleOwn!='custom' GROUP BY 1 ORDER BY 1") as $r) $out[$r['v']] = (int) $r['a'];
    return $out;
}
function ids_sorted($csv) { $a = array_filter(explode(',', $csv), 'strlen'); sort($a); return implode(',', $a); }

// Baseline sanity - fail loudly if the scratch DB wasn't reset.
$pref = q("SELECT prefsStyleSet s FROM preferences");
if ($pref[0]['s'] != 'BJCP2021' || count(entries()) != 9) { echo "Scratch DB is not in its freshly-reset state. Run reset-bjcp-scratch-db.sh first.\n"; exit(2); }

$other_sets_before = other_sets_active();

echo "\n[1] Seeding (twice - the second pass must add nothing)\n";
for ($i = 1; $i <= 2; $i++) {
    $output_run_update = "";
    if (!check_new_style_version("C1","E","Spanish Cider","BJCP2025")) include UPDATE.'styles_bjcp_2025_update.php';
    if (!check_new_style_version("M2","E","Other Fruit Mead","BJCP2026")) include UPDATE.'styles_bjcp_2026_update.php';
    if ($i == 1) check("pass 1 reports both inserts", (strpos($output_run_update, 'Added 16 BJCP 2025') !== false) && (strpos($output_run_update, 'Added 17 BJCP 2026') !== false), true);
    else check("pass 2 inserts nothing", $output_run_update, "");
}
check("BJCP2025 has 16 cider rows", (int) q("SELECT COUNT(*) c FROM styles WHERE brewStyleVersion='BJCP2025' AND brewStyleOwn!='custom'")[0]['c'], 16);
check("BJCP2026 has 17 mead rows", (int) q("SELECT COUNT(*) c FROM styles WHERE brewStyleVersion='BJCP2026' AND brewStyleOwn!='custom'")[0]['c'], 17);

$id2021 = function ($g, $n) { return id_of($g, $n, 'BJCP2021'); };
$id2025 = function ($g, $n) { return id_of($g, $n, 'BJCP2025'); };
$id2026 = function ($g, $n) { return id_of($g, $n, 'BJCP2026'); };

echo "\n[2] Convert BJCP2021 -> BJCP2025 (cider)\n";
include INCLUDES.'convert/convert_bjcp_2025.inc.php';
$db_conn->where('id', '1'); $db_conn->update('preferences', array('prefsStyleSet' => 'BJCP2025'));

check("entries: cider re-mapped, mead untouched", entries(), array(
    'C1C English Cider',            // old C1B -> C1C
    'C4A Common Perry',             // old C1D "New World Perry" -> C4A
    'C3A Fruit Cider',              // old C2B "Cider with Other Fruit" -> C3A
    'M1A Dry Mead', 'M2E Melomel', 'M3A Fruit and Spice Mead', 'M3B Spice, Herb, or Vegetable Mead', 'M4B Historical Mead', 'M4C Experimental Mead'));
list($likes, $dislikes) = judge1();
check("judge likes point at the new 2025 rows; unmapped ids kept", ids_sorted($likes), ids_sorted('603,602,'.$id2025('C1','C').',9998'));
check("judge dislikes point at the new 2025 rows, no stray comma", ids_sorted($dislikes), ids_sorted('605,'.$id2025('C4','A')));
check("judge dislikes has no empty element", strpos($dislikes, ',,') === false && substr($dislikes, -1) !== ',', true);
check("table styles: cider re-mapped to 2025 ids; mead + unmapped ids kept", ids_sorted(table116()), ids_sorted('605,606,603,601,594,9999,'.$id2025('C1','C')));
check("all 16 BJCP2025 cider rows are active", active_count('BJCP2025', 2), 16);
check("old BJCP2021 cider rows are deactivated", active_count('BJCP2021', 2), 0);
check("BJCP2021 beer rows untouched (130 active)", active_count('BJCP2021', 1), 130);
check("BJCP2021 mead rows untouched (13 active)", active_count('BJCP2021', 3), 13);
check("other style sets (AABC, BA, NWCiderCup...) are untouched", other_sets_active(), $other_sets_before);
check("prefsStyleSet is BJCP2025", q("SELECT prefsStyleSet s FROM preferences")[0]['s'], 'BJCP2025');

echo "\n[3] Convert BJCP2025 -> BJCP2026 (mead)\n";
include INCLUDES.'convert/convert_bjcp_2026.inc.php';
$db_conn->where('id', '1'); $db_conn->update('preferences', array('prefsStyleSet' => 'BJCP2026'));

check("entries: mead re-mapped, cider stays 2025", entries(), array(
    'C1C English Cider', 'C4A Common Perry', 'C3A Fruit Cider',
    'M1A Dry Mead',
    'M2E Other Fruit Mead',         // renamed in place
    'M3C Fruit and Spice Mead',     // old M3A moved to M3C
    'M3A Metheglin',                // old M3B -> M3A
    'M4F Experimental Mead',        // old M4B Historical -> M4F
    'M4F Experimental Mead'));      // old M4C Experimental -> M4F
list($likes, $dislikes) = judge1();
check("judge likes: mead -> 2026 ids, cider id (2025) and unmapped id kept", ids_sorted($likes), ids_sorted($id2026('M3','A').','.$id2026('M3','C').','.$id2025('C1','C').',9998'));
check("judge dislikes: Historical Mead -> M4F, Perry (2025) kept", ids_sorted($dislikes), ids_sorted($id2026('M4','F').','.$id2025('C4','A')));
check("table styles: mead -> 2026 ids, cider id and unmapped id kept", ids_sorted(table116()), ids_sorted($id2026('M4','F').','.$id2026('M3','A').','.$id2026('M2','E').','.$id2026('M1','A').','.$id2025('C1','C').',9999'));
check("all 17 BJCP2026 mead rows are active", active_count('BJCP2026', 3), 17);
check("old BJCP2021 mead rows are deactivated", active_count('BJCP2021', 3), 0);
check("BJCP2025 cider rows still active (16)", active_count('BJCP2025', 2), 16);
check("BJCP2021 beer rows still active (130)", active_count('BJCP2021', 1), 130);
check("other style sets are still untouched", other_sets_active(), $other_sets_before);
check("prefsStyleSet is BJCP2026", q("SELECT prefsStyleSet s FROM preferences")[0]['s'], 'BJCP2026');

echo "\n[4] The composite BJCP2026 set resolves to exactly one row per style\n";
$clause = style_set_version_clause('BJCP2026');
$rows = q("SELECT brewStyleGroup g, brewStyleNum n, COUNT(*) c FROM styles WHERE brewStyleOwn!='custom' AND ".$clause['sql']." GROUP BY 1,2 HAVING c > 1");
check("no duplicate group/num under BJCP2026", count($rows), 0);
check("BJCP2026 set size = 130 beer + 16 cider + 17 mead", (int) q("SELECT COUNT(*) c FROM styles WHERE brewStyleOwn!='custom' AND ".$clause['sql'])[0]['c'], 163);

echo "\nconverter error_count=$error_count\n";
echo $failures == 0 ? "\nALL PASS\n" : "\n$failures FAILED\n";
exit($failures == 0 ? 0 : 1);
