# Testing guide

How to run the automated tests for this branch, and how to add more.

There are two test suites in this project, and **they live on different branches**:

| Suite | What it tests | Where it lives | Runs against this branch? |
| --- | --- | --- | --- |
| **Playwright e2e** | The real app through a browser: registration, entries, admin, security, Bluebonnet rules | `e2e/` on `bluebonnet-testing` | **Yes** |
| **PHPUnit** | PHP library functions in `lib/common.lib.php` | `tests/` (Unit tier only; database tiers are on `testing-baseline`) | **Unit tier only** (see [PHPUnit](#phpunit-suite)) |

If you only want to know "did I break anything on the 2.7 / Bluebonnet code?", run the Playwright suite. It takes about 2.5 minutes.

---

## 1. Playwright e2e suite

### What you need

- Docker with Compose v2 (`docker compose`)
- Node 20 or newer
- Free ports for the web and database containers. The defaults are 8080 and 3306. The examples below use **8082 and 3307** so they don't clash with another BCOE&M stack. Pick any free pair.

### Run it

From the repo root:

```bash
# 1. Start the app and database (first run builds the image and seeds the DB)
WEB_PORT=8082 DB_PORT=3307 docker compose up -d --build

# 2. Install the test dependencies (first time only)
cd e2e
npm install
npx playwright install chromium

# 3. Run everything
DB_PORT=3307 BASE_URL=http://localhost:8082 npx playwright test

OR

# 4. Run visible browser with one test at a time
DB_PORT=3307 BASE_URL=http://localhost:8082 npx playwright test --headed --workders=1
```

The suite waits for the app to be genuinely ready, so it is safe to start it right after `docker compose up`. After first-run seeding the database restarts once, and `global-setup.ts` polls for up to two minutes until the registration page renders.

The two environment variables:

- `BASE_URL`: where the app is. Defaults to `http://localhost:8080`.
- `DB_PORT` (and `DB_HOST`): used by specs that talk to MariaDB directly, for example to backdate an entry's timestamp. Defaults to 3306.

Expected result on a healthy checkout: **23 passed, 1 skipped, about 2.5 minutes.** The skipped test is a known gap (see [Known gaps](#known-gaps)).

### Useful variations

```bash
npx playwright test tests/smoke.spec.ts        # one spec file
npx playwright test -g "path traversal"        # tests whose title matches
npx playwright test --headed                   # watch the browser
npx playwright test --headed --workers=1       # watch the browser test at a time
npx playwright test --debug                    # step through with the inspector
npx playwright test --reporter=list            # one line per test
npx playwright show-report                     # open the HTML report from the last run (run from e2e/)
npx playwright show-trace test-results/<dir>/trace.zip   # replay a failure
```

All `npx playwright` commands must be run from the `e2e/` folder, because that is where `playwright.config.ts` lives and where the report is written (`e2e/playwright-report`). Running `show-report` from the repo root fails with "No report found". The HTML report is only written by the default reporter, so a run with `--reporter=list` alone doesn't refresh it; use `--reporter=list,html` to get both.

Failures save a screenshot and a trace under `e2e/test-results/` (gitignored). The trace is the fastest way to see what the page looked like when a test broke.

### The specs

| Spec | Covers |
| --- | --- |
| `smoke.spec.ts` | App serves, login modal, admin login, entrant registration |
| `entrant-journey.spec.ts` | Register, create an entry, edit it, reach the payment page |
| `admin-journey.spec.ts` | Entries list, build a judging table, record a score |
| `security-invariants.spec.ts` | Anonymous and entrant users can't reach admin or account pages, PDF download rejects path traversal, the legacy login bypass is closed, the session id changes on login |
| `bluebonnet-rules.spec.ts` | Bluebonnet-only rules: 4 entries per table, 1 per entrant per table, 60 seconds between entries, unpaid entries deleted after 1 hour |

### Starting from a clean database

Tests create unique users on every run, so back-to-back runs don't collide and you normally don't need to reset. To start completely fresh:

```bash
WEB_PORT=8082 DB_PORT=3307 docker compose down -v
WEB_PORT=8082 DB_PORT=3307 docker compose up -d --build
```

`down -v` **deletes the database volume**. Only do this on a stack that holds nothing you care about. The database is rebuilt from the files below, which run in order on first start:

| File | Purpose |
| --- | --- |
| `sql/bcoem_baseline_2.7.X.sql`, via `docker/01-baseline.sh` | Base schema and seed data (the script strips the table prefix to match production) |
| `docker/02-open-registration.sql` | Moves the baseline's 2023 deadlines into the future so entry and registration are open |
| `docker/03-e2e-fixtures.sql` | Turns off CAPTCHA so tests can register users, and pins the competition timezone to Central |
| `docker/04-schema-catchup.sql` | Brings the stale baseline up to the schema the code expects |
| `docker/05-bluebonnet-schema.sql` | Bluebonnet layer: `brewing` becomes a view over `brewing_bbo_real`, plus `bbo_tables` and `barcode_log` |

Two things to know about that seed data:

- `bbo_tables` is a **synthetic** layout (one table per BJCP category group). The real Bluebonnet table layout isn't in the repo.
- The timezone matters. `config.php` sets `SET time_zone = '-06:00'`. If the fixture's timezone differs, the 1-hour unpaid-delete rule removes every new entry immediately.

### Adding a test

1. **Pick a spec file.** Add to an existing one if the topic fits, or create `e2e/tests/<topic>.spec.ts`. Files are picked up automatically.
2. **Reuse the helpers** in `e2e/helpers/` rather than driving forms by hand:

   | Helper | Use it to |
   | --- | --- |
   | `auth.ts`: `loginAsAdmin(page)`, `login(page, email, pw)`, `registerEntrant(page)` | Get a logged-in admin, or a brand-new entrant (returns their credentials) |
   | `entries.ts`: `createEntrantWithEntry(browser)`, `chooseStyle(page)` | Create an entrant with one entry in a throwaway browser context |
   | `bluebonnet.ts`: `submitEntry(page, style, name, force?)`, `entrantWithEntry(...)`, `bboName(tag)` | Add Bluebonnet entries. `force = true` removes the disabled flag so you test the **server** check, not only the form |
   | `db.ts`: `query(sql)`, `backdateEntries(name, minutes)`, `purgeBboTestEntries()` | Read or adjust the database directly, for time-based rules |
   | `forms.ts`: `fillDateTime(page, name, value)` | Fill the 2.7 datetime pickers (plain `fill()` silently saves today's date) |

3. **Write the test.** The smallest useful example:

   ```ts
   import { test, expect } from '@playwright/test';
   import { loginAsAdmin } from '../helpers/auth';

   test('admin can open the entries page', async ({ page }) => {
     await loginAsAdmin(page);
     await page.goto('/index.php?section=admin&go=entries');
     await expect(page.locator('body')).toContainText('Entries');
   });
   ```

4. **Run just your test** with `npx playwright test -g "admin can open"`, then run the whole suite once before committing.

### Conventions and gotchas

- **One worker, in file order.** Tests share one seeded database, so `playwright.config.ts` sets `workers: 1` and `fullyParallel: false`. Don't turn parallelism on.
- **Use real UI paths.** Register and log in through the actual forms. That keeps tests honest about what a user can do.
- **Name test data so you can clean it up.** Bluebonnet specs prefix entry names with `BBO ` (via `bboName()`), and `purgeBboTestEntries()` deletes them before and after each test.
- **Test the server, not just the form.** For a rule that the page also enforces in JavaScript, add a case that bypasses the front end (see `submitEntry(..., force = true)`). A disabled button proves nothing about the server.
- **Mind the 30-second default timeout.** A test that registers several users can exceed it. Raise it for that one test with `test.setTimeout(60_000)`, as the table-cap test does.
- **Record known bugs as `test.fixme`.** Write the test as it should behave, mark it `fixme` with a comment explaining the gap, and flip it to a normal test when the bug is fixed.
- **Redirect messages carry the result.** Many app actions finish with `msg=<code>` in the URL (for example `msg=1` added, `msg=13` medal category full, `msg=bbo3` entrant already has an entry at this table). Assert on the code and on the visible text.

### Known gaps

- `bluebonnet-rules.spec.ts` has one `fixme`: the server accepts an add with no style. Only the literal `0-A` placeholder is rejected.
- Results from the most recent recorded run are in [`e2e/RESULTS-2026-11-02.md`](e2e/RESULTS-2026-11-02.md).

### Troubleshooting

| Symptom | Likely cause and fix |
| --- | --- |
| `App at http://... not ready after 120s` | Containers still starting, or the wrong `BASE_URL`/ports. Check `docker compose ps` (the db should say `healthy`) and `docker compose logs web`. |
| `ECONNREFUSED` from a DB-backed spec | `DB_PORT` doesn't match the port you started the stack on. |
| Every new entry vanishes immediately | The competition timezone and PHP's `SET time_zone` have drifted apart. Reseed with `down -v` then `up`. |
| Registration tests fail on a CAPTCHA | `docker/03-e2e-fixtures.sql` didn't run. The DB volume pre-dates it, so reseed with `down -v`. |
| Port already allocated | Another stack is using it. Choose different `WEB_PORT` and `DB_PORT` values and pass the same ones to the test command. |

---

## PHPUnit suite

**Status: the Unit tier is ported; Integration and Approval are not.**

```bash
composer install          # first time only
vendor/bin/phpunit        # Unit tier: 214 tests, 13 skipped, no database needed
```

The suite was written for the 3.x code on the `testing-baseline` branch and adapted to 2.7. How it was adapted, which tests are skipped because of 2.7 bugs, and which were deleted is in [tests/README.md](tests/README.md). The 13 skipped tests are backport candidates: remove a skip once the matching fix lands.

`testing-baseline` also has two database tiers that are **not** on this branch yet:

| Tier | Needs a database? | Covers |
| --- | --- | --- |
| Integration | Yes | Fee totals, table info, brewer info, best-brewer points, token verification. Each test rolls back its transaction |
| Approval | Yes | Output compared with committed snapshots |

A trial run against the Bluebonnet test database failed because those tests hardcode the `baseline_` table prefix (the Bluebonnet DB is unprefixed, and an empty `BCOEM_DB_PREFIX` is treated as unset), and because the 2.7 schema differs from 3.x. Porting them is open work.

**Don't share a database between PHPUnit's database tiers and Playwright.** The e2e suite commits real rows, and the fee-total integration tests sum across every row. Reseed (`docker compose down -v && up -d`) between them, or use separate stacks.

### Adding a unit test

1. Add a method to the matching file in `tests/Unit/`, or create `tests/Unit/<Topic>Test.php` (class `<Topic>Test extends PHPUnit\Framework\TestCase`).
2. Call the `lib/common.lib.php` function directly. The bootstrap has already loaded it.
3. If the test exposes a 2.7 bug, write the expectation you want and add `$this->markTestSkipped('2.7 bug: ...')` as the first line, then list it in `tests/README.md`.
4. Run `vendor/bin/phpunit`.
