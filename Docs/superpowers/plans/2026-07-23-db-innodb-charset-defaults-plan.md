# Complete InnoDB Conversion and Set Charset/Collation Defaults Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the MyISAM→InnoDB conversion across every table-creation code path (fresh install, four version-upgrade scripts, runtime `payments` table, and a new unconditional idempotent conversion in `update.php` for existing production installs), and set `utf8mb4`/`utf8mb4_unicode_ci` as the MariaDB server-level default on the Docker deployment target.

**Architecture:** Two independent, small fixes bundled together because they were found in the same investigation: (1) a Docker Compose `command:` flag change for server-level charset/collation, and (2) six files' worth of `ENGINE=MyISAM` → `ENGINE=InnoDB` string swaps plus one new, unit-testable function (`convert_myisam_tables_to_innodb()`) wired into `update.php`.

**Tech Stack:** PHP 8.2 (legacy procedural + mysqli), PHPUnit (Integration tier, real MariaDB via Docker), Docker Compose / MariaDB 11.

## Global Constraints

- `site/config.php`'s existing per-connection `SET`/`mysqli_set_charset` calls stay completely untouched (shared-hosting safety net) — do not modify `site/config.php` in this plan.
- No `FULLTEXT` indexes exist anywhere in the schema — no conversion risk to guard against.
- `convert_myisam_tables_to_innodb()` must catch failures per-table and never let one table's failure abort the rest — this codebase runs PHP 8.2 with `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)` set globally (`src/Kernel/container.php:57`, and PHP 8.1+ defaults to this anyway), so a failed `mysqli_query()` throws `mysqli_sql_exception` rather than returning `false` — catch that exception type, not a falsy return value.
- Do not touch `update/current_alter_tables.php` / `update/current_data_updates.php` — investigated and rejected as the conversion's insertion point (they only run for installs below version 2.1.8.0).
- Do not add a test harness for `update.php` itself — out of scope; wiring is a two-line call verified by manual smoke-test.

---

### Task 1: MariaDB server-level charset/collation defaults

**Files:**
- Modify: `docker-compose.yml:87-88`

**Interfaces:** None — this task is self-contained infrastructure config, consumed by nothing else in this plan.

- [ ] **Step 1: Add the `command:` flags to the `db` service**

In `docker-compose.yml`, the `db` service currently starts like this:

```yaml
  db:
    image: mariadb:11
    environment:
```

Change it to:

```yaml
  db:
    image: mariadb:11
    command: ["--character-set-server=utf8mb4", "--collation-server=utf8mb4_unicode_ci"]
    environment:
```

- [ ] **Step 2: Recreate the `db` container and verify the server defaults**

Run:
```bash
docker compose up -d --force-recreate db
```

Wait for the healthcheck to pass (`docker compose ps db` shows `healthy`), then run:
```bash
docker compose exec db mariadb -u root -proot_password -e "SHOW VARIABLES LIKE 'character_set_server'; SHOW VARIABLES LIKE 'collation_server';"
```

Expected output:
```
+----------------------+---------+
| Variable_name        | Value   |
+----------------------+---------+
| character_set_server | utf8mb4 |
+----------------------+---------+
+-------------------+--------------------+
| Variable_name     | Value              |
+-------------------+--------------------+
| collation_server  | utf8mb4_unicode_ci |
+-------------------+--------------------+
```

If the values don't match, re-check the `command:` array syntax (YAML list of individual `--flag=value` strings, not one combined string) and re-run Step 2.

- [ ] **Step 3: Commit**

```bash
git add docker-compose.yml
git commit -m "fix: set utf8mb4/utf8mb4_unicode_ci as MariaDB server-level defaults"
```

---

### Task 2: Fresh-install script — MyISAM → InnoDB

**Files:**
- Modify: `setup/install_db.setup.php` (22 occurrences of `ENGINE=MyISAM`)

**Interfaces:** None — pure string substitution, no logic changes, nothing else depends on this task's internals.

- [ ] **Step 1: Verify current occurrence count**

Run:
```bash
grep -c "ENGINE=MyISAM" setup/install_db.setup.php
```
Expected: `22`

- [ ] **Step 2: Replace every occurrence**

Use the Edit tool on `setup/install_db.setup.php` with `replace_all: true`:
- `old_string`: `ENGINE=MyISAM`
- `new_string`: `ENGINE=InnoDB`

- [ ] **Step 3: Verify zero occurrences remain**

Run:
```bash
grep -c "ENGINE=MyISAM" setup/install_db.setup.php
```
Expected: `0` (grep exits 1 on no matches — that's expected here, not a failure)

Run a PHP lint to confirm the file is still syntactically valid:
```bash
php -l setup/install_db.setup.php
```
Expected: `No syntax errors detected in setup/install_db.setup.php`

- [ ] **Step 4: Commit**

```bash
git add setup/install_db.setup.php
git commit -m "fix: use InnoDB for fresh-install table creation"
```

---

### Task 3: Version-upgrade scripts — MyISAM → InnoDB

**Files:**
- Modify: `update/1.2.0.0_update.php` (5 occurrences)
- Modify: `update/1.2.1.0_update.php` (3 occurrences)
- Modify: `update/1.3.0.0_update.php` (2 occurrences)
- Modify: `update/2.1.0.0_update.php` (1 occurrence)

**Interfaces:** None — pure string substitution, no logic changes.

- [ ] **Step 1: Verify current occurrence counts**

Run:
```bash
grep -c "ENGINE=MyISAM" update/1.2.0.0_update.php update/1.2.1.0_update.php update/1.3.0.0_update.php update/2.1.0.0_update.php
```
Expected:
```
update/1.2.0.0_update.php:5
update/1.2.1.0_update.php:3
update/1.3.0.0_update.php:2
update/2.1.0.0_update.php:1
```

- [ ] **Step 2: Replace every occurrence in each file**

Use the Edit tool with `replace_all: true` on each file:
- `old_string`: `ENGINE=MyISAM`
- `new_string`: `ENGINE=InnoDB`

Apply to all four files: `update/1.2.0.0_update.php`, `update/1.2.1.0_update.php`, `update/1.3.0.0_update.php`, `update/2.1.0.0_update.php`.

- [ ] **Step 3: Verify zero occurrences remain and files still lint clean**

Run:
```bash
grep -c "ENGINE=MyISAM" update/1.2.0.0_update.php update/1.2.1.0_update.php update/1.3.0.0_update.php update/2.1.0.0_update.php
php -l update/1.2.0.0_update.php && php -l update/1.2.1.0_update.php && php -l update/1.3.0.0_update.php && php -l update/2.1.0.0_update.php
```
Expected: each `grep -c` reports `0` (or the file is absent from a match-only listing), and each `php -l` reports `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add update/1.2.0.0_update.php update/1.2.1.0_update.php update/1.3.0.0_update.php update/2.1.0.0_update.php
git commit -m "fix: use InnoDB for version-upgrade table creation"
```

---

### Task 4: Runtime `payments` table — MyISAM → InnoDB

**Files:**
- Modify: `includes/process/process_prefs.inc.php:542,616`

**Interfaces:** None — pure string substitution.

- [ ] **Step 1: Verify current occurrence count**

Run:
```bash
grep -n "ENGINE=MyISAM" includes/process/process_prefs.inc.php
```
Expected: two matches, at lines 542 and 616, both reading:
```
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",$prefix."payments");
```

- [ ] **Step 2: Replace both occurrences**

Use the Edit tool on `includes/process/process_prefs.inc.php` with `replace_all: true`:
- `old_string`: `ENGINE=MyISAM`
- `new_string`: `ENGINE=InnoDB`

- [ ] **Step 3: Verify**

Run:
```bash
grep -c "ENGINE=MyISAM" includes/process/process_prefs.inc.php
php -l includes/process/process_prefs.inc.php
```
Expected: `grep -c` reports `0`; `php -l` reports `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add includes/process/process_prefs.inc.php
git commit -m "fix: use InnoDB for runtime payments table creation"
```

---

### Task 5: `convert_myisam_tables_to_innodb()` — new function + test

**Files:**
- Modify: `lib/update.lib.php` (add new function; guard existing `check_setup()` with `function_exists()`)
- Test: `tests/Integration/ConvertMyisamTablesToInnodbTest.php`

**Interfaces:**
- Produces: `convert_myisam_tables_to_innodb(mysqli $connection, string $prefix): array` — issues `ALTER TABLE {prefix}{table} ENGINE=InnoDB` for each of the 24 real tables listed below, catches `mysqli_sql_exception` per-table, and returns an associative array `[tableNameWithoutPrefix => exceptionMessage]` containing **only the tables that failed** (an empty array means full success). Table list (bare names, no prefix): `archive`, `bcoem_sys`, `brewer`, `brewing`, `contacts`, `contest_info`, `drop_off`, `evaluation`, `judging_assignments`, `judging_flights`, `judging_locations`, `judging_preferences`, `judging_scores`, `judging_scores_bos`, `judging_tables`, `mods`, `preferences`, `special_best_data`, `special_best_info`, `sponsors`, `staff`, `styles`, `style_types`, `users`.

**Why `check_setup()` needs a guard:** `tests/bootstrap.php:80-84` already defines a stub `check_setup()` (guarded with `function_exists()`) so Unit tests don't hit a real DB. `lib/update.lib.php`'s `check_setup()` (top of file) is currently declared unconditionally — if an Integration test does `require_once LIB.'update.lib.php'` to reach the new function, PHP fatals with "Cannot redeclare check_setup()". No current test requires `update.lib.php`, so this has never been hit before. Wrapping the existing declaration in the same `function_exists()` guard bootstrap.php already uses elsewhere resolves this with no behavior change in production (the file is only ever loaded once there).

- [ ] **Step 1: Write the failing test**

Create `tests/Integration/ConvertMyisamTablesToInnodbTest.php`:

```php
<?php
/**
 * Integration tests for convert_myisam_tables_to_innodb().
 *
 * convert_myisam_tables_to_innodb(mysqli $connection, string $prefix): array
 *
 * Converts each of the 24 real BCOEM tables to InnoDB via
 * `ALTER TABLE {prefix}{table} ENGINE=InnoDB`, catching failures per-table
 * rather than aborting. Returns [tableName => errorMessage] for tables that
 * failed; an empty array means every table converted (or was already
 * InnoDB — the ALTER is a safe no-op in that case).
 */

declare(strict_types=1);

namespace BCOEM\Tests\Integration;

class ConvertMyisamTablesToInnodbTest extends IntegrationTestCase
{
    public function testConvertsAMyisamTableToInnodb(): void
    {
        $pfx = self::$pfx;

        // Force one real table to MyISAM first (ALTER TABLE causes an
        // implicit commit in MySQL/MariaDB, ending this test's open
        // transaction — that's fine, the table is schema-only, no rows
        // are involved, and the conversion below restores it to InnoDB
        // either way).
        self::$conn->query("ALTER TABLE `{$pfx}sponsors` ENGINE=MyISAM");

        $failures = convert_myisam_tables_to_innodb(self::$conn, $pfx);

        $this->assertArrayNotHasKey('sponsors', $failures);
        $this->assertSame('InnoDB', $this->engineOf('sponsors'));
    }

    public function testAlreadyInnodbTableIsLeftAloneWithoutError(): void
    {
        $pfx = self::$pfx;

        // baseline schema already creates this table as InnoDB
        $this->assertSame('InnoDB', $this->engineOf('style_types'));

        $failures = convert_myisam_tables_to_innodb(self::$conn, $pfx);

        $this->assertArrayNotHasKey('style_types', $failures);
        $this->assertSame('InnoDB', $this->engineOf('style_types'));
    }

    public function testUnconvertibleTablesAreCapturedNotThrown(): void
    {
        // A prefix that matches no real table - all 24 ALTER statements
        // fail ("table doesn't exist"), and none of them may throw.
        $failures = convert_myisam_tables_to_innodb(self::$conn, 'nonexistent_prefix_');

        $this->assertCount(24, $failures);
        $this->assertArrayHasKey('users', $failures);
        $this->assertNotSame('', $failures['users']);
    }

    private function engineOf(string $table): ?string
    {
        $pfx = self::$pfx;
        $result = self::$conn->query(
            "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . self::$db . "' AND TABLE_NAME = '{$pfx}{$table}'"
        );
        $row = $result->fetch_assoc();
        return $row['ENGINE'] ?? null;
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Prerequisite: `docker compose up -d db` must be running (Task 1 will have already recreated it).

Run:
```bash
BCOEM_DB_HOST=127.0.0.1 BCOEM_DB_USER=bcoem BCOEM_DB_PASSWORD=bcoem_password BCOEM_DB_NAME=bcoem BCOEM_DB_PORT=3306 BCOEM_DB_PREFIX=baseline_ ./vendor/bin/phpunit tests/Integration/ConvertMyisamTablesToInnodbTest.php
```
Expected: FAIL / Error — `Call to undefined function convert_myisam_tables_to_innodb()` (the function doesn't exist yet; the file also isn't required yet).

- [ ] **Step 3: Add `require_once` for the library under test**

Since `lib/update.lib.php` isn't loaded by `tests/bootstrap.php`, the test file needs it directly. Add this after the `namespace` line in `tests/Integration/ConvertMyisamTablesToInnodbTest.php`:

```php
namespace BCOEM\Tests\Integration;

require_once LIB . 'update.lib.php';

class ConvertMyisamTablesToInnodbTest extends IntegrationTestCase
```

- [ ] **Step 4: Guard the existing `check_setup()` declaration**

In `lib/update.lib.php`, find:

```php
<?php
function check_setup($tablename, $database) {
	
	require(CONFIG.'config.php');
	mysqli_select_db($connection,$database);
	
	$query_log = sprintf("SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = '%s' AND table_name = '%s'", $database, $tablename);
	$log = mysqli_query($connection,$query_log) or die (mysqli_error($connection));
	$row_log = mysqli_fetch_assoc($log);

	if ($row_log['count'] == 0) return FALSE;
	else return TRUE;

}
```

Replace with:

```php
<?php
if (!function_exists('check_setup')) {
function check_setup($tablename, $database) {
	
	require(CONFIG.'config.php');
	mysqli_select_db($connection,$database);
	
	$query_log = sprintf("SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = '%s' AND table_name = '%s'", $database, $tablename);
	$log = mysqli_query($connection,$query_log) or die (mysqli_error($connection));
	$row_log = mysqli_fetch_assoc($log);

	if ($row_log['count'] == 0) return FALSE;
	else return TRUE;

}
}
```

- [ ] **Step 5: Add `convert_myisam_tables_to_innodb()`**

At the end of `lib/update.lib.php`, immediately before the closing `?>`, add:

```php
function convert_myisam_tables_to_innodb(mysqli $connection, string $prefix): array {

	$tables = array(
		'archive', 'bcoem_sys', 'brewer', 'brewing', 'contacts', 'contest_info',
		'drop_off', 'evaluation', 'judging_assignments', 'judging_flights',
		'judging_locations', 'judging_preferences', 'judging_scores',
		'judging_scores_bos', 'judging_tables', 'mods', 'preferences',
		'special_best_data', 'special_best_info', 'sponsors', 'staff',
		'styles', 'style_types', 'users',
	);

	$failures = array();

	foreach ($tables as $table) {
		$sql = sprintf('ALTER TABLE `%s%s` ENGINE=InnoDB', $prefix, $table);
		try {
			mysqli_query($connection, $sql);
		} catch (mysqli_sql_exception $e) {
			$failures[$table] = $e->getMessage();
		}
	}

	return $failures;

}
```

- [ ] **Step 6: Run the test to verify it passes**

Run:
```bash
BCOEM_DB_HOST=127.0.0.1 BCOEM_DB_USER=bcoem BCOEM_DB_PASSWORD=bcoem_password BCOEM_DB_NAME=bcoem BCOEM_DB_PORT=3306 BCOEM_DB_PREFIX=baseline_ ./vendor/bin/phpunit tests/Integration/ConvertMyisamTablesToInnodbTest.php
```
Expected: `OK (3 tests, ...)`

- [ ] **Step 7: Run the full test suite to confirm no regressions**

Run:
```bash
BCOEM_DB_HOST=127.0.0.1 BCOEM_DB_USER=bcoem BCOEM_DB_PASSWORD=bcoem_password BCOEM_DB_NAME=bcoem BCOEM_DB_PORT=3306 BCOEM_DB_PREFIX=baseline_ ./vendor/bin/phpunit
```
Expected: `OK (...)` — all suites green, in particular no other Integration test broke from the `check_setup()` guard change.

Also run PHPStan (this project's static analysis, config already covers `lib/`):
```bash
./vendor/bin/phpstan analyse lib/update.lib.php
```
Expected: no new errors related to `convert_myisam_tables_to_innodb()` or `check_setup()`.

- [ ] **Step 8: Commit**

```bash
git add lib/update.lib.php tests/Integration/ConvertMyisamTablesToInnodbTest.php
git commit -m "feat: add convert_myisam_tables_to_innodb() with integration tests"
```

---

### Task 6: Wire the conversion into `update.php`

**Files:**
- Modify: `update.php:24-30`

**Interfaces:**
- Consumes: `convert_myisam_tables_to_innodb(mysqli $connection, string $prefix): array` from Task 5.

- [ ] **Step 1: Call the function unconditionally near the top**

In `update.php`, find the existing idempotent `bcoem_sys` rename block:

```php
// Check if bcoem_sys is there. If not, change.
if (table_exists($prefix."system")) {
	$query_sys = sprintf("RENAME TABLE `%s` TO `%s`",$prefix."system",$prefix."bcoem_sys");
	$sys = mysqli_query($connection,$query_sys) or die (mysqli_error($connection));
	$system_name_change = TRUE;
}

require_once (DB.'common.db.php');
```

Replace with:

```php
// Check if bcoem_sys is there. If not, change.
if (table_exists($prefix."system")) {
	$query_sys = sprintf("RENAME TABLE `%s` TO `%s`",$prefix."system",$prefix."bcoem_sys");
	$sys = mysqli_query($connection,$query_sys) or die (mysqli_error($connection));
	$system_name_change = TRUE;
}

// Convert any remaining MyISAM tables to InnoDB. Safe no-op for tables
// already on InnoDB. Runs on every load regardless of stored version so
// existing installs get converted the next time an operator visits this
// page - failures are surfaced below as a notice, not a blocker.
$innodb_conversion_failures = convert_myisam_tables_to_innodb($connection, $prefix);

require_once (DB.'common.db.php');
```

- [ ] **Step 2: Display a notice when conversions fail**

Find:

```php
$filename = INCLUDES."version.inc.php";
$update_alerts = "";
$update_body = "";
$output = "";
```

Replace with:

```php
$filename = INCLUDES."version.inc.php";
$update_alerts = "";
$update_body = "";
$output = "";

if (!empty($innodb_conversion_failures)) {
	$update_alerts .= "<div class=\"alert alert-warning\"><span class=\"fa fa-lg fa-exclamation-triangle\"></span> <strong>"
		. count($innodb_conversion_failures)
		. " table(s) could not be converted to InnoDB:</strong> "
		. htmlspecialchars(implode(', ', array_keys($innodb_conversion_failures)))
		. "</div>";
}
```

- [ ] **Step 3: Lint check**

Run:
```bash
php -l update.php
```
Expected: `No syntax errors detected in update.php`

- [ ] **Step 4: Manual smoke-test**

This entry point has no existing test harness (confirmed in the design doc's Testing section) and adding one is out of scope. Verify manually:

```bash
docker compose up -d web db
```

Visit `http://localhost:8080/update.php` in a browser while logged in as a top-level admin on a fresh Docker install (all 24 tables already InnoDB from the baseline SQL). Confirm:
- The page loads without a fatal error.
- No "N table(s) could not be converted to InnoDB" warning appears (since the Docker baseline schema tables are already InnoDB — all 24 `ALTER TABLE` calls are no-ops).
- The rest of the update flow (version comparison, "up to date" message) renders as before.

- [ ] **Step 5: Commit**

```bash
git add update.php
git commit -m "feat: run idempotent InnoDB conversion on every update.php load"
```

---

## Post-Plan Verification

After all six tasks:

```bash
grep -rn "ENGINE=MyISAM" setup/install_db.setup.php update/1.2.0.0_update.php update/1.2.1.0_update.php update/1.3.0.0_update.php update/2.1.0.0_update.php includes/process/process_prefs.inc.php
```
Expected: no output (zero remaining occurrences across all six touched files).

```bash
BCOEM_DB_HOST=127.0.0.1 BCOEM_DB_USER=bcoem BCOEM_DB_PASSWORD=bcoem_password BCOEM_DB_NAME=bcoem BCOEM_DB_PORT=3306 BCOEM_DB_PREFIX=baseline_ ./vendor/bin/phpunit
```
Expected: full suite green.
