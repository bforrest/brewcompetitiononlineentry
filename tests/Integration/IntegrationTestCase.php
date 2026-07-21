<?php
/**
 * IntegrationTestCase — base class for all Tier-2 (DB) tests.
 *
 * Isolation strategy: transaction rollback.
 *
 * All BCOEM tables now use the InnoDB storage engine (converted from MyISAM
 * in the baseline SQL schema).  InnoDB supports full ACID transactions, so
 * each test runs inside a single transaction that is rolled back in tearDown()
 * — no data ever commits to disk, and baseline schema data is untouched between
 * runs.
 *
 * A one-time orphan sweep in setUpBeforeClass() removes any leftover rows from
 * runs that pre-date the InnoDB migration (when MyISAM silently ignored
 * rollbacks and data accumulated).
 *
 * Prerequisites:
 *   - Docker MariaDB container running:  docker-compose up -d db
 *   - Env vars (defaults match docker-compose.yml):
 *       BCOEM_DB_HOST       = 127.0.0.1   (NOT 'db' — we're outside Docker)
 *       BCOEM_DB_USER       = bcoem
 *       BCOEM_DB_PASSWORD   = bcoem_password
 *       BCOEM_DB_NAME       = bcoem
 *       BCOEM_DB_PORT       = 3306
 *       BCOEM_DB_PREFIX     = baseline_
 *
 * Running only integration tests:
 *   ./vendor/bin/phpunit --testsuite Integration
 *
 * Running all suites (unit + integration):
 *   ./vendor/bin/phpunit
 */

declare(strict_types=1);

namespace BCOEM\Tests\Integration;

use mysqli;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    /** Shared connection; opened once per test class. */
    protected static mysqli $conn;

    /** Database name, e.g. 'bcoem'. */
    protected static string $db;

    /** Table prefix, e.g. 'baseline_'. */
    protected static string $pfx;

    // ── Lifecycle ──────────────────────────────────────────────────────────────

    /**
     * Open the DB connection once for the whole test class, then sweep out
     * any leftover test rows from runs that pre-date the InnoDB migration.
     */
    public static function setUpBeforeClass(): void
    {
        $host = getenv('BCOEM_DB_HOST')     ?: '127.0.0.1';
        $user = getenv('BCOEM_DB_USER')     ?: 'bcoem';
        $pass = getenv('BCOEM_DB_PASSWORD') ?: 'bcoem_password';
        $db   = getenv('BCOEM_DB_NAME')     ?: 'bcoem';
        $port = (int)(getenv('BCOEM_DB_PORT') ?: 3306);
        $pfx  = getenv('BCOEM_DB_PREFIX')   ?: 'baseline_';

        self::$db  = $db;
        self::$pfx = $pfx;

        // PHP 8.1+ throws mysqli_sql_exception on connection failure, but the
        // extension also emits a PHP Warning before throwing — which PHPUnit
        // counts as a test warning.  The @ suppresses that C-level warning;
        // connect_error / the caught exception still detect real failures.
        try {
            self::$conn = @new mysqli($host, $user, $pass, $db, $port);
            if (self::$conn->connect_error) {
                throw new \mysqli_sql_exception(self::$conn->connect_error);
            }
            mysqli_set_charset(self::$conn, 'utf8mb4');
        } catch (\mysqli_sql_exception $e) {
            self::markTestSkipped(
                "Integration DB unavailable ({$host}:{$port}): "
                . $e->getMessage()
                . " — run: docker-compose up -d db"
            );
        }

        // One-time sweep: remove leftover rows from pre-InnoDB runs where
        // MyISAM ignored rollbacks and test data accumulated.
        self::sweepOrphanTestData();
    }

    /**
     * Delete any rows matching our test-data conventions.
     * Called once per class before any tests run.
     */
    private static function sweepOrphanTestData(): void
    {
        $conn = self::$conn;
        $pfx  = self::$pfx;

        $result = $conn->query(
            "SELECT id FROM `{$pfx}users` WHERE user_name LIKE '%@test.example'"
        );
        $testUserIds = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $testUserIds[] = (int)$row['id'];
            }
        }

        if ($testUserIds) {
            $ids = implode(',', $testUserIds);
            $conn->query("DELETE FROM `{$pfx}brewing` WHERE brewBrewerID IN ({$ids})");
            $conn->query("DELETE FROM `{$pfx}brewer`  WHERE uid IN ({$ids})");
            $conn->query("DELETE FROM `{$pfx}users`   WHERE id  IN ({$ids})");
        }

        $conn->query(
            "DELETE FROM `{$pfx}judging_tables`
             WHERE tableName IN ('IPA Table','Stout Table')"
        );
        $conn->query(
            "DELETE FROM `{$pfx}judging_locations`
             WHERE judgingLocName IN ('Main Hall','Side Room')"
        );
    }

    /** Close the shared connection after all tests in this class. */
    public static function tearDownAfterClass(): void
    {
        if (isset(self::$conn) && !self::$conn->connect_error) {
            self::$conn->close();
        }
    }

    /**
     * Before each test: seed the PHP globals that library functions expect,
     * set minimal session defaults, and open a transaction for rollback isolation.
     */
    protected function setUp(): void
    {
        // Expose connection + DB metadata as PHP globals so library functions
        // that call require(CONFIG.'config.php') find an existing connection
        // and skip opening a new one.
        $GLOBALS['connection'] = self::$conn;
        $GLOBALS['database']   = self::$db;
        $GLOBALS['prefix']     = self::$pfx;
        $GLOBALS['brewing']    = self::$conn;

        // Session defaults — mirrors a minimal logged-in state
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['prefsStyleSet']          = $_SESSION['prefsStyleSet']          ?? 'BJCP2021';
        $_SESSION['prefsProEdition']        = $_SESSION['prefsProEdition']        ?? 0;
        $_SESSION['prefsSEF']               = $_SESSION['prefsSEF']               ?? '';
        $_SESSION['style_set_category_end'] = $_SESSION['style_set_category_end'] ?? 0;

        // Begin transaction — rolled back in tearDown() so no test data commits
        self::$conn->begin_transaction();
    }

    /**
     * After each test: roll back the transaction (discards all inserts/updates
     * made during the test), then clean up PHP globals.
     */
    protected function tearDown(): void
    {
        if (isset(self::$conn) && !self::$conn->connect_error) {
            self::$conn->rollback();
        }

        // Remove globals so they don't bleed into Unit tests if suites are mixed
        unset($GLOBALS['connection'], $GLOBALS['database'], $GLOBALS['prefix'], $GLOBALS['brewing']);
    }

    // ── Convenience helpers ────────────────────────────────────────────────────

    /**
     * Insert a row into `{prefix}{table}` and return the new auto-increment id.
     *
     * @param  string  $table  Table name WITHOUT prefix (e.g. 'users', 'brewer')
     * @param  array   $data   Associative column => value array
     * @return int             The inserted row's id
     */
    protected function insert(string $table, array $data): int
    {
        $fullTable = self::$pfx . $table;
        $cols = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $vals = implode(', ', array_map(
            fn($v) => $v === null ? 'NULL' : "'" . self::$conn->real_escape_string((string)$v) . "'",
            $data
        ));
        $sql = "INSERT INTO `{$fullTable}` ({$cols}) VALUES ({$vals})";
        $result = self::$conn->query($sql);
        if (!$result) {
            $this->fail("DB insert failed [{$fullTable}]: " . self::$conn->error . "\nSQL: {$sql}");
        }
        return (int)self::$conn->insert_id;
    }

    /**
     * Run a SELECT and return all rows as associative arrays.
     *
     * @param  string  $table   Table name WITHOUT prefix
     * @param  string  $where   Optional WHERE clause (without the word WHERE)
     * @return array[]
     */
    protected function select(string $table, string $where = ''): array
    {
        $fullTable = self::$pfx . $table;
        $sql = "SELECT * FROM `{$fullTable}`" . ($where ? " WHERE {$where}" : '');
        $result = self::$conn->query($sql);
        if (!$result) {
            $this->fail("DB select failed [{$fullTable}]: " . self::$conn->error);
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Insert a minimal user + brewer record pair.
     * Returns ['userId' => int, 'brewerId' => int].
     */
    protected function insertTestUser(
        string $email,
        string $firstName = 'Test',
        string $lastName  = 'Brewer',
        string $discount  = 'N'
    ): array {
        $userId = $this->insert('users', [
            'user_name'   => $email,
            'password'    => '$2a$08$placeholder',
            'userLevel'   => '2',
            'userCreated' => date('Y-m-d H:i:s'),
        ]);

        $brewerId = $this->insert('brewer', [
            'uid'             => $userId,
            'brewerFirstName' => $firstName,
            'brewerLastName'  => $lastName,
            'brewerEmail'     => $email,
            'brewerDiscount'  => $discount,
            'brewerJudgeRank' => 'Non-BJCP',
            'brewerJudgeMead' => 'N',
            'brewerCountry'   => 'United States',
        ]);

        return ['userId' => $userId, 'brewerId' => $brewerId];
    }

    /**
     * Insert a minimal brewing entry for a given brewer.
     * Returns the new entry id.
     */
    protected function insertEntry(
        int    $brewerId,
        string $style     = '1A',
        int    $paid      = 1,
        int    $received  = 1,
        int    $confirmed = 1
    ): int {
        return $this->insert('brewing', [
            'brewBrewerID'    => $brewerId,
            'brewStyle'       => $style,
            'brewCategory'    => substr($style, 0, 2),
            'brewCategorySort'=> substr($style, 0, 2),
            'brewPaid'        => $paid,
            'brewReceived'    => $received,
            'brewConfirmed'   => $confirmed,
            'brewName'        => 'Test Beer',
        ]);
    }
}
