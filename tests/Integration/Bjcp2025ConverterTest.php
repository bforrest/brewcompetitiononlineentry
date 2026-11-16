<?php
/**
 * Behavioural test for includes/convert/convert_bjcp_2025.inc.php (BJCP 2021 -> 2025 cider).
 *
 * Runs the REAL converter once against a throwaway database, `bcoem_phpunit_scratch`, built from
 * copies of the dev stack's tables. The dev stack's own `bcoem` database is only ever READ (to copy
 * table structure and BJCP2021/NWCiderCup style rows) - never written. The scratch DB is dropped
 * afterwards. Skips when the stack's MariaDB isn't reachable, so a plain `vendor/bin/phpunit` still
 * works without docker.
 *
 * Connection (defaults match docker-compose.yml): BCOEM_TEST_DB_HOST=127.0.0.1, BCOEM_TEST_DB_PORT
 * (or DB_PORT)=3307, BCOEM_TEST_DB_ROOT_PASSWORD=root_password, BCOEM_TEST_DB_SOURCE=bcoem.
 *
 * Regression coverage for the bugs found in upstream's converter: the style-id map was never filled,
 * judge likes/dislikes and table styles resolved to BJCP2021 ids and dropped unmapped ids, activation
 * left 3 of 16 cider styles on, and the failsafe switched on same-numbered styles in other sets.
 */

use PHPUnit\Framework\TestCase;

class Bjcp2025ConverterTest extends TestCase
{
    private const SCRATCH = 'bcoem_phpunit_scratch';

    /** @var mysqli|null root connection (creates/drops the scratch DB) */
    private static $root = null;
    private static string $skipReason = '';
    /** @var array<string,mixed> state captured after the converter ran */
    private static array $after = [];
    private static array $before = [];
    private static array $ids = [];

    public static function setUpBeforeClass(): void
    {
        require_once LIB . 'convert.lib.php';
        mysqli_report(MYSQLI_REPORT_OFF);

        $host = getenv('BCOEM_TEST_DB_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('BCOEM_TEST_DB_PORT') ?: (getenv('DB_PORT') ?: 3307));
        $pass = getenv('BCOEM_TEST_DB_ROOT_PASSWORD') ?: 'root_password';
        $source = getenv('BCOEM_TEST_DB_SOURCE') ?: 'bcoem';

        $root = @new mysqli($host, 'root', $pass, '', $port);
        if ($root->connect_errno) { self::$skipReason = 'dev stack DB not reachable: ' . $root->connect_error; return; }
        $has = $root->query("SELECT COUNT(*) c FROM `$source`.styles WHERE brewStyleVersion='BJCP2021'");
        if (!$has || (int) $has->fetch_assoc()['c'] < 100) { self::$skipReason = "`$source` has no BJCP2021 styles to copy"; return; }
        self::$root = $root;

        try {
            self::buildScratch($source);
            self::runConverter($host, $port, $pass);
        } catch (Throwable $e) {
            self::dropScratch();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::dropScratch();
    }

    protected function setUp(): void
    {
        if (self::$root === null) $this->markTestSkipped(self::$skipReason);
    }

    private static function dropScratch(): void
    {
        if (self::$root) self::$root->query('DROP DATABASE IF EXISTS `' . self::SCRATCH . '`');
    }

    private static function q(string $sql): mysqli_result|bool
    {
        $r = self::$root->query($sql);
        if ($r === false) throw new RuntimeException(self::$root->error . ' -- ' . $sql);
        return $r;
    }

    private static function rows(string $sql): array
    {
        return self::q($sql)->fetch_all(MYSQLI_ASSOC);
    }

    private static function buildScratch(string $source): void
    {
        $s = self::SCRATCH;
        self::q("DROP DATABASE IF EXISTS `$s`");
        self::q("CREATE DATABASE `$s` CHARACTER SET utf8mb4");
        self::q("SET SESSION sql_mode=''");
        // Structure copied from the stack; `brewing` is a view there, so copy its backing table.
        self::q("CREATE TABLE `$s`.styles LIKE `$source`.styles");
        self::q("CREATE TABLE `$s`.brewing LIKE `$source`.brewing_bbo_real");
        self::q("CREATE TABLE `$s`.brewer LIKE `$source`.brewer");
        self::q("CREATE TABLE `$s`.judging_tables LIKE `$source`.judging_tables");
        self::q("CREATE TABLE `$s`.preferences LIKE `$source`.preferences");
        self::q("INSERT INTO `$s`.preferences SELECT * FROM `$source`.preferences LIMIT 1");
        self::q("UPDATE `$s`.preferences SET id=1, prefsStyleSet='BJCP2021'");
        self::q("INSERT INTO `$s`.brewer SELECT * FROM `$source`.brewer LIMIT 1");
        self::q("UPDATE `$s`.brewer SET id=1, uid=1");
        // Real BJCP2021 rows, plus another style set whose group/num codes collide with the cider codes.
        self::q("INSERT INTO `$s`.styles SELECT * FROM `$source`.styles WHERE brewStyleVersion IN ('BJCP2021','NWCiderCup') AND brewStyleOwn!='custom'");
        self::q("UPDATE `$s`.styles SET brewStyleActive='Y' WHERE brewStyleVersion='BJCP2021'");
        self::q("UPDATE `$s`.styles SET brewStyleActive='N' WHERE brewStyleVersion='NWCiderCup'");

        // Seed the 2025 rows from the real seed file's $data array (same rows run_update.php inserts).
        $src = file_get_contents(UPDATE . 'styles_bjcp_2025_update.php');
        $start = strpos($src, '$data = array(');
        $end = strpos($src, "\n);", $start);
        eval(substr($src, $start, $end - $start + 3));
        $db = new mysqli(getenv('BCOEM_TEST_DB_HOST') ?: '127.0.0.1', 'root', getenv('BCOEM_TEST_DB_ROOT_PASSWORD') ?: 'root_password', $s, (int) (getenv('BCOEM_TEST_DB_PORT') ?: (getenv('DB_PORT') ?: 3307)));
        $db->query("SET SESSION sql_mode=''");
        foreach ($data as $row) {
            $cols = '`' . implode('`,`', array_keys($row)) . '`';
            $vals = "'" . implode("','", array_map([$db, 'real_escape_string'], array_values($row))) . "'";
            if (!$db->query("INSERT INTO styles ($cols) VALUES ($vals)")) throw new RuntimeException($db->error);
        }
        $db->close();

        $id21 = fn($g, $n) => (int) self::rows("SELECT id FROM `$s`.styles WHERE brewStyleGroup='$g' AND brewStyleNum='$n' AND brewStyleVersion='BJCP2021'")[0]['id'];
        $id25 = fn($g, $n) => (int) self::rows("SELECT id FROM `$s`.styles WHERE brewStyleGroup='$g' AND brewStyleNum='$n' AND brewStyleVersion='BJCP2025'")[0]['id'];
        self::$ids = [
            'old_C1B' => $id21('C1', 'B'), 'old_C1D' => $id21('C1', 'D'), 'old_M3B' => $id21('M3', 'B'),
            'new_C1C' => $id25('C1', 'C'), 'new_C4A' => $id25('C4', 'A'),
        ];

        foreach ([
            ['English Cider', 'C1', 'B', 2], ['New World Perry', 'C1', 'D', 2], ['Cider with Other Fruit', 'C2', 'B', 2],
            ['Dry Mead', 'M1', 'A', 3], ['Melomel', 'M2', 'E', 3],
        ] as [$name, $cat, $sub, $type]) {
            self::q("INSERT INTO `$s`.brewing (brewName,brewStyle,brewCategory,brewCategorySort,brewSubCategory,brewBrewerID,brewStyleType,brewPaid,brewReceived,brewConfirmed)
                     VALUES ('t $name','$name','$cat','$cat','$sub','1',$type,1,1,1)");
        }
        // 999999 / 888888 are ids of no mapped style (think: a custom style) and must survive.
        $ids = self::$ids;
        self::q("INSERT INTO `$s`.judging_tables (id, tableName, tableStyles) VALUES (116, 'T', '{$ids['old_M3B']},{$ids['old_C1B']},999999')");
        self::q("UPDATE `$s`.brewer SET brewerJudgeLikes='{$ids['old_M3B']},{$ids['old_C1B']},888888', brewerJudgeDislikes='{$ids['old_C1D']}'");

        self::$before = [
            'other_active' => self::rows("SELECT SUM(brewStyleActive='Y') a FROM `$s`.styles WHERE brewStyleVersion='NWCiderCup'")[0]['a'],
        ];
    }

    private static function runConverter(string $host, int $port, string $pass): void
    {
        require_once CONFIG . 'MysqliDb.php';
        $db_conn = new MysqliDb($host, 'root', $pass, self::SCRATCH, $port);
        $db_conn->rawQuery("SET SESSION sql_mode=''");

        // The converter expects these from the surrounding app.
        $prefix = '';
        $prefix_session = 'phpunit';
        $output = '';
        (function () use ($db_conn, $prefix, $prefix_session, &$output) {
            include INCLUDES . 'convert/convert_bjcp_2025.inc.php';
        })();

        $s = self::SCRATCH;
        $after = [];
        $after['entries'] = array_map(fn($r) => $r['c'] . $r['s'] . ' ' . $r['n'], self::rows("SELECT brewCategorySort c, brewSubCategory s, brewStyle n FROM `$s`.brewing ORDER BY id"));
        $after['likes'] = self::rows("SELECT brewerJudgeLikes l, brewerJudgeDislikes d FROM `$s`.brewer")[0];
        $after['table'] = self::rows("SELECT tableStyles t FROM `$s`.judging_tables WHERE id=116")[0]['t'];
        $after['active_2025'] = (int) self::rows("SELECT COUNT(*) c FROM `$s`.styles WHERE brewStyleVersion='BJCP2025' AND brewStyleActive='Y'")[0]['c'];
        $after['total_2025'] = (int) self::rows("SELECT COUNT(*) c FROM `$s`.styles WHERE brewStyleVersion='BJCP2025'")[0]['c'];
        $after['active_2021_cider'] = (int) self::rows("SELECT COUNT(*) c FROM `$s`.styles WHERE brewStyleVersion='BJCP2021' AND brewStyleType='2' AND brewStyleActive='Y'")[0]['c'];
        $after['active_2021_beer'] = (int) self::rows("SELECT COUNT(*) c FROM `$s`.styles WHERE brewStyleVersion='BJCP2021' AND brewStyleType='1' AND brewStyleActive='Y'")[0]['c'];
        $after['total_2021_beer'] = (int) self::rows("SELECT COUNT(*) c FROM `$s`.styles WHERE brewStyleVersion='BJCP2021' AND brewStyleType='1'")[0]['c'];
        $after['other_active'] = self::rows("SELECT SUM(brewStyleActive='Y') a FROM `$s`.styles WHERE brewStyleVersion='NWCiderCup'")[0]['a'];
        $after['prefs'] = self::rows("SELECT prefsStyleSet p FROM `$s`.preferences WHERE id=1")[0]['p'];
        self::$after = $after;
    }

    private static function sortedIds(string $csv): array
    {
        $a = array_values(array_filter(explode(',', $csv), 'strlen'));
        sort($a);
        return $a;
    }

    public function test_entries_are_remapped_to_2025_cider_codes_and_mead_is_untouched(): void
    {
        $this->assertSame([
            'C1C English Cider',        // old C1B
            'C4A Common Perry',         // old C1D "New World Perry"
            'C3A Fruit Cider',          // old C2B "Cider with Other Fruit"
            'M1A Dry Mead',
            'M2E Melomel',
        ], self::$after['entries']);
    }

    public function test_judge_likes_resolve_to_the_new_2025_row_and_keep_unmapped_ids(): void
    {
        $ids = self::$ids;
        $expected = [(string) $ids['old_M3B'], (string) $ids['new_C1C'], '888888'];
        sort($expected);
        $this->assertSame($expected, self::sortedIds(self::$after['likes']['l']));
    }

    public function test_judge_dislikes_resolve_to_the_new_2025_row_without_stray_commas(): void
    {
        $this->assertSame((string) self::$ids['new_C4A'], self::$after['likes']['d']);
    }

    public function test_table_styles_resolve_to_the_new_2025_row_and_keep_unmapped_ids(): void
    {
        $ids = self::$ids;
        $expected = [(string) $ids['old_M3B'], (string) $ids['new_C1C'], '999999'];
        sort($expected);
        $this->assertSame($expected, self::sortedIds(self::$after['table']));
    }

    public function test_every_2025_cider_style_ends_up_active(): void
    {
        $this->assertSame(16, self::$after['total_2025']);
        $this->assertSame(16, self::$after['active_2025'], 'upstream left only 3 of 16 active');
    }

    public function test_old_2021_cider_rows_are_deactivated_but_beer_is_untouched(): void
    {
        $this->assertSame(0, self::$after['active_2021_cider']);
        $this->assertSame(self::$after['total_2021_beer'], self::$after['active_2021_beer']);
    }

    public function test_the_failsafe_does_not_activate_same_numbered_styles_in_other_style_sets(): void
    {
        // NWCiderCup reuses codes such as C1-A, C2-B, C3-A and C4-A.
        $this->assertSame(self::$before['other_active'], self::$after['other_active']);
        $this->assertSame('0', (string) self::$after['other_active']);
    }

    public function test_preferences_are_switched_to_bjcp2025(): void
    {
        $this->assertSame('BJCP2025', self::$after['prefs']);
    }
}
