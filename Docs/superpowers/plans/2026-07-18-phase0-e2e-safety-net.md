# Phase 0 — E2E Safety Net Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the test safety net that everything later refactoring hides behind: adopt the existing 3-tier characterization suite, add a Playwright e2e suite covering the critical entrant and admin journeys plus security invariants, and wire all of it into GitHub Actions CI.

**Architecture:** No production-code changes except (a) the MyISAM→InnoDB baseline-schema conversion, (b) porting 9 already-reviewed legacy bug fixes the characterization tests pin, and (c) additive `data-test` attributes in legacy markup. Tests run against the existing Docker stack (`php:8.2-apache` + `mariadb:11`, auto-seeded baseline DB). Spec: `Docs/superpowers/specs/2026-07-18-modernization-slim-strangler-design.md` (Section 5).

**Tech Stack:** PHPUnit ^10 (PHP 8.2 inside the `web` container), Playwright `@playwright/test` (Node 20+, Chromium), GitHub Actions, MariaDB 11.

## Global Constraints

- App URL: `http://localhost:8080` (Docker compose stack, service `web`); DB on `localhost:3306` from host, host `db` from inside containers.
- DB credentials: db `bcoem`, user `bcoem`, password `bcoem_password`, root password `root_password`. Table prefix: `baseline_`.
- Seeded admin login (userLevel 0): `user.baseline@brewingcompetitions.com` / `bcoem`.
- Legacy PHP files may ONLY be edited to add `data-test="..."` attributes (additive, upstream-merge-safe). Exception: `lib/common.lib.php` receives the 9 ported bug fixes in Task 2. No security fixes in Phase 0 — security-invariant tests that fail against current code are annotated `fixme` and flip green in Phase 1.
- `vendor/` is committed to git on this branch — after any composer change, `git add vendor composer.json composer.lock`.
- All phpunit runs happen INSIDE the web container (`docker compose exec web ...`) so PHP version/extensions are guaranteed; Playwright runs on the host.
- Reseeding the DB (needed after schema/fixture changes): `docker compose down -v && docker compose up -d --build`, then wait for health: `docker compose ps` shows `db` healthy.
- Playwright specs live in `e2e/`; PHPUnit suites in `tests/` (`Unit`, `Integration`, `Approval`).

---

### Task 1: Adopt the characterization test suite

**Files:**
- Create: `tests/` (entire tree), `phpunit.xml` — checked out from `origin/characterization-tests`
- Modify: `composer.json`, `composer.lock`, `vendor/` (composer update), `.gitignore`

**Interfaces:**
- Produces: `docker compose exec web vendor/bin/phpunit --testsuite Unit` as the Tier-1 command later tasks and CI rely on; PSR-4 test namespaces `BCOEM\Tests\{Unit,Integration,Approval}`.

- [ ] **Step 1: Fetch and check out the test tree from the branch**

```bash
git fetch origin characterization-tests
git checkout origin/characterization-tests -- tests/ phpunit.xml
```

- [ ] **Step 2: Merge composer.json (keep current deps, add phpunit + autoload-dev)**

Replace `composer.json` with exactly:

```json
{
    "name": "bcoem/brewcompetitiononlineentry",
    "description": "Brew Competition Online Entry & Management",
    "type": "project",
    "require": {
        "pixel418/markdownify": "^2.3"
    },
    "require-dev": {
        "phpstan/phpstan": "^2.2",
        "phpunit/phpunit": "^10.0"
    },
    "autoload-dev": {
        "psr-4": {
            "BCOEM\\Tests\\Approval\\": "tests/Approval/",
            "BCOEM\\Tests\\Integration\\": "tests/Integration/",
            "BCOEM\\Tests\\Unit\\": "tests/Unit/"
        }
    },
    "config": {
        "sort-packages": true
    }
}
```

- [ ] **Step 3: Ignore the PHPUnit cache**

Append to `.gitignore`:

```
.phpunit.cache/
```

If `git status` shows `.phpunit.cache/` staged from Step 1, unstage it: `git restore --staged .phpunit.cache && rm -rf .phpunit.cache`.

- [ ] **Step 4: Install dependencies (inside the container for correct PHP)**

```bash
docker compose up -d --build
docker compose exec web sh -c "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer" # skip if composer already present
docker compose exec web composer update
```

Expected: `phpunit/phpunit` 10.x installed, no errors.

- [ ] **Step 5: Run the Unit tier — verify green**

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit
```

Expected: `FAILURES!` — some Unit tests WILL fail at this point, because they pin the *fixed* behavior of 9 legacy bugs whose fixes live on the other branch and arrive in Task 2. Record the failing test names; they must exactly match the 9 bug areas listed in Task 2 (in_string, search_array, build_public_url, designations, display_array_content, random_generator, display_place, readable_number). If tests fail *outside* those areas, stop and investigate before proceeding.

- [ ] **Step 6: Commit**

```bash
git add tests/ phpunit.xml composer.json composer.lock vendor .gitignore
git commit -m "Adopt 3-tier characterization test suite from characterization-tests branch"
```

---

### Task 2: Port the 9 characterization-exposed bug fixes to lib/common.lib.php

**Files:**
- Modify: `lib/common.lib.php`

**Interfaces:**
- Consumes: failing Unit tests from Task 1 Step 5 (they are the acceptance tests here).
- Produces: green Tier-1: `docker compose exec web vendor/bin/phpunit --testsuite Unit` → 0 failures.

- [ ] **Step 1: Apply the fix commit's lib changes**

```bash
git diff 09ca6308^ 09ca6308 -- lib/common.lib.php > /tmp/bugfixes.patch
git apply --3way /tmp/bugfixes.patch
```

If `git apply` conflicts (upstream 3.0.3 merges may have drifted the file), apply the 9 fixes manually — the commit message of `09ca6308` enumerates them (BUG-001…BUG-009: `in_string()` explicit `return false`; `search_array()` returns first match directly; `build_public_url()` honours `$sef` via `!empty()`; delete commented `build_admin_url()` dead code; `designations()` guards empty string; `display_array_content()` single rtrim; `random_generator()` rewritten with `random_int()`; `display_place()` drop `require(config.php)`; `readable_number()` boundary fixes at 100 and 1000). Use `git show 09ca6308 -- lib/common.lib.php` as the reference diff.

- [ ] **Step 2: Run Unit tier — verify fully green**

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit
```

Expected: `OK` — 0 failures, ~235 tests.

- [ ] **Step 3: Run PHPStan (guard against syntax/API regressions in the patched file)**

```bash
docker compose exec web vendor/bin/phpstan analyse
```

Expected: no new errors versus `git stash; docker compose exec web vendor/bin/phpstan analyse; git stash pop` baseline (level 0 over `lib/`).

- [ ] **Step 4: Commit**

```bash
git add lib/common.lib.php
git commit -m "Port 9 characterization-exposed bug fixes to common.lib.php (BUG-001..BUG-009)"
```

---

### Task 3: Convert baseline schema to InnoDB and reseed

**Files:**
- Modify: `sql/bcoem_baseline_3.0.X.sql`

**Interfaces:**
- Produces: all 24 `baseline_*` tables on InnoDB — required by `tests/Integration/IntegrationTestCase.php`, whose transaction-rollback isolation silently breaks on MyISAM.

- [ ] **Step 1: Convert engines in the seed file**

```bash
sed -i '' 's/ENGINE=MyISAM/ENGINE=InnoDB/g' sql/bcoem_baseline_3.0.X.sql
```

- [ ] **Step 2: Verify the conversion**

```bash
grep -c 'ENGINE=InnoDB' sql/bcoem_baseline_3.0.X.sql   # expected: 24
grep -c 'ENGINE=MyISAM' sql/bcoem_baseline_3.0.X.sql   # expected: 0 (grep exits 1)
```

- [ ] **Step 3: Reseed the Docker DB and verify live engines**

```bash
docker compose down -v && docker compose up -d --build
# wait until healthy:
docker compose ps
docker compose exec db mariadb -ubcoem -pbcoem_password bcoem -e \
  "SELECT COUNT(*) AS myisam_left FROM information_schema.tables WHERE table_schema='bcoem' AND engine='MyISAM';"
```

Expected: `myisam_left` = 0.

- [ ] **Step 4: Sanity-check the app still serves**

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/index.php
```

Expected: `200`.

- [ ] **Step 5: Commit**

```bash
git add sql/bcoem_baseline_3.0.X.sql
git commit -m "Convert baseline schema tables from MyISAM to InnoDB"
```

---

### Task 4: Run Integration + Approval tiers against the Docker DB

**Files:**
- None expected; this task validates Tasks 1–3 together. If individual tests fail from branch drift, fix the *test* only when it pins behavior that legitimately changed upstream since the branch was cut — record each such change in the commit message.

**Interfaces:**
- Consumes: `IntegrationTestCase` env contract — `BCOEM_DB_HOST` (use `db` inside the container), `BCOEM_DB_USER`, `BCOEM_DB_PASSWORD`, `BCOEM_DB_NAME`, `BCOEM_DB_PORT`, `BCOEM_DB_PREFIX=baseline_`.
- Produces: green Tier-2/3 commands used by CI: see Step 1/2 commands.

- [ ] **Step 1: Run Integration tier**

```bash
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Integration
```

Expected: `OK` — ~54 tests. (Defaults assume host `127.0.0.1`; inside the container the DB host is `db`.)

- [ ] **Step 2: Run Approval tier**

```bash
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Approval
```

Expected: ~47 tests, 1 intentional skip (style_type DB methods); warnings/deprecations from vendored libs are pre-existing noise catalogued in `Docs/characterization-test-findings.md` on the source branch — not failures.

- [ ] **Step 3: Run everything together**

```bash
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit
```

Expected: `OK` (~336 tests), 0 failures.

- [ ] **Step 4: Commit (only if test files needed drift fixes)**

```bash
git add tests/
git commit -m "Adjust characterization pins for post-branch upstream changes"
```

---

### Task 5: Playwright scaffold + smoke test

**Files:**
- Create: `e2e/package.json`, `e2e/playwright.config.ts`, `e2e/tests/smoke.spec.ts`
- Modify: `.gitignore`

**Interfaces:**
- Produces: `cd e2e && npx playwright test` as the e2e command; `BASE_URL` env override (default `http://localhost:8080`); `e2e/tests/` as the spec directory later tasks add to.

- [ ] **Step 1: Scaffold the Playwright project**

`e2e/package.json`:

```json
{
    "name": "bcoem-e2e",
    "private": true,
    "scripts": {
        "test": "playwright test",
        "test:headed": "playwright test --headed",
        "report": "playwright show-report"
    },
    "devDependencies": {
        "@playwright/test": "^1.45.0"
    }
}
```

`e2e/playwright.config.ts`:

```ts
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  fullyParallel: false,          // journeys share one seeded DB; keep ordering deterministic
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: [['html', { open: 'never' }], ['list']],
  use: {
    baseURL: process.env.BASE_URL ?? 'http://localhost:8080',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
```

Append to `.gitignore`:

```
e2e/node_modules/
e2e/test-results/
e2e/playwright-report/
```

- [ ] **Step 2: Install**

```bash
cd e2e && npm install && npx playwright install chromium
```

- [ ] **Step 3: Write the smoke test**

`e2e/tests/smoke.spec.ts`:

```ts
import { test, expect } from '@playwright/test';

test('home page renders the competition site', async ({ page }) => {
  const resp = await page.goto('/index.php');
  expect(resp?.status()).toBe(200);
  await expect(page).toHaveTitle(/Brew Competition Online Entry/);
});

test('login page renders its form fields', async ({ page }) => {
  await page.goto('/index.php?section=login');
  await expect(page.locator('input[name="loginUsername"]')).toBeVisible();
  await expect(page.locator('input[name="loginPassword"]')).toBeVisible();
});
```

- [ ] **Step 4: Run it (stack must be up)**

```bash
cd e2e && npx playwright test tests/smoke.spec.ts
```

Expected: 2 passed.

- [ ] **Step 5: Commit**

```bash
git add e2e/package.json e2e/package-lock.json e2e/playwright.config.ts e2e/tests/smoke.spec.ts .gitignore
git commit -m "Add Playwright e2e scaffold with smoke tests"
```

---

### Task 6: E2E fixtures + auth helpers

**Files:**
- Create: `docker/03-e2e-fixtures.sql`, `e2e/helpers/auth.ts`
- Modify: `docker-compose.yml` (one additive volume line), `e2e/tests/smoke.spec.ts` (add admin-login smoke)

**Interfaces:**
- Produces: `loginAsAdmin(page)` — logs in the seeded admin, resolves when the nav shows the Admin Dashboard link; `registerEntrant(page)` — registers a fresh entrant via the public form, returns `{ email, password }`, leaves the session logged in (registration auto-logs-in). Both exported from `e2e/helpers/auth.ts`. Admin creds constant `ADMIN = { email: 'user.baseline@brewingcompetitions.com', password: 'bcoem' }`.

- [ ] **Step 1: Write the fixture SQL (CAPTCHA off, deterministically)**

First confirm the column exists:

```bash
docker compose exec db mariadb -ubcoem -pbcoem_password bcoem -e "SHOW COLUMNS FROM baseline_preferences LIKE 'prefsCAPTCHA';"
```

Expected: one row. Then create `docker/03-e2e-fixtures.sql`:

```sql
-- E2E test fixtures, applied after 02-open-registration.sql on fresh volumes.
-- Registration must be CAPTCHA-free for automated journeys (reCAPTCHA cannot
-- and should not be solved by tests).
USE bcoem;
UPDATE baseline_preferences SET prefsCAPTCHA = '0';
```

- [ ] **Step 2: Mount it in docker-compose.yml**

Add one line to the `db` service volumes, after the `02-open-registration.sql` line:

```yaml
      - ./docker/03-e2e-fixtures.sql:/docker-entrypoint-initdb.d/03-e2e-fixtures.sql:ro
```

- [ ] **Step 3: Reseed and verify**

```bash
docker compose down -v && docker compose up -d
docker compose exec db mariadb -ubcoem -pbcoem_password bcoem -e "SELECT prefsCAPTCHA FROM baseline_preferences;"
```

Expected: `0`.

- [ ] **Step 4: Write the auth helpers**

`e2e/helpers/auth.ts`:

```ts
import { Page, expect } from '@playwright/test';

export const ADMIN = {
  email: 'user.baseline@brewingcompetitions.com',
  password: 'bcoem',
};

export async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto('/index.php?section=login');
  await page.fill('input[name="loginUsername"]', ADMIN.email);
  await page.fill('input[name="loginPassword"]', ADMIN.password);
  await page.click('button[name="submit"]');
  await expect(page.locator('a[href*="section=admin"]').first()).toBeVisible();
}

export interface EntrantCreds { email: string; password: string; }

export async function registerEntrant(page: Page): Promise<EntrantCreds> {
  const email = `e2e-${Date.now()}-${Math.floor(Math.random() * 1e6)}@example.com`;
  const password = 'E2eTest123!';
  await page.goto('/index.php?section=register&go=entrant');
  // Field names below must be confirmed against the live form on first
  // implementation run (see Step 5); the registration form is
  // sections/register.sec.php and posts to
  // includes/process.inc.php?action=add&dbTable=baseline_users&section=register&go=entrant.
  await page.fill('input[name="brewerFirstName"]', 'E2e');
  await page.fill('input[name="brewerLastName"]', 'Entrant');
  await page.fill('input[name="user_name"]', email);
  await page.fill('input[name="password"]', password);
  await page.fill('input[name="password2"]', password);
  await page.fill('input[name="brewerAddress"]', '1 Test Street');
  await page.fill('input[name="brewerCity"]', 'Testville');
  await page.fill('input[name="brewerZip"]', '75001');
  await page.fill('input[name="brewerPhone1"]', '555-0100');
  await page.click('button[type="submit"]');
  // Successful registration auto-logs-in and redirects to the account area.
  await expect(page.locator('a[href*="section=logout"], a[href*="action=logout"]').first()).toBeVisible();
  return { email, password };
}
```

- [ ] **Step 5: Confirm registration field names against the live form**

```bash
curl -s "http://localhost:8080/index.php?section=register&go=entrant" | grep -o 'name="[a-zA-Z0-9_]*"' | sort -u
```

Adjust the `page.fill` selectors in `registerEntrant` to the actual visible required fields (state/country are `<select>`s — use `page.selectOption`). The helper must fill every field the form marks `required`.

- [ ] **Step 6: Add an admin-login smoke test**

Append to `e2e/tests/smoke.spec.ts`:

```ts
import { loginAsAdmin } from '../helpers/auth';

test('seeded admin can log in and reach the dashboard', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/index.php?section=admin');
  await expect(page).toHaveURL(/section=admin/);
});
```

- [ ] **Step 7: Run smoke suite**

```bash
cd e2e && npx playwright test tests/smoke.spec.ts
```

Expected: 3 passed.

- [ ] **Step 8: Commit**

```bash
git add docker/03-e2e-fixtures.sql docker-compose.yml e2e/helpers/auth.ts e2e/tests/smoke.spec.ts
git commit -m "Add e2e DB fixtures and auth helpers (admin login, entrant registration)"
```

---

### Task 7: Entrant journey spec

**Files:**
- Create: `e2e/tests/entrant-journey.spec.ts`
- Modify (data-test attributes only, if selectors prove ambiguous): `sections/brew.sec.php`, `sections/brewer_entries.sec.php`, `sections/pay.sec.php`

**Interfaces:**
- Consumes: `registerEntrant(page)` from `e2e/helpers/auth.ts`.
- Produces: the flagship regression test for the strangler migration — this spec must pass identically before and after the Phase 2 Slim shell.

- [ ] **Step 1: Write the journey spec**

`e2e/tests/entrant-journey.spec.ts`:

```ts
import { test, expect } from '@playwright/test';
import { registerEntrant } from '../helpers/auth';

// One continuous journey: register → create entry → edit → verify → pay page.
// Serial: each step depends on state from the previous one.
test.describe.serial('entrant journey', () => {
  test('register, create, edit, and see an entry through to payment', async ({ page }) => {
    await registerEntrant(page);

    // — Create entry —
    await page.goto('/index.php?section=list');
    await page.click('a[href*="section=brew"][href*="action=add"]');
    await page.fill('input[name="brewName"]', 'E2E Test Ale');
    // brewStyle is a bootstrap-select (hidden native <select id="type">):
    // drive the UI widget, not the hidden element.
    await page.click('button[data-id="type"]');
    await page.locator('.dropdown-menu.open li, .dropdown-menu.show li')
      .filter({ hasText: /American IPA|Ordinary Bitter|American Light Lager/ })
      .first().click();
    await page.click('form[name="form1"] button[type="submit"]');

    // — Verify it appears in the account list —
    await page.goto('/index.php?section=list');
    await expect(page.getByText('E2E Test Ale')).toBeVisible();

    // — Edit —
    await page.click('a[href*="section=brew"][href*="action=edit"]');
    await page.fill('input[name="brewName"]', 'E2E Test Ale (revised)');
    await page.click('form[name="form1"] button[type="submit"]');
    await page.goto('/index.php?section=list');
    await expect(page.getByText('E2E Test Ale (revised)')).toBeVisible();

    // — Payment page lists the entry/fee (PayPal itself is out of scope) —
    await page.goto('/index.php?section=pay');
    const payBody = await page.textContent('body');
    expect(payBody).toMatch(/E2E Test Ale \(revised\)|Total|Fee/i);
  });
});
```

- [ ] **Step 2: First run — expect selector drift, fix with data-test attributes**

```bash
cd e2e && npx playwright test tests/entrant-journey.spec.ts --headed
```

Where a selector is ambiguous or fragile (e.g. multiple `form1` submit buttons, the entry-list edit link), add a `data-test` attribute to the legacy markup instead of writing a brittle CSS chain. Pattern (additive only — never modify existing attributes):

```php
<!-- sections/brew.sec.php, on the submit button: -->
<button ... data-test="entry-save" ...>
```

then in the spec: `page.click('[data-test="entry-save"]')`. Keep a running list of every attribute added.

- [ ] **Step 3: Run until green, twice in a row (idempotency check)**

```bash
cd e2e && npx playwright test tests/entrant-journey.spec.ts
cd e2e && npx playwright test tests/entrant-journey.spec.ts
```

Expected: both runs pass — each run registers a fresh unique entrant, so no cross-run state collision.

- [ ] **Step 4: Commit**

```bash
git add e2e/tests/entrant-journey.spec.ts sections/
git commit -m "Add entrant e2e journey: register, create/edit entry, payment page"
```

---

### Task 8: Admin journey spec

**Files:**
- Create: `e2e/tests/admin-journey.spec.ts`
- Modify (data-test attributes only): `admin/judging_tables.admin.php`, `admin/entries.admin.php` as needed

**Interfaces:**
- Consumes: `loginAsAdmin(page)`, `registerEntrant(page)` from `e2e/helpers/auth.ts`.
- Produces: admin-side regression coverage for the highest-churn admin hotspots (`entries.admin.php`, `judging_tables.admin.php`).

- [ ] **Step 1: Write the admin journey**

`e2e/tests/admin-journey.spec.ts`:

```ts
import { test, expect, Browser } from '@playwright/test';
import { loginAsAdmin, registerEntrant } from '../helpers/auth';

async function createEntrantWithEntry(browser: Browser): Promise<string> {
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await registerEntrant(page);
  await page.goto('/index.php?section=list');
  await page.click('a[href*="section=brew"][href*="action=add"]');
  const name = `Admin Journey Ale ${Date.now()}`;
  await page.fill('input[name="brewName"]', name);
  await page.click('button[data-id="type"]');
  await page.locator('.dropdown-menu.open li, .dropdown-menu.show li')
    .filter({ hasText: /American IPA|Ordinary Bitter|American Light Lager/ })
    .first().click();
  await page.click('form[name="form1"] button[type="submit"]');
  await ctx.close();
  return name;
}

test.describe.serial('admin journey', () => {
  test('admin sees entries and manages judging tables', async ({ browser, page }) => {
    const entryName = await createEntrantWithEntry(browser);

    await loginAsAdmin(page);

    // — Entries admin lists the new entry —
    await page.goto('/index.php?section=admin&go=entries');
    await expect(page.getByText(entryName)).toBeVisible();

    // — Create a judging table —
    await page.goto('/index.php?section=admin&go=judging_tables');
    // "Add table" form: input#tableName, selectpickers tableNumber/tableLocation,
    // input#tableEntryLimit; posts to process.inc.php?section=admin&action=add.
    await page.fill('input[name="tableName"]', 'E2E Judging Table');
    await page.click('form[name="form1"] button[type="submit"]');
    await expect(page.getByText('E2E Judging Table')).toBeVisible();
  });
});
```

- [ ] **Step 2: First run, extend to assignment and scoring incrementally**

```bash
cd e2e && npx playwright test tests/admin-journey.spec.ts --headed
```

After table creation passes, extend the same spec file in this order, running between each addition, using the live UI to discover exact form fields (`curl -s <url> | grep -o 'name="[a-zA-Z0-9_]*"' | sort -u` works for any admin page while logged out — for logged-in pages use Playwright's `--debug` inspector):

1. **Assign styles/entries to the table** — edit link on the created table row (`judging_tables.admin.php` edit form, fields `tableName`/`tableNumber`/`tableLocation`/`tableEntryLimit` plus the style-assignment control on the same page).
2. **Enter a score for the entry** — `/index.php?section=admin&go=judging_scores`; score the entry with place `1`.
3. **Publish/verify results** — `/index.php?section=winners` (public page) shows the placed entry after scores exist.

Each extension follows the same discipline: run, add `data-test` attributes where selectors are ambiguous, re-run.

- [ ] **Step 3: Idempotency check — run twice**

```bash
cd e2e && npx playwright test tests/admin-journey.spec.ts
cd e2e && npx playwright test tests/admin-journey.spec.ts
```

Both green. If the second run fails on duplicate table names, make the table name unique per run (`E2E Judging Table ${Date.now()}`).

- [ ] **Step 4: Commit**

```bash
git add e2e/tests/admin-journey.spec.ts admin/
git commit -m "Add admin e2e journey: entries list, judging tables, scoring, winners"
```

---

### Task 9: Security-invariant spec

**Files:**
- Create: `e2e/tests/security-invariants.spec.ts`

**Interfaces:**
- Consumes: `loginAsAdmin`, `registerEntrant` helpers.
- Produces: the acceptance tests for Phase 1's security fixes and Phase 2's central authorization gate. Tests asserting *not-yet-true* secure behavior are annotated `test.fixme` — Phase 1 removes the annotation as each fix lands.

- [ ] **Step 1: Write the invariant tests**

`e2e/tests/security-invariants.spec.ts`:

```ts
import { test, expect } from '@playwright/test';
import { registerEntrant } from '../helpers/auth';

// These encode the authorization invariants from the design spec (Section 5).
// Invariants that already hold are plain tests and must stay green forever.
// Invariants that current code violates are `fixme` until Phase 1 fixes land.

test('anonymous user cannot reach the admin section', async ({ page }) => {
  await page.goto('/index.php?section=admin');
  // index.php:74 redirects anonymous admin hits to ?msg=0 (login prompt)
  await expect(page).toHaveURL(/msg=0/);
  await expect(page.locator('a[href*="go=judging_tables"]')).toHaveCount(0);
});

test('anonymous user cannot reach account pages', async ({ page }) => {
  await page.goto('/index.php?section=list');
  // index.php:27 redirects anonymous account-page hits to ?msg=99
  await expect(page).toHaveURL(/msg=99/);
});

test('entrant cannot reach the admin section', async ({ page }) => {
  await registerEntrant(page);
  await page.goto('/index.php?section=admin');
  // index.php:84 redirects userLevel > 1 to ?msg=4
  await expect(page).toHaveURL(/msg=4/);
  await expect(page.locator('a[href*="go=judging_tables"]')).toHaveCount(0);
});

// P1-SEC-005: handle.php builds readfile(USER_DOCS."$id.pdf") from the raw id
// param; sterilize() does not strip "../". Secure behavior: reject ids
// containing path separators. Currently VULNERABLE — fixme until Phase 1.
test.fixme('pdf download rejects path traversal in id', async ({ page }) => {
  await registerEntrant(page); // endpoint requires any logged-in session
  const resp = await page.request.get(
    '/handle.php?section=pdf-download&id=../../../../etc/passwd%00');
  expect([400, 403, 404]).toContain(resp.status());
});

// ajax/save.ajax.php must not be reachable without an authenticated session.
test('save endpoint denies anonymous requests', async ({ page }) => {
  const resp = await page.request.get('/ajax/save.ajax.php');
  const body = await resp.text();
  // Must not return a success/data payload to an anonymous caller.
  expect(body).not.toMatch(/success|saved/i);
});
```

- [ ] **Step 2: Run and reconcile annotations with reality**

```bash
cd e2e && npx playwright test tests/security-invariants.spec.ts
```

For each test: if a plain test fails, current behavior is worse than believed — verify the failure manually (`curl -i` the URL), then convert it to `test.fixme` with a comment citing the observed behavior; it becomes a Phase 1 work item. If a `fixme` test would actually pass, remove the annotation. The suite must end fully green (fixme = skipped-with-tracking).

- [ ] **Step 3: Commit**

```bash
git add e2e/tests/security-invariants.spec.ts
git commit -m "Add security-invariant e2e tests; fixme markers are Phase 1 acceptance criteria"
```

---

### Task 10: CI workflow

**Files:**
- Create: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: every command established above — phpstan, the three phpunit suites (env contract from Task 4), the compose stack, `cd e2e && npx playwright test`.
- Produces: required status checks for all future PRs (upstream syncs included, per spec Section 6).

- [ ] **Step 1: Write the workflow**

`.github/workflows/ci.yml`:

```yaml
name: CI

on:
  push:
    branches: [docker-baseline-db, develop, master]
  pull_request:

jobs:
  static-and-unit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mysqli, mbstring, gd, zip, intl, exif
      - run: composer install --no-interaction
      - run: vendor/bin/phpstan analyse
      - run: vendor/bin/phpunit --testsuite Unit

  db-tests:
    runs-on: ubuntu-latest
    services:
      mariadb:
        image: mariadb:11
        env:
          MARIADB_ROOT_PASSWORD: root_password
          MARIADB_DATABASE: bcoem
          MARIADB_USER: bcoem
          MARIADB_PASSWORD: bcoem_password
        ports: ['3306:3306']
        options: >-
          --health-cmd="healthcheck.sh --connect --innodb_initialized"
          --health-interval=10s --health-timeout=5s --health-retries=10
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mysqli, mbstring, gd, zip, intl, exif
      - run: composer install --no-interaction
      - name: Seed baseline schema + fixtures
        run: |
          mysql -h127.0.0.1 -ubcoem -pbcoem_password bcoem < sql/bcoem_baseline_3.0.X.sql
          mysql -h127.0.0.1 -ubcoem -pbcoem_password bcoem < docker/02-open-registration.sql
          mysql -h127.0.0.1 -ubcoem -pbcoem_password bcoem < docker/03-e2e-fixtures.sql
      - name: Integration + Approval tiers
        env:
          BCOEM_DB_HOST: 127.0.0.1
        run: |
          vendor/bin/phpunit --testsuite Integration
          vendor/bin/phpunit --testsuite Approval

  e2e:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Start the app stack
        run: |
          docker compose up -d --build
          timeout 120 sh -c 'until curl -sf http://localhost:8080/index.php > /dev/null; do sleep 3; done'
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - name: Run Playwright suite
        working-directory: e2e
        run: |
          npm ci
          npx playwright install --with-deps chromium
          npx playwright test
      - uses: actions/upload-artifact@v4
        if: failure()
        with:
          name: playwright-report
          path: |
            e2e/playwright-report/
            e2e/test-results/
          retention-days: 14
      - name: Stack logs on failure
        if: failure()
        run: docker compose logs --tail 200
```

- [ ] **Step 2: Validate the workflow locally as far as possible**

```bash
# YAML sanity:
docker compose config -q && echo "compose ok"
# The db-tests seeding path, replicated against the local stack DB is already
# proven by Tasks 3-4; the e2e job replicates Tasks 5-9 exactly.
```

- [ ] **Step 3: Commit, push, verify green in Actions**

```bash
git add .github/workflows/ci.yml
git commit -m "Add CI: PHPStan, 3-tier PHPUnit, Playwright e2e"
git push origin docker-baseline-db
gh run watch --exit-status || gh run view --log-failed
```

Expected: all three jobs green. Iterate on CI-only failures (typically: mysql client availability — preinstalled on ubuntu-latest; compose build time) until green.

---

### Task 11: Testing runbook

**Files:**
- Create: `e2e/README.md`
- Modify: `Docs/docker-loadtest-runbook.md` (append a "Test suites" section)

- [ ] **Step 1: Write e2e/README.md**

````markdown
# BCOE&M end-to-end tests (Playwright)

Prereqs: Node 20+, the Docker stack running (`docker compose up -d`).

```bash
cd e2e
npm install
npx playwright install chromium
npx playwright test              # all specs
npx playwright test --headed     # watch it
npx playwright show-report       # last HTML report
```

- `BASE_URL` overrides the target (default `http://localhost:8080`).
- Specs: `smoke` (app up, logins work), `entrant-journey` (register→enter→edit→pay),
  `admin-journey` (entries, judging tables, scores, winners),
  `security-invariants` (authorization boundaries; `fixme` = known Phase 1 work).
- Fresh DB state: `docker compose down -v && docker compose up -d`
  (journeys create unique users per run and do not require reseeding).
- PHPUnit tiers (characterization): `docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit`
````

- [ ] **Step 2: Append to the runbook and commit**

Append to `Docs/docker-loadtest-runbook.md`:

```markdown
## Test suites (added Phase 0)

- PHPUnit 3-tier characterization suite: `docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit`
- Playwright e2e: see `e2e/README.md`
- CI runs both on every push/PR: `.github/workflows/ci.yml`
```

```bash
git add e2e/README.md Docs/docker-loadtest-runbook.md
git commit -m "Document Phase 0 test suites and how to run them"
```

---

## Self-review notes (already applied)

- **Spec coverage:** Section 5 items → Tasks 1–4 (characterization adoption incl. InnoDB), 5–8 (e2e journeys + data-test discipline), 9 (security invariants as Phase 1 acceptance), 10 (CI ladder in spec order: PHPStan → Unit → DB tiers → Playwright), 11 (docs). PayPal deliberately stubbed at "payment page lists entry" per spec ("PayPal stubbed"). The spec's "suite passes identically before/after the Slim shell" gate belongs to Phase 2 and needs no task here.
- **Known judgment calls:** Task 2 ports legacy bug fixes (only non-additive legacy change; the tests pinning them are the acceptance criteria). Task 8 Step 2 is discovery-driven by necessity — admin scoring forms are only reachable through stateful UI; the plan constrains discovery with exact URLs, field names where verified (`tableName`, `tableNumber`, `tableLocation`, `tableEntryLimit`), commands, and the data-test discipline.
- **Type consistency:** helper names (`loginAsAdmin`, `registerEntrant`, `ADMIN`, `EntrantCreds`) and env contract (`BCOEM_DB_*`) are used identically across Tasks 6–10.
