> **Status update (2026-07-19, later same day):** Tasks 1–3 were superseded by cherry-picking three pre-existing fix branches (`fix/login-sql-injection` 092203bc, `fix/md5-password-prehashing` 9bba67f3, `fix/pdf-download-path-traversal` 57c94420) rather than written from scratch per the steps below. All three are now on `docker-baseline-db`. Details, actual function names, and what's still open (Task 4 — session regeneration — and test coverage) are in the "Cherry-pick reconciliation" section right after this banner. Tasks 1–3's step-by-step content below is kept for historical/reference purposes (it describes a *different but equivalent* implementation) — don't re-execute it.
>
> The fourth branch the user pointed at, `fix/concurrent-auto-update-lock-storm`, turned out to already be merged into `docker-baseline-db` (as commit `6a876ed7`, identical content) — nothing to do there.

## Cherry-pick reconciliation (2026-07-19)

**What landed, in order:**
1. `092203bc` — parameterizes all 4 SQL statements in `includes/logincheck.inc.php` via `mysqli_prepare`/`bind_param`/`get_result`, and removes a **critical unconditional-bypass bug** (`$check = 1;` overwriting the real password-check result on the legacy `section=update` login branch) — the same bug I independently found while scoping Task 1 below. Confirmed via `git log` this predates and is broader than anything in the existing `Docs/Technical Review*.md` reviews (those only mention "auth bypass" as a *consequence* of the SQLi, not this separate logic bug).
2. `9bba67f3` — removes MD5 pre-hashing everywhere, replacing phpass with native `password_hash()`/`password_verify()`. Conflicted with (1) on the same lines in `logincheck.inc.php` (both touch the login lookup); resolved by keeping (1)'s parameterized queries and layering in this commit's verify/migrate calls. Adds three functions to `lib/common.lib.php` (not the `verify_and_migrate_legacy_password()` this plan originally specified — see below): `password_verify_legacy()`, `password_needs_legacy_upgrade()`, `upgrade_legacy_password_hash()`. Distinguishes old vs. new hashes by bcrypt prefix (`$2a$` = legacy phpass, `$2y$` = new `PASSWORD_BCRYPT`) rather than "try current scheme, fall back to legacy" — a cleaner design than this plan's original approach. Bonus fix caught in the same commit: `qr.php`'s `$_SESSION['qrPasswordOK']` was storing the (formerly MD5, now would-be-plaintext) check-in password in the session; changed to store `TRUE`.
3. `57c94420` — fixes the `handle.php` traversal with two independent layers: a character-class + `..`-substring check on `$id`, **and** a `realpath()` containment check confirming the resolved path still falls inside `USER_DOCS` regardless of (1) — stronger than this plan's single-layer `safe_document_id()` design. No new named helper function; the check is inline in `handle.php`.

**Verification performed:**
- `docker compose exec web vendor/bin/phpstan analyse` → clean, no new errors.
- Full 3-tier PHPUnit suite on a freshly reseeded DB (`docker compose down -v && up -d --build`) → `336 tests, 0 failures` (19 pre-existing warnings/1 deprecation/6 skips, all pre-catalogued, none new).
- Full Playwright suite twice → all pass, including the seeded-admin login (whose stored hash is legacy-scheme — empirically confirmed via `crypt(md5('bcoem'), $hash) === $hash` — so this is a live regression test for the MD5 migration, no synthetic fixture needed).
- Flipped the `P1-SEC-005` `test.fixme` in `e2e/tests/security-invariants.spec.ts` to a plain test now that the traversal fix is in; ran twice more, green both times.
- **Manually reproduced the critical bypass against the running app** with a real HTTP request (fresh session + CSRF token from `?section=register`, POST to `includes/process.inc.php?section=update&action=login` with the seeded admin's username and a wrong password): before these fixes this would have logged in as admin; now correctly redirects to `?msg=11` (rejected).

**Closed out same day (2026-07-19):**
- **Task 4 (session regeneration on login, P1-SEC-006)** — added `session_regenerate_id(true);` as the first line of `logincheck.inc.php`'s `if ($check == 1)` block.
- **Regression tests added** for all four fixes, against the actual landed API:
  - `tests/Unit/SecurityAndCryptoTest.php` — 7 new tests for `password_verify_legacy()` / `password_needs_legacy_upgrade()` (current-scheme hash, legacy `$2a$` hash, wrong password, empty hash).
  - `tests/Integration/PasswordLegacyMigrationTest.php` — 3 new tests for `upgrade_legacy_password_hash()`, including a full end-to-end "legacy hash → verify → migrate → still verifies, old hash no longer does" sequence mirroring exactly what `logincheck.inc.php` runs.
  - `e2e/tests/security-invariants.spec.ts` — flipped the `P1-SEC-005` `fixme` to a plain test, added two more traversal payloads (bare `..`, backslash), added the `section=update` bypass regression test, a legacy-admin-login test, a fresh-registration round-trip test, and the session-regeneration-on-login test.
- **Full verification:** PHPStan clean; PHPUnit `346 tests, 0 failures` on a fresh DB (up from 336 — the 10 new Unit/Integration tests); full Playwright suite (18 tests) green.

---

# Phase 1 — Security Fixes Under the Safety Net Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the P1 security findings (login SQL injection, MD5 password pre-hashing, `handle.php` path traversal, missing session regeneration) behind the Phase 0 e2e/characterization safety net, plus a **critical authentication-bypass bug newly discovered while planning this phase** (see below) — flipping the Phase-0 `test.fixme` security-invariant tests green and adding new ones as acceptance criteria.

**Architecture:** All fixes stay inside the existing legacy files (`includes/logincheck.inc.php`, `handle.php`, `qr.php`, `includes/process/process_*.inc.php`) — no Slim shell, no `src/` yet (that's Phase 2). Two small, genuinely reusable pieces of logic move into `lib/common.lib.php` as named functions so they're unit/integration-testable: a parameterized user lookup and a legacy-password verify-and-migrate helper. Everything else stays inline. Spec: `Docs/superpowers/specs/2026-07-18-modernization-slim-strangler-design.md` (Phase plan table, row "1").

**Tech Stack:** PHPUnit ^10 (PHP 8.2 inside the `web` container), Playwright `@playwright/test`, MariaDB 11 (InnoDB), `phpass/PasswordHash`, `mysqli` prepared statements (`mysqli_prepare`/`bind_param`/`get_result` — the mysqlnd-backed API already available in the `web` image).

## Global Constraints

- App URL: `http://localhost:8080` (Docker compose stack, service `web`). DB: host `db` prefix `baseline_`, db `bcoem`, user `bcoem` / `bcoem_password`.
- Seeded admin login (userLevel 0): `user.baseline@brewingcompetitions.com` / `bcoem`. **Its stored hash is `HashPassword(md5('bcoem'))`** — verified empirically (`crypt(md5('bcoem'), $hash) === $hash` is true, `crypt('bcoem', $hash) === $hash` is false) — so the existing Playwright admin-login smoke test is already a live regression test for the legacy-hash migration path in Task 2; no synthetic legacy-hash fixture is needed.
- mysqli on PHP 8.2 defaults to `MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT` (exceptions on failure) — new prepared-statement code should NOT wrap calls in `or die(...)`; let failures throw (matches how the rest of the app already behaves under PHP 8.1+, whether or not the surrounding `or die()` idiom still fires).
- `find_user_by_username()` and `verify_and_migrate_legacy_password()` (added in Tasks 1–2) take table/column names as **literal string constants supplied by the caller only** — they are interpolated directly into SQL (identifiers can't be bound as parameters). Never pass user input as `$updateTable`/`$updateColumn`.
- Unit tests: plain `TestCase` classes, no namespace, directory-based discovery (`tests/Unit`) — matches existing files (`tests/Unit/SecurityAndCryptoTest.php`, etc.). Integration tests: `namespace BCOEM\Tests\Integration;`, extend `IntegrationTestCase`, real DB, transaction-rollback isolation.
- Every task that changes `lib/common.lib.php` ends with `docker compose exec web vendor/bin/phpstan analyse` clean (no new errors) and `docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit` (all 3 tiers) green.
- e2e discipline carries over from Phase 0: run each new/modified spec until green **twice in a row** before committing; flip a `test.fixme` to a plain test only once it's actually green.
- Reseeding the DB: `docker compose down -v && docker compose up -d --build`, then wait for `docker compose ps` to show `db` healthy.

---

## ⚠️ Newly discovered during Phase-1 planning (2026-07-19): critical login auth-bypass

While reading `includes/logincheck.inc.php` to scope the SQLi fix, found a bug **not captured in any prior security review**: when the login POST's query string carries `section=update` (a leftover "ONLY for 1.3.0.0 release" migration shim), the code computes the real password check and then **unconditionally overwrites it to success**:

```php
if ($totalRows_login > 0) {
    $check = $hasher->CheckPassword($entered_password, $stored_hash);
    $check = 1;   // <-- always overwrites the real result
}
```

`$_SESSION['loginUsername']` is then set exactly as in a normal successful login. Net effect: **any existing username + any password** (including the admin account) authenticates fully, provided the attacker sends `section=update` instead of `section=login` on the login POST. The route isn't protected by the app's CSRF gate either — `process.inc.php`'s `$bypass_token` list only exempts `section=login`, so `section=update` *requires* a CSRF token, but that token is a same-origin nonce obtainable by any visitor from any public page (e.g. `?section=register`), not a secret tied to the victim — it doesn't stop a direct attacker. Task 1 below fixes this as an inseparable side effect of unifying the (also broken, also SQLi-vulnerable) duplicate lookup branches.

---

### Task 1: Unify + parameterize the login lookup — fixes the `section=update` auth bypass (critical) and the login SQLi (P1-SEC-002)

**Files:**
- Modify: `lib/common.lib.php` (add `find_user_by_username()`)
- Modify: `includes/logincheck.inc.php` (replace both duplicate lookup branches with one parameterized call; parameterize the two post-login `UPDATE`s)
- Create: `tests/Integration/FindUserByUsernameTest.php`
- Modify: `e2e/tests/security-invariants.spec.ts` (new bypass-attempt test)

**Interfaces:**
- Produces: `find_user_by_username(mysqli $connection, string $prefix, string $username): ?array` — returns the matching `{prefix}users` row (assoc array, same shape as `SELECT *`) or `null`. Used by Task 2 and by `logincheck.inc.php`.

- [ ] **Step 1: Write the failing Integration test**

`tests/Integration/FindUserByUsernameTest.php`:

```php
<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration;

class FindUserByUsernameTest extends IntegrationTestCase
{
    public function test_finds_existing_user_by_exact_username(): void
    {
        $this->insertTestUser('lookup-target@test.example');
        $row = \find_user_by_username(self::$conn, self::$pfx, 'lookup-target@test.example');
        $this->assertNotNull($row);
        $this->assertSame('lookup-target@test.example', $row['user_name']);
    }

    public function test_returns_null_for_unknown_username(): void
    {
        $row = \find_user_by_username(self::$conn, self::$pfx, 'nobody-here@test.example');
        $this->assertNull($row);
    }

    public function test_sql_injection_payload_as_username_finds_nothing(): void
    {
        $this->insertTestUser('victim@test.example');
        $row = \find_user_by_username(self::$conn, self::$pfx, "' OR '1'='1");
        $this->assertNull($row);
    }

    public function test_sql_injection_payload_with_comment_finds_nothing(): void
    {
        $this->insertTestUser('victim2@test.example');
        $row = \find_user_by_username(self::$conn, self::$pfx, "victim2@test.example' -- ");
        $this->assertNull($row);
    }
}
```

- [ ] **Step 2: Run — verify it fails**

```bash
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Integration --filter FindUserByUsernameTest
```

Expected: `Error: Call to undefined function find_user_by_username()`.

- [ ] **Step 3: Implement in `lib/common.lib.php`**

Add near the other DB-adjacent helpers:

```php
/**
 * Parameterized user lookup by username, replacing the sprintf()-built
 * "SELECT ... WHERE user_name = '%s'" pattern that only ever escaped (never
 * bound) the value — P1-SEC-002.
 */
function find_user_by_username(mysqli $connection, string $prefix, string $username): ?array {
    $stmt = mysqli_prepare($connection, "SELECT * FROM {$prefix}users WHERE user_name = ?");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}
```

- [ ] **Step 4: Run — verify it passes**

```bash
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Integration --filter FindUserByUsernameTest
```

Expected: `OK (4 tests...)`.

- [ ] **Step 5: Rewrite `includes/logincheck.inc.php`'s lookup — delete the bypass branch**

Replace lines 28–73 (from `mysqli_real_escape_string($connection,$loginUsername);` through the end of the `if ($section != "update") { ... }` block) with:

```php
$loginUsername = strtolower($loginUsername);
$row_login = find_user_by_username($connection, $prefix, $loginUsername);
$totalRows_login = $row_login ? 1 : 0;
$stored_hash = $row_login['password'] ?? null;
$check = 0;

if ($totalRows_login > 0) $check = $hasher->CheckPassword($entered_password, $stored_hash);
```

This deletes: the no-op `mysqli_real_escape_string()` calls and — critically — the entire `if ($section == "update") { ... $check = 1; ... }` branch and its duplicate `if ($section != "update")` twin. There is now exactly one lookup and one real check, regardless of `$section`. Leave the line `$entered_password = md5($entered_password);` in place for now — it stays self-consistent (both the write side and this read side still agree on the md5-prehashed scheme) until Task 2 removes it as part of the MD5 migration fix.

- [ ] **Step 6: Parameterize the two post-login `UPDATE`s**

Still in `logincheck.inc.php`, inside `if ($check == 1) { ... }`, replace:

```php
$updateSQL = sprintf("UPDATE %s SET user_name='%s' WHERE id='%s'",$prefix."users",$loginUsername, $row_login['id']);
mysqli_real_escape_string($connection,$updateSQL);
$result = mysqli_query($connection,$updateSQL) or die("A database error occurred.");

$updateSQL = sprintf("UPDATE %s SET brewerEmail='%s' WHERE uid='%s'",$prefix."brewer",$loginUsername, $row_login['id']);
mysqli_real_escape_string($connection,$updateSQL);
$result = mysqli_query($connection,$updateSQL) or die("A database error occurred.");
```

with:

```php
$stmt = mysqli_prepare($connection, "UPDATE {$prefix}users SET user_name = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'si', $loginUsername, $row_login['id']);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($connection, "UPDATE {$prefix}brewer SET brewerEmail = ? WHERE uid = ?");
mysqli_stmt_bind_param($stmt, 'si', $loginUsername, $row_login['id']);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
```

- [ ] **Step 7: Manual smoke check**

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/index.php
```

Expected: `200`. Then run the existing Playwright smoke suite (proves normal login still works end-to-end):

```bash
cd e2e && npx playwright test tests/smoke.spec.ts
```

Expected: all pass, including "seeded admin can log in and reach the dashboard".

- [ ] **Step 8: Add the bypass-regression e2e test**

Append to `e2e/tests/security-invariants.spec.ts`:

```ts
test('login rejects a wrong password via the legacy section=update bypass path', async ({ page }) => {
  // Historical 1.3.0.0-era branch (triggered by `section=update` on the login
  // POST) computed the real password check then unconditionally overwrote it
  // to "success" — any existing username + ANY password logged in as that
  // user. Fixed by unifying the two lookup/check branches (Task 1).
  await page.goto('/index.php?section=register&go=entrant');
  const token = await page.locator('input[name="user_session_token"]').first().inputValue();

  const resp = await page.request.post('/includes/process.inc.php?section=update&action=login', {
    headers: { referer: page.url() },
    form: {
      loginUsername: ADMIN.email,
      loginPassword: 'definitely-not-the-real-password',
      user_session_token: token,
    },
  });
  expect(resp.url()).toMatch(/msg=11/);
});
```

Add `ADMIN` to the existing import: `import { ADMIN, loginAsAdmin, registerEntrant } from '../helpers/auth';`.

- [ ] **Step 9: Run until green twice**

```bash
cd e2e && npx playwright test tests/security-invariants.spec.ts
cd e2e && npx playwright test tests/security-invariants.spec.ts
```

If the POST doesn't reach the CSRF-gated branch as expected (e.g. `$process_allowed` false because the referrer/session-preference precondition isn't satisfied the way expected), inspect with `curl -i -b cookies.txt -c cookies.txt` against a real browser session first, then adjust the header/flow — don't weaken the assertion.

- [ ] **Step 10: Commit**

```bash
git add lib/common.lib.php includes/logincheck.inc.php tests/Integration/FindUserByUsernameTest.php e2e/tests/security-invariants.spec.ts
git commit -m "Fix critical login auth-bypass (section=update) and parameterize login SQL (P1-SEC-002)"
```

---

### Task 2: Remove MD5 password pre-hashing with transparent legacy-hash migration (P1-SEC-001)

**Files:**
- Modify: `lib/common.lib.php` (add `verify_and_migrate_legacy_password()`)
- Modify: `includes/logincheck.inc.php` (wire in the helper; delete the now-dead `$hasher` var if unused)
- Modify: `includes/process/process_users.inc.php` (self-service password change verify + drop md5 from the 2 hash-creation sites)
- Modify: `includes/process/process_users_register.inc.php`, `includes/process/process_users_setup.inc.php`, `includes/process/process_forgot_password.inc.php`, `includes/process/process_comp_info.inc.php` (drop md5 from hash-creation sites)
- Modify: `qr.php` (verify + migrate the contest check-in password)
- Create: `tests/Integration/VerifyAndMigrateLegacyPasswordTest.php`
- Modify: `e2e/tests/security-invariants.spec.ts` (new round-trip test for freshly-registered users)

**Interfaces:**
- Consumes: nothing new (uses `PasswordHash` from `classes/phpass/PasswordHash.php`, already required elsewhere).
- Produces: `verify_and_migrate_legacy_password(mysqli $connection, string $plaintext, string $storedHash, string $updateTable, string $updateColumn, int $updateId): bool`. Tries the current scheme (`HashPassword($plaintext)`) first; on failure, tries the legacy scheme (`HashPassword(md5($plaintext))`); on a legacy match, rehashes with the current scheme and persists it via a prepared `UPDATE`, then returns `true`. Returns `false` if neither matches (no DB write).

- [ ] **Step 1: Write the failing Integration test**

`tests/Integration/VerifyAndMigrateLegacyPasswordTest.php`:

```php
<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration;

require_once CLASSES.'phpass/PasswordHash.php';

class VerifyAndMigrateLegacyPasswordTest extends IntegrationTestCase
{
    private function hasher(): \PasswordHash
    {
        return new \PasswordHash(8, false);
    }

    public function test_current_scheme_hash_verifies_directly(): void
    {
        $hash = $this->hasher()->HashPassword('CorrectHorse123!');
        $ids = $this->insertTestUser('current-scheme@test.example');

        $this->assertTrue(\verify_and_migrate_legacy_password(
            self::$conn, 'CorrectHorse123!', $hash, self::$pfx.'users', 'password', $ids['userId']
        ));
    }

    public function test_legacy_md5_prehashed_hash_still_verifies(): void
    {
        $hash = $this->hasher()->HashPassword(md5('LegacyPass123!'));
        $ids = $this->insertTestUser('legacy-scheme@test.example');

        $this->assertTrue(\verify_and_migrate_legacy_password(
            self::$conn, 'LegacyPass123!', $hash, self::$pfx.'users', 'password', $ids['userId']
        ));
    }

    public function test_legacy_match_persists_a_rehashed_current_scheme_hash(): void
    {
        $legacyHash = $this->hasher()->HashPassword(md5('LegacyPass123!'));
        $ids = $this->insertTestUser('rehash-target@test.example');

        \verify_and_migrate_legacy_password(
            self::$conn, 'LegacyPass123!', $legacyHash, self::$pfx.'users', 'password', $ids['userId']
        );

        $rows = $this->select('users', "id = {$ids['userId']}");
        $persistedHash = $rows[0]['password'];

        $this->assertNotSame($legacyHash, $persistedHash);
        $this->assertTrue($this->hasher()->CheckPassword('LegacyPass123!', $persistedHash));
        $this->assertFalse($this->hasher()->CheckPassword(md5('LegacyPass123!'), $persistedHash));
    }

    public function test_wrong_password_does_not_match_and_does_not_touch_db(): void
    {
        $hash = $this->hasher()->HashPassword('CorrectHorse123!');
        $ids = $this->insertTestUser('no-match@test.example');

        $result = \verify_and_migrate_legacy_password(
            self::$conn, 'WrongPassword!', $hash, self::$pfx.'users', 'password', $ids['userId']
        );

        $this->assertFalse($result);
        $rows = $this->select('users', "id = {$ids['userId']}");
        $this->assertSame($hash, $rows[0]['password']);
    }
}
```

- [ ] **Step 2: Run — verify it fails**

```bash
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Integration --filter VerifyAndMigrateLegacyPasswordTest
```

Expected: `Error: Call to undefined function verify_and_migrate_legacy_password()`.

- [ ] **Step 3: Implement in `lib/common.lib.php`**

```php
/**
 * Verifies a password against a stored phpass hash, transparently migrating
 * off the legacy md5-pre-hash scheme (P1-SEC-001). Existing hashes were
 * created as HashPassword(md5($plaintext)); new hashes are
 * HashPassword($plaintext) directly. On a legacy-scheme match, rehashes and
 * persists the current-scheme hash so the account never needs the fallback
 * again.
 *
 * $updateTable/$updateColumn are interpolated as SQL identifiers — callers
 * must pass literal constants, never user input.
 */
function verify_and_migrate_legacy_password(
    mysqli $connection,
    string $plaintext,
    string $storedHash,
    string $updateTable,
    string $updateColumn,
    int $updateId
): bool {
    require_once(CLASSES.'phpass/PasswordHash.php');
    $hasher = new PasswordHash(8, false);

    if ($hasher->CheckPassword($plaintext, $storedHash)) return true;

    if ($hasher->CheckPassword(md5($plaintext), $storedHash)) {
        $newHash = $hasher->HashPassword($plaintext);
        $stmt = mysqli_prepare($connection, "UPDATE {$updateTable} SET {$updateColumn} = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $newHash, $updateId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return true;
    }

    return false;
}
```

- [ ] **Step 4: Run — verify it passes**

```bash
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Integration --filter VerifyAndMigrateLegacyPasswordTest
```

Expected: `OK (4 tests...)`.

- [ ] **Step 5: Wire into `includes/logincheck.inc.php`**

Remove the line `$entered_password = md5($entered_password);` entirely (it should still be present if you stopped at the end of Task 1; if not, this step is a no-op). Replace:

```php
if ($totalRows_login > 0) $check = $hasher->CheckPassword($entered_password, $stored_hash);
```

with:

```php
if ($totalRows_login > 0) {
    $check = verify_and_migrate_legacy_password(
        $connection, $entered_password, $stored_hash, $prefix.'users', 'password', (int)$row_login['id']
    ) ? 1 : 0;
}
```

The `if (strlen($entered_password) > 72) { ... }` guard above stays exactly as-is — phpass/bcrypt still truncates at 72 bytes and the raw plaintext now reaches it directly (previously the md5 pre-hash masked this; the guard was already independent of it). Remove the now-unused `$hasher = new PasswordHash(8, false);` line and its `require(CLASSES.'phpass/PasswordHash.php');` if nothing else in the file references `$hasher`.

- [ ] **Step 6: Fix the self-service password-change verify + drop md5 from creation sites in `includes/process/process_users.inc.php`**

Around the `$go == "password"` block, replace:

```php
$password_old = md5(sterilize($_POST['passwordOld']));
$password_new = md5(sterilize($_POST['password']));

$query_userPass = sprintf("SELECT password FROM $users_db_table WHERE id = '%s'",$id);
$userPass = mysqli_query($connection,$query_userPass) or die (mysqli_error($connection));
$row_userPass = mysqli_fetch_assoc($userPass);

$check = $hasher->CheckPassword($password_old, $row_userPass['password']);
$hash_new = $hasher->HashPassword($password_new);
```

with:

```php
$password_old = sterilize($_POST['passwordOld']);
$password_new = sterilize($_POST['password']);

$query_userPass = sprintf("SELECT password FROM $users_db_table WHERE id = '%s'",$id);
$userPass = mysqli_query($connection,$query_userPass) or die (mysqli_error($connection));
$row_userPass = mysqli_fetch_assoc($userPass);

$check = verify_and_migrate_legacy_password($connection, $password_old, $row_userPass['password'], $prefix.'users', 'password', (int)$id);
$hash_new = $hasher->HashPassword($password_new);
```

(`$check` is used later as `if (!$check)` / `if ($check)` — both a bool and the prior `int` 0/1 satisfy those checks identically, no further change needed there.)

Then remove `md5(...)` wrapping in the two pure-creation sites in the same file:
- Line ~51 (admin creating a new user): `$entered_password = md5($_POST['password']);` → `$entered_password = $_POST['password'];`
- Line ~350 (`$go == "change_user_password"`, admin setting a password with no old-password check): `$password_new = md5(sterilize($_POST['password']));` → `$password_new = sterilize($_POST['password']);`

- [ ] **Step 7: Drop md5 from the remaining pure-creation sites**

In each file, remove only the `md5(...)` wrapper, keeping any existing `sterilize()` call:

- `includes/process/process_users_register.inc.php:137`: `$entered_password = md5($_POST['password']);` → `$entered_password = $_POST['password'];`
- `includes/process/process_users_setup.inc.php:27`: `$entered_password = md5($_POST['password']);` → `$entered_password = $_POST['password'];`
- `includes/process/process_forgot_password.inc.php:33`: `$entered_password = md5(sterilize($_POST['newPassword1']));` → `$entered_password = sterilize($_POST['newPassword1']);`
- `includes/process/process_comp_info.inc.php:125` and `:233`: `$entered_password = md5(sterilize($_POST['contestCheckInPassword']));` → `$entered_password = sterilize($_POST['contestCheckInPassword']);` (both occurrences)

- [ ] **Step 8: Fix the qr.php check-in password verify**

In `qr.php`, remove:

```php
mysqli_real_escape_string($connection,$password);
$password = md5($password);
```

and replace:

```php
$check = 0;
$check = $hasher->CheckPassword($password, $stored_hash);
```

with:

```php
$check = verify_and_migrate_legacy_password($connection, $password, $stored_hash, $prefix.'contest_info', 'contestCheckInPassword', 1) ? 1 : 0;
```

(The `id='1'` in the surrounding `SELECT` is a hardcoded singleton row, not user input — out of scope for the SQLi fix, which Phase 1 deliberately limits to the unauthenticated login path per the design spec.)

- [ ] **Step 9: Verify no unintended md5 call sites remain**

```bash
grep -rn "md5(" includes/ | grep -v "\.md5("
```

Expected remaining matches, both unrelated to passwords (leave untouched): `includes/process/process_archive.inc.php:421-422` (session-prefix hashing) and `includes/process/process_contacts.inc.php:40` (rate-limit temp filename). No other `md5(` should remain in `includes/`.

Also confirm the two security-question `CheckPassword` call sites were never in scope (no md5 involved):

```bash
grep -n "CheckPassword" includes/process/process_brewer.inc.php ajax/account_checks.ajax.php
```

- [ ] **Step 10: Run static + full PHPUnit**

```bash
docker compose exec web vendor/bin/phpstan analyse
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit
```

Expected: PHPStan no new errors; PHPUnit `OK`.

- [ ] **Step 11: Add the round-trip e2e regression test**

Append to `e2e/tests/security-invariants.spec.ts` (add `login` to the import: `import { ADMIN, login, loginAsAdmin, registerEntrant } from '../helpers/auth';`):

```ts
test('a freshly registered entrant can log out and log back in (new-scheme hash round-trips)', async ({ page }) => {
  const { email, password } = await registerEntrant(page);
  await page.locator('a[href*="logout"], a:has-text("Log Out")').first().click();
  await login(page, email, password);
});
```

- [ ] **Step 12: Run the full Playwright suite (proves the seeded admin's legacy hash still logs in, twice for idempotency)**

```bash
cd e2e && npx playwright test
cd e2e && npx playwright test
```

Expected: all specs pass both times, including the existing "seeded admin can log in" smoke test — this IS the legacy-migration regression test, since the admin's stored hash predates this fix (see Global Constraints).

- [ ] **Step 13: Commit**

```bash
git add lib/common.lib.php includes/logincheck.inc.php includes/process/process_users.inc.php \
  includes/process/process_users_register.inc.php includes/process/process_users_setup.inc.php \
  includes/process/process_forgot_password.inc.php includes/process/process_comp_info.inc.php \
  qr.php tests/Integration/VerifyAndMigrateLegacyPasswordTest.php e2e/tests/security-invariants.spec.ts
git commit -m "Remove MD5 password pre-hashing with transparent legacy-hash migration (P1-SEC-001)"
```

---

### Task 3: Fix `handle.php` PDF path traversal (P1-SEC-005)

**Files:**
- Modify: `lib/common.lib.php` (add `safe_document_id()`)
- Modify: `handle.php`
- Modify: `tests/Unit/SecurityAndCryptoTest.php` (new test group)
- Modify: `e2e/tests/security-invariants.spec.ts` (flip the existing `test.fixme`)

**Interfaces:**
- Produces: `safe_document_id(?string $id): ?string` — returns `$id` unchanged if it's a bare filename component (no directory separators, no traversal, no null bytes, non-empty); otherwise `null`.

- [ ] **Step 1: Write the failing Unit tests**

Append to `tests/Unit/SecurityAndCryptoTest.php` (inside the class):

```php
    // ── safe_document_id() ────────────────────────────────────

    public function test_safe_document_id_accepts_plain_numeric_id(): void
    {
        $this->assertSame('42', safe_document_id('42'));
    }

    public function test_safe_document_id_rejects_parent_directory_traversal(): void
    {
        $this->assertNull(safe_document_id('../../../../etc/passwd'));
    }

    public function test_safe_document_id_rejects_absolute_path(): void
    {
        $this->assertNull(safe_document_id('/etc/passwd'));
    }

    public function test_safe_document_id_rejects_backslash_traversal(): void
    {
        $this->assertNull(safe_document_id('..\\..\\windows\\win.ini'));
    }

    public function test_safe_document_id_rejects_null_byte(): void
    {
        $this->assertNull(safe_document_id("42\0.jpg"));
    }

    public function test_safe_document_id_rejects_empty_string(): void
    {
        $this->assertNull(safe_document_id(''));
    }

    public function test_safe_document_id_rejects_null(): void
    {
        $this->assertNull(safe_document_id(null));
    }
```

- [ ] **Step 2: Run — verify it fails**

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit --filter safe_document_id
```

Expected: `Error: Call to undefined function safe_document_id()`.

- [ ] **Step 3: Implement in `lib/common.lib.php`**

```php
/**
 * Reduces a user-supplied document id to a bare filename component, rejecting
 * path traversal and directory separators (P1-SEC-005). Returns null for
 * anything but a plain basename.
 */
function safe_document_id(?string $id): ?string {
    if ($id === null || $id === '' || strpos($id, "\0") !== false) return null;
    if (basename($id) !== $id) return null;
    return $id;
}
```

- [ ] **Step 4: Run — verify it passes**

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit --filter safe_document_id
```

Expected: `OK (7 tests...)`.

- [ ] **Step 5: Wire into `handle.php`**

Replace:

```php
if ((isset($_SESSION['loginUsername'])) && ($section == "pdf-download")) {
	header("Content-disposition: attachment; filename=$id.pdf");
	header("Content-type: application/pdf");
	readfile(USER_DOCS."$id.pdf");
}
```

with:

```php
if ((isset($_SESSION['loginUsername'])) && ($section == "pdf-download")) {
	$safe_id = safe_document_id($id);
	if ($safe_id === null) {
		http_response_code(400);
		exit;
	}
	header("Content-disposition: attachment; filename=$safe_id.pdf");
	header("Content-type: application/pdf");
	readfile(USER_DOCS."$safe_id.pdf");
}
```

- [ ] **Step 6: Flip the e2e fixme**

In `e2e/tests/security-invariants.spec.ts`, change:

```ts
// P1-SEC-005: for a LOGGED-IN user, handle.php builds
// readfile(USER_DOCS."$id.pdf") from the raw id; sterilize() does not strip
// "../". Secure behavior: reject ids containing path separators. Currently the
// endpoint attempts the traversed read instead of rejecting it.
// fixme until Phase 1 hardens handle.php.
test.fixme('pdf download rejects path traversal for a logged-in user', async ({ page }) => {
```

to:

```ts
// P1-SEC-005: fixed — safe_document_id() rejects anything but a bare
// basename before it reaches readfile(USER_DOCS."$id.pdf").
test('pdf download rejects path traversal for a logged-in user', async ({ page }) => {
```

- [ ] **Step 7: Run until green twice**

```bash
cd e2e && npx playwright test tests/security-invariants.spec.ts
cd e2e && npx playwright test tests/security-invariants.spec.ts
```

Expected: all pass both runs, including the now-unmarked test.

- [ ] **Step 8: Commit**

```bash
git add lib/common.lib.php handle.php tests/Unit/SecurityAndCryptoTest.php e2e/tests/security-invariants.spec.ts
git commit -m "Fix handle.php path traversal on PDF download (P1-SEC-005)"
```

---

### Task 4: Session regeneration on login (P1-SEC-006)

**Files:**
- Modify: `includes/logincheck.inc.php`
- Modify: `e2e/tests/security-invariants.spec.ts`

**Interfaces:**
- None new — this is a one-line addition guarded by existing e2e login coverage.

- [ ] **Step 1: Add the regeneration call**

In `includes/logincheck.inc.php`, inside `if ($check == 1) { ... }`, as the very first line of the block (before the two `UPDATE`s from Task 1 and before `$_SESSION['loginUsername'] = $loginUsername;`):

```php
if ($check == 1) {

	// Regenerate the session id on privilege elevation to prevent session
	// fixation (P1-SEC-006) — must run before any session data is trusted.
	session_regenerate_id(true);

	$stmt = mysqli_prepare($connection, "UPDATE {$prefix}users SET user_name = ? WHERE id = ?");
	...
```

- [ ] **Step 2: Add the e2e assertion**

Append to `e2e/tests/security-invariants.spec.ts`:

```ts
test('login regenerates the session id (prevents fixation)', async ({ page }) => {
  await page.goto('/index.php');
  const cookiesBefore = await page.context().cookies();
  // The app names its session cookie md5(installation_id) — deterministic
  // per install but not hardcoded here; there is exactly one cookie for a
  // fresh anonymous context.
  const sessionCookieName = cookiesBefore[0]?.name;
  expect(sessionCookieName).toBeTruthy();
  const before = cookiesBefore[0]?.value;

  await loginAsAdmin(page);

  const cookiesAfter = await page.context().cookies();
  const after = cookiesAfter.find(c => c.name === sessionCookieName)?.value;
  expect(after).toBeDefined();
  expect(after).not.toBe(before);
});
```

- [ ] **Step 3: Run until green twice**

```bash
cd e2e && npx playwright test tests/security-invariants.spec.ts
cd e2e && npx playwright test tests/security-invariants.spec.ts
```

If a fresh context carries more than one cookie before login (e.g. a consent cookie), filter `cookiesBefore` to the one whose name isn't a known non-session cookie before picking `sessionCookieName` — inspect with `--headed`/`--debug` if the assertion is flaky.

- [ ] **Step 4: Commit**

```bash
git add includes/logincheck.inc.php e2e/tests/security-invariants.spec.ts
git commit -m "Regenerate session id on login to prevent fixation (P1-SEC-006)"
```

---

### Task 5: Full verification, CI, and docs/memory update

**Files:**
- None expected to change except documentation.

- [ ] **Step 1: Full local verification**

```bash
docker compose exec web vendor/bin/phpstan analyse
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit
cd e2e && npx playwright test
cd e2e && npx playwright test   # idempotency check
```

Expected: PHPStan clean, PHPUnit `OK` (~336+ tests, a few new ones from Tasks 1–3), Playwright all green (no `fixme` left for P1-SEC-005) both runs.

- [ ] **Step 2: Push and verify CI**

```bash
git push origin docker-baseline-db
gh run watch --exit-status || gh run view --log-failed
```

Expected: all three CI jobs (`static-and-unit`, `db-tests`, `e2e`) green.

- [ ] **Step 3: Update the runbook**

Append to `Docs/docker-loadtest-runbook.md` under "Test suites (added Phase 0)":

```markdown
### Phase 1 additions

- Login SQLi + critical `section=update` auth-bypass: fixed via
  `find_user_by_username()` (lib/common.lib.php).
- MD5 password pre-hashing: removed, with transparent legacy-hash migration
  via `verify_and_migrate_legacy_password()`. Existing accounts (including the
  seeded admin) keep working; their hash is upgraded on next successful login.
- `handle.php` path traversal: fixed via `safe_document_id()`.
- Session fixation: `session_regenerate_id(true)` on successful login.
- All four are acceptance-tested in `e2e/tests/security-invariants.spec.ts`
  (no `fixme` markers remain).
```

```bash
git add Docs/docker-loadtest-runbook.md
git commit -m "Document Phase 1 security fixes in the test runbook"
```

- [ ] **Step 4: Update project memory** (not a repo file — the assistant's memory store)

Mark Phase 1 done in `project-modernization`, record the critical auth-bypass finding and its fix, and update `project-security-findings` to move P1-SEC-001/002/005/006 from "open" to "fixed", noting the newly-found bypass separately since it predates and is broader than the original review's scope.

---

## Self-review notes (already applied)

- **Spec coverage:** design spec Phase 1 row → Login SQLi (Task 1, plus the more severe bypass it was found alongside), MD5 pre-hash (Task 2), `handle.php` traversal (Task 3), session regeneration (Task 4), security-invariant e2e acceptance tests (all four tasks, plus Task 5's full-suite gate). The design spec scopes the SQLi fix to the *unauthenticated* login path only — `qr.php`'s non-login queries and the other ~510 no-op-escape call sites catalogued in the SQLi audit remain explicitly out of scope, to be addressed per-workflow during Phase 3 extraction (`Connection` class, prepared-statements-only).
- **Known judgment calls:** (a) the `section=update` bypass was not in any prior review; fixing the SQLi and the bypass in one task was unavoidable since both live in the same duplicated-branch code that Task 1 unifies — flagged prominently rather than buried in a routine SQLi step. (b) `verify_and_migrate_legacy_password()` takes table/column as literal-only parameters (documented constraint) rather than a fully generic query builder, since only 3 call sites exist and over-genericizing risks a future caller passing untrusted identifiers. (c) qr.php's check-in-password verify shares the same migration helper even though it wasn't in the original security-findings memory — found live in current code during planning, included since it's the same vulnerability class.
- **Type consistency:** `find_user_by_username()` and `verify_and_migrate_legacy_password()` signatures are used identically across Tasks 1–2's call sites (`logincheck.inc.php`, `process_users.inc.php`, `qr.php`). `safe_document_id()` is unit-tested and wired with matching null-check semantics in Task 3.

---

## Appendix: Phase 2 & 3 outline (high-level — not yet broken into bite-sized tasks)

Per the design spec's phase table, these come after Phase 1 and are **the upstream-divergence point** — do not start until Phase 1's fixes are merged and the security-invariant suite is fully green with no `fixme`s. Each will get its own detailed plan document (like this one) when work is ready to start; the breakdown below is a roadmap, not an execution-ready task list.

### Phase 2 — Slim shell + central authorization (divergence point)

Rough task groups, in dependency order:
1. **Scaffold** `src/Kernel/` (PHP-DI container, Slim app, middleware pipeline registration) and a `composer.json` PSR-4 entry for `Bcoem\`. `index.php` shrinks to the ~10-line bootstrap described in Section 2 of the design spec.
2. **`src/Legacy/`** — `LegacyBootstrap` (runs `paths.php`'s one-time setup once per request instead of once per side door), `LegacyPageHandler` (output-buffers the `index.php?section=X` GET flow), `LegacyProcessHandler` (output-buffers `process.inc.php` POSTs). Prove equivalence by running the full Phase 0/1 Playwright suite against the bridge and requiring byte-for-byte-equivalent behavior (the design spec's explicit Phase 2 gate).
3. **`src/Security/`** — `Identity` (reads legacy `$_SESSION` into a typed object), `Role` enum mapping `userLevel`, `AccessPolicy` loader.
4. **`config/access_policy.php`** — the deny-by-default policy map (Section 1 of the spec has a starter shape). Build it by enumerating every legacy `section`/`go`/`action` combination and every side door (`handle.php`, `qr.php`, `ajax/*.php`, `setup.php`, `update.php`, `awards.php`, `ppv.php`) — this enumeration is itself a task, since "missing a route" fails closed but a forgotten *side door* currently fails open.
5. **`AuthorizationMiddleware`** enforcing the policy map ahead of both legacy and modern routes; **`AuditMiddleware`** stub (full audit-log writes arrive with Phase 3's write-path contract).
6. **Error handling**: `mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT)` kernel line (already effectively true under PHP 8.2's default, but make it explicit and intentional), `ErrorMiddleware` driven by `APP_DEBUG`, Monolog channels (`app`/`security`/`legacy`), retire `or die(mysqli_error())` (13 remaining instances) globally via the exception model instead of touching each call site.
7. **OpenTelemetry** — `TracingMiddleware`, `Connection` DB spans, Docker Compose `otel-collector` + Jaeger/Grafana, no-op bindings for shared hosting.
8. **Deployment pipeline** groundwork — Phinx (first migration: `audit_log` table + indexes), env-var config shim in `site/config.php`, CI build/package steps for the two artifacts (Docker image + zip fallback).

Acceptance for Phase 2 as a whole: Playwright suite passes identically before/after the shell lands; PHPStan gains its two new custom rules (no `mysqli_*` outside `Connection`; nothing outside `src/Legacy/` references legacy globals) and passes with zero suppressions for new code.

### Phase 3 — Hotspot-ordered service extraction

Migration order follows the hotspot ranking already computed (`Docs/hotspot-analysis.html`): `lib/common.lib.php` (0.99) is dismantled opportunistically as its callers migrate, not extracted wholesale up front. Concrete first workflows, in order:

1. **Entries/brewing** (`sections/brew.sec.php`, 0.58) → `src/Domain/Entry/` (`EntryService`, `EntryRepository`, `CreateEntryCommand`). This is the flagship migration described in Section 3 of the design spec — `POST /entries` behind `AuthorizationMiddleware`, `Validator::validate()`, `AuditLogger::record()` in the same transaction. Its Phase 0 e2e journey (`entrant-journey.spec.ts`) is the regression gate.
2. **Admin defaults/preferences** (`admin/default.admin.php` 0.89, `admin/site_preferences.admin.php` 0.68).
3. **Admin entries + judging tables** (`admin/entries.admin.php` 0.57, `admin/judging_tables.admin.php` 0.49) — gated by `admin-journey.spec.ts`.
4. **Registration** (`sections/register.sec.php`, 0.50) — this is also where the remaining ~510 no-op `mysqli_real_escape_string()` call sites in registration-adjacent code finally move onto the `Connection` prepared-statement-only API, file by file, as each workflow is touched (per Section 2's contract — no big-bang SQLi remediation sweep).
5. **Export** (`output/export.output.php`, 0.48).

Each migrated workflow's definition of done (per Section 5 of the design spec): service unit tests (in-memory fakes) + repository integration tests (real MariaDB) + its existing e2e journey still green + PHPStan clean. `common.lib.php` shrinks as each workflow's helper functions get absorbed into the corresponding `src/Domain/*` service or are deleted as dead legacy code once nothing calls them.
