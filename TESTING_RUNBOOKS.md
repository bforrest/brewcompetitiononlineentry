# Testing Runbooks

Everything here assumes you are in the repo root of the `testing-baseline` branch
(or a worktree of it). The app is the stock upstream BCOE&M code; the tests
characterize `lib/` and drive the real UI.

## One-Time Setup

```bash
# 1. Dev dependencies (PHPUnit, PHPStan). Needs PHP 8.2+ on the host.
#    No local PHP? Use:
#    docker run --rm -v "$PWD":/app -w /app composer:2 install --ignore-platform-reqs
composer install

# 2. App config. site/config.php is gitignored; the Docker stack supplies the
#    DB settings through environment variables (see docker-compose.yml).
cp site/config.sample.php site/config.php

# 3. E2E dependencies and browser
cd e2e && npm ci && npx playwright install chromium && cd ..

# 4. Start the stack: app on :8080, MariaDB on :3306 (seeded from
#    sql/bcoem_baseline_3.2.X.sql on first start)
docker compose up -d --build
```

- phpMyAdmin is not part of this stack. Use `mysql -h 127.0.0.1 -u bcoem -pbcoem_password bcoem`.
- The seeded admin login is `user.baseline@brewingcompetitions.com` / `bcoem`.

## Quick Start: Run Everything

Order matters. PHPUnit goes first because the Playwright suite leaves users and
entries behind, and some Integration tests (e.g. `TotalFeesTest`) assume the
freshly seeded database. See [Database state](#database-state-and-run-order).

```bash
# Static analysis + DB-free tests (host)
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/phpunit --testsuite Unit

# DB-backed tests (inside the web container)
docker compose exec -T -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Integration
docker compose exec -T -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Approval

# Reset the DB, then run the browser suite
docker compose down -v && docker compose up -d
until curl -sf http://localhost:8080/index.php > /dev/null; do sleep 2; done
(cd e2e && npm test)
```

**Expected result (as of the commit that introduced this branch):**

| Check | Result |
|---|---|
| PHPStan | `[OK] No errors` |
| Unit | 242 tests, 5 skipped |
| Integration | 57 tests |
| Approval | 47 tests, 1 skipped |
| Playwright | 18 passed |

PHPUnit prints "OK, but there were issues!" because the legacy library triggers
known PHP warnings and deprecations; `failOnWarning` is intentionally off (see
`phpunit.xml`). Only failures and errors matter.

`composer` shortcuts exist for the same commands: `composer stan`,
`composer test:unit`, `composer test:db`, `composer e2e`, `composer db:reset`.

---

## Database State and Run Order

- Unit tests need no database.
- Integration and Approval tests use the seeded `baseline_` tables. Integration
  tests run inside a transaction that is rolled back, so they do not dirty the DB,
  but they do read whatever is already there.
- Playwright registers users and creates entries, and **does not clean up**.
  Afterwards, tests that aggregate over all brewers fail (for example
  `TotalFeesTest::testAllBrewersSumAcrossMultipleUsers`).

Reset to the seeded state whenever you have run e2e and want to run PHPUnit again:

```bash
docker compose down -v && docker compose up -d   # same as `composer db:reset`
```

`down -v` deletes the database volume. That is intended here; the data is a
disposable fixture.

---

## Test Tiers: Individual Runbooks

### Tier 1: Unit Tests (seconds, no Docker)

**Best for:** quick feedback while editing `lib/`.

```bash
vendor/bin/phpunit --testsuite Unit
```

```bash
# One file
vendor/bin/phpunit tests/Unit/StringUtilitiesTest.php

# One test method
vendor/bin/phpunit --filter test_style_number_const_method3_default_ltrims_category
```

Use `--testdox` for a readable list of test names.

**Common failures**
- `Undefined array key ...` / `Call to undefined function`: the function reads a
  `$_SESSION` key or a helper that `tests/bootstrap.php` does not define. Set the
  session key in the test (and `unset` it afterwards), or add a stub to the bootstrap.
- Assertion mismatch after pulling upstream: upstream changed behavior. Decide
  whether the new behavior is right, then update the test and say so in the
  commit message (see "Keeping tests in step with upstream" below).

### Tier 2: Integration Tests (about a minute, needs the DB)

**Best for:** functions in `lib/` that run real queries through `MysqliDb`.

```bash
# Inside the container (recommended): it already has the DB settings
docker compose exec -T -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Integration

# One file
docker compose exec -T -e BCOEM_DB_HOST=db web vendor/bin/phpunit tests/Integration/VerifyTokenTest.php
```

To run on the host instead, point at the published port:

```bash
export BCOEM_DB_HOST=127.0.0.1 BCOEM_DB_USER=bcoem BCOEM_DB_PASSWORD=bcoem_password \
       BCOEM_DB_NAME=bcoem BCOEM_DB_PORT=3306 BCOEM_DB_PREFIX=baseline_
vendor/bin/phpunit --testsuite Integration
```

The host needs PHP with the `mysqli` extension. The values above are also the
defaults in `tests/Integration/IntegrationTestCase.php`, so on the host you can
usually skip the exports. `BCOEM_DB_HOST=db` is needed only inside the container,
where the database is reachable by its compose service name.

**Common failures**
- `Connection refused` / `Unknown MySQL server host`: the stack is not up, or you are
  using `db` from the host (or `127.0.0.1` from inside the container). Check
  `docker compose ps` and `docker compose logs db`.
- `Class "MysqliDb" not found`: `tests/bootstrap.php` must `require_once CONFIG . 'MysqliDb.php'`.
- Sum/count assertions off by a lot: leftover e2e data. Reset the DB (above).
- Tests errored because tables are missing: the volume was created before the
  baseline SQL changed. `docker compose down -v && docker compose up -d`.

### Tier 3: Approval (snapshot) Tests (seconds, needs the DB)

**Best for:** detecting accidental changes in generated HTML/URLs/style output.

```bash
docker compose exec -T -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Approval
```

Snapshots live in `tests/Approval/__snapshots__/`. A missing snapshot is created
on first run. A mismatch fails the test and prints the diff.

If the change is intentional, regenerate and review:

```bash
docker compose exec -T -e BCOEM_DB_HOST=db -e UPDATE_SNAPSHOTS=1 web \
  vendor/bin/phpunit --testsuite Approval

git diff tests/Approval/__snapshots__/     # read it before committing
git add tests/Approval/__snapshots__/
```

`UPDATE_SNAPSHOTS=1` overwrites every snapshot exercised in the run, so always
review the diff.

### Tier 4: E2E Tests (about a minute, needs the full stack)

**Best for:** the browser happy paths and access-control checks.

| Spec | Covers |
|---|---|
| `smoke.spec.ts` | home page, login modal, admin login, entrant registration |
| `entrant-journey.spec.ts` | register, create/edit an entry, payment page |
| `admin-journey.spec.ts` | admin sees entries, builds a judging table, records a score |
| `security-invariants.spec.ts` | anonymous/entrant access limits, PDF path traversal, login behavior |

```bash
docker compose down -v && docker compose up -d          # clean DB (see above)
until curl -sf http://localhost:8080/index.php > /dev/null; do sleep 2; done

cd e2e
npm test                                  # headless
npm run test:headed                       # watch the browser
npx playwright test smoke.spec.ts         # one file
npx playwright test -g "fresh entrant"    # one test by name
npx playwright test --debug               # step through
npm run report                            # open the HTML report
```

The specs run serially against one shared database (`workers: 1`). The admin
credentials are hard-coded in `e2e/helpers/auth.ts`, and the base URL defaults to
`http://localhost:8080` (override with `BASE_URL`). Registration only works
because `docker/03-e2e-fixtures.sql` pins `prefsCAPTCHA = 0`.

**Common failures**
- Login or registration times out and you land back on the login page: a request is
  not reaching its PHP script. `docker/.htaccess` is bind-mounted over the root
  `.htaccess` and must stay identical to upstream's apart from the HTTPS redirect.
- `net::ERR_CONNECTION_REFUSED`: stack not up yet; wait for the `curl` loop.
- A test passes alone but fails in the full run: it depends on state from an earlier
  spec or leftover data. Reset the DB and re-run the whole suite.

---

## Scenario: Adding a New Test

### Characterization test for a `lib/` function (Unit)

```php
<?php
// tests/Unit/MyFunctionTest.php
declare(strict_types=1);

namespace BCOEM\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MyFunctionTest extends TestCase
{
    public function test_pins_current_behavior(): void
    {
        // tests/bootstrap.php has already loaded lib/common.lib.php
        $this->assertSame('expected', some_lib_function('input'));
    }
}
```

```bash
vendor/bin/phpunit tests/Unit/MyFunctionTest.php
```

Write the assertion for what the code does **today**, even if that looks wrong,
and leave a comment saying so. The point of a characterization test is to make a
later change show up as a diff.

### Integration test (needs the DB)

Extend `IntegrationTestCase` (`tests/Integration/IntegrationTestCase.php`). It opens
a transaction in `setUp` and rolls it back afterwards, and provides helpers such as
`insertTestUser()`, `insert()` and `select()`.

```bash
docker compose exec -T -e BCOEM_DB_HOST=db web vendor/bin/phpunit tests/Integration/MyNewIntegrationTest.php
```

Functions that take a database wrapper want a `MysqliDb`, not the raw `mysqli`
handle: pass `new \MysqliDb(self::$conn)`.

### E2E test

```typescript
// e2e/tests/my-journey.spec.ts
import { test, expect } from '@playwright/test';
import { registerEntrant } from '../helpers/auth';

test('entrant sees the entries page', async ({ page }) => {
  await registerEntrant(page);
  await page.goto('/index.php?section=list');
  await expect(page.locator('body')).toContainText(/entries/i);
});
```

```bash
cd e2e && npx playwright test my-journey.spec.ts
```

Helpers: `registerEntrant`, `login`, `loginAsAdmin` in `e2e/helpers/auth.ts`;
`chooseStyle`, `createEntrantWithEntry` in `e2e/helpers/entries.ts`.

---

## Scenario: Debugging a Failing Test

### Unit / Integration / Approval

```bash
# Run the one failing test with readable output
docker compose exec -T -e BCOEM_DB_HOST=db web \
  vendor/bin/phpunit tests/Integration/BrewerInfoTest.php --testdox

# Inspect the database directly
mysql -h 127.0.0.1 -u bcoem -pbcoem_password bcoem \
  -e "SELECT id, user_name, userLevel FROM baseline_users ORDER BY id DESC LIMIT 5;"

# PHP errors from the web container
docker compose logs web --since 5m | grep -E "php:(error|warn)"
```

To see exactly what the SQL layer received, add a temporary `var_dump` in the test
or the `lib/` function and re-run; remove it afterwards.

### E2E

```bash
cd e2e
npm run test:headed                         # watch it fail
npx playwright show-trace test-results/<test-folder>/trace.zip
docker compose logs web --since 5m | tail -50
```

Failed runs keep a screenshot, trace and `error-context.md` under
`e2e/test-results/` (gitignored).

---

## Keeping Tests in Step with Upstream

This branch is `upstream/master` plus the test layers, so pulling upstream is the
normal way to update it:

```bash
git fetch upstream
git merge upstream/master          # or rebase
```

Then, with a fresh DB, run the whole suite. A failure after a merge means one of:

1. **A deliberate upstream behavior change.** Confirm the new behavior is
   acceptable, update the test, and note it in the commit message. Past examples:
   `style_number_const` switched to a `style_set_no_numbering` session flag; the
   currency list grew to 29; `upgrade_legacy_password_hash()` takes a `MysqliDb`.
2. **A regression.** Report it upstream rather than editing the test to match.
   One known case: `verify_token()` now uses `getOne()`, so two accounts sharing a
   reset token both validate (`VerifyTokenTest::testDuplicateTokenReturnsOne`
   pins this).
3. **New code PHPStan cannot resolve.** Fix the cause, or regenerate the baseline
   and review the diff:
   `vendor/bin/phpstan analyse --memory-limit=1G --generate-baseline=phpstan-baseline.neon`

If upstream changes `.htaccess`, mirror the change into `docker/.htaccess`
(keeping the HTTPS redirect commented out).

---

## CI

`.github/workflows/ci.yml` runs on pushes to `testing-baseline` and `master`, and on
every pull request, in two jobs:

| Job | Steps |
|---|---|
| `static-and-unit` | `composer install`, PHPStan, Unit tier |
| `integration-and-e2e` | copy `site/config.php`, start the stack, Integration and Approval tiers in the container, Playwright from the host, uploads the Playwright report on failure |

To reproduce a CI failure, follow the matching tier above from a fresh `down -v`.
CI always starts from a clean database, so a failure that only appears locally
after e2e is the run-order issue, not a code problem.

---

## Resources

- **Overview:** `TESTING.md`, `TESTING_HEALTH_DASHBOARD.md`
- **Test code:** `tests/`, `e2e/tests/`, `e2e/helpers/`
- **Per-tier notes:** `tests/README.md`, `tests/Integration/README.md`, `e2e/README.md`
- **CI workflow:** `.github/workflows/ci.yml`
- **Docker:** `docker-compose.yml`, `Dockerfile`, `docker/`
- **Configs:** `phpunit.xml`, `phpstan.neon`, `e2e/playwright.config.ts`
