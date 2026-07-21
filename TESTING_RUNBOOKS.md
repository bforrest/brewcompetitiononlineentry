# Testing Runbooks

## Quick Start: Run All Tests Locally

### One-Liner (All 4 Tiers)

```bash
# Setup
docker-compose up -d

# Wait for app ready
until curl -sf http://localhost:8080/index.php > /dev/null; do sleep 1; done

# Run all tests
php vendor/bin/phpunit && \
cd e2e && npm ci && npm test
```

**Expected Duration:** ~4 minutes  
**Expected Result:** "OK (x tests, y assertions)" from PHPUnit + "passed (z tests)" from Playwright

---

## Test Tiers: Individual Runbooks

### Tier 1: Unit Tests Only (30 seconds)

**Best for:** Quick feedback during development, no Docker needed.

```bash
php vendor/bin/phpunit --testsuite Unit -v
```

**Common Failures:**
- `Class not found`: Missing use statement or wrong namespace — check namespace path matches directory
- `Call to undefined method`: Method doesn't exist on mock or real object — check class definition
- `Assertion failed`: Logic error — inspect test setup and implementation

**Debug Tip:**
```bash
# Run single test file
php vendor/bin/phpunit tests/Unit/Domain/Entry/StyleNumberTest.php -v

# Run single test method
php vendor/bin/phpunit tests/Unit/Domain/Entry/StyleNumberTest.php::test_format_returns_combined_string -v
```

---

### Tier 2: Integration Tests (60 seconds + DB setup)

**Best for:** Verifying database queries and business logic.

**Prerequisites:**
```bash
docker-compose up -d db

# Verify DB is ready (wait for ~5 sec startup)
sleep 5

# Check connectivity
mysql -h 127.0.0.1 -u bcoem -pbcoem_password bcoem -e "SELECT 1;"
```

**Run:**
```bash
# Option A: Tests inside container (reads $GLOBALS['connection'] from docker-compose)
docker-compose exec -T web vendor/bin/phpunit --testsuite Integration -v

# Option B: Tests on local PHP (reads environment variables)
export BCOEM_DB_HOST=127.0.0.1
export BCOEM_DB_USER=bcoem
export BCOEM_DB_PASSWORD=bcoem_password
export BCOEM_DB_NAME=bcoem
export BCOEM_DB_PORT=3306
export BCOEM_DB_PREFIX=baseline_
php vendor/bin/phpunit --testsuite Integration -v
```

**Common Failures:**
- `Connection refused`: DB not running or not ready — check `docker-compose ps db` and `docker-compose logs db`
- `Unknown column 'xyz'`: Schema mismatch — check migration is applied; verify table structure with `SHOW CREATE TABLE baseline_xyz;`
- `Test did not execute`: Transaction rollback issue — check test extends `IntegrationTestCase`; verify MySQL supports transactions (InnoDB)

**Debug Tip:**
```bash
# Run single test and keep DB data (don't rollback)
# 1. Temporarily comment out rollback in tearDown() method
# 2. Run test
# 3. Query DB manually: mysql -u bcoem -pbcoem_password bcoem -e "SELECT * FROM baseline_brewing;"
# 4. Restore rollback

# Or, run in container to see raw SQL
docker-compose exec web vendor/bin/phpunit tests/Integration/Entry/EntryRepositoryIntegrationTest.php -v
```

---

### Tier 3: Approval Tests (20 seconds)

**Best for:** Detecting formatting regressions (URLs, style names, serialization).

```bash
php vendor/bin/phpunit --testsuite Approval -v
```

**If Snapshot Mismatch:**

```bash
# 1. Run test to see diff
php vendor/bin/phpunit tests/Approval/StyleConvertApprovalTest.php -v

# 2. Inspect the diff
cat tests/Approval/__snapshots__/style_convert_type4_rauchbier.snap

# 3. If change is intentional, approve all mismatches in test file
# Open test file, call $this->approveAll() in setUp or tearDown

# 4. Re-run to confirm
php vendor/bin/phpunit tests/Approval/StyleConvertApprovalTest.php -v

# 5. Commit updated snapshot files
git add tests/Approval/__snapshots__/
git commit -m "Update style conversion snapshot after format change"
```

**Common Failures:**
- `File does not exist`: Snapshot not yet created — this is normal for new tests; approve to generate initial snapshot
- `Expected output differs`: Formatting changed — review diff carefully, verify it's intentional

---

### Tier 4: E2E Tests (currently broken — see below)

**Best for:** End-to-end user journeys; browser interaction.

**⚠ Known-broken as of 2026-07-21:** every spec fails at the very first `page.goto()` with `net::ERR_SSL_PROTOCOL_ERROR`. `.htaccess` unconditionally redirects every request to HTTPS, but the Docker vhost never configures a TLS listener. Confirmed with `smoke.spec.ts` (simplest possible spec) — this is infrastructure, not test logic, and isn't specific to any one file. Check whether GitHub Actions CI hits the same issue before assuming this only affects local runs.

**Prerequisites:**
```bash
# Start full stack
docker-compose up -d

# Wait for app ready (check logs)
docker-compose logs --tail 20 web

# Verify with curl
until curl -sf http://localhost:8080/index.php > /dev/null; do echo "Waiting..."; sleep 2; done
```

**Run:**
```bash
cd e2e

# Headless (default, for CI)
npm test

# Headed (open browser, watch execution)
npm run test:headed

# Watch mode (re-run on file change)
npx playwright test --watch

# Single test file
npx playwright test entrant-journey.spec.ts

# Single test within file
npx playwright test -g "register, create, edit, and see an entry via modern routes"

# Debug (pauses before each step)
npx playwright test --debug
```

**View Results:**
```bash
npm run report
# Opens HTML report at file:///path/to/playwright-report/index.html
```

**Common Failures:**
- `Timeout waiting for selector`: App not ready or selector wrong — check app logs, verify element exists in browser
- `Navigation failed`: Page crashed or auth failed — check app logs for errors
- `Page close error`: Test left page in bad state — inspect test for unhandled promises

**Debug Tips:**
```bash
# Run headed to watch execution
npm run test:headed

# Verbose output
npx playwright test --verbose

# Take screenshots during failure
# Tests automatically capture screenshots on failure; view in report

# Check app logs
docker-compose logs web | tail -50
```

---

## Scenario: Adding a New Test

### Adding a Unit Test

```bash
# 1. Create test file
cat > tests/Unit/Domain/Entry/MyNewTest.php << 'EOF'
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Entry\ValueObject\EntryId;

class MyNewTest extends TestCase
{
    public function test_something(): void
    {
        $id = new EntryId(123);
        $this->assertSame(123, $id->value());
    }
}
EOF

# 2. Run it
php vendor/bin/phpunit tests/Unit/Domain/Entry/MyNewTest.php

# 3. Fix errors and re-run
```

### Adding an Integration Test

```bash
# 1. Create test file (must extend IntegrationTestCase)
cat > tests/Integration/MyNewIntegrationTest.php << 'EOF'
<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration;

class MyNewIntegrationTest extends IntegrationTestCase
{
    public function test_something(): void
    {
        $userId = $this->insertTestUser('test@example.com');
        $this->assertNotNull($userId['brewerId']);
    }
}
EOF

# 2. Start DB
docker-compose up -d db
sleep 5

# 3. Run it
export BCOEM_DB_HOST=127.0.0.1 BCOEM_DB_USER=bcoem BCOEM_DB_PASSWORD=bcoem_password
php vendor/bin/phpunit tests/Integration/MyNewIntegrationTest.php
```

### Adding an E2E Test

```bash
# 1. Create test file
cat > e2e/tests/my-journey.spec.ts << 'EOF'
import { test, expect } from '@playwright/test';

test('my user journey', async ({ page }) => {
  await page.goto('/index.php');
  await expect(page.locator('h1')).toContainText('Welcome');
});
EOF

# 2. Start stack
docker-compose up -d
until curl -sf http://localhost:8080/index.php > /dev/null; do sleep 1; done

# 3. Run it
cd e2e && npm test my-journey.spec.ts
```

---

## Scenario: Debugging a Failing Test

### Unit Test Failure

```bash
# 1. Run test with verbose output
php vendor/bin/phpunit tests/Unit/Domain/Entry/StyleNumberTest.php::test_format_returns_combined_string -v

# 2. Add debug output to test
// In test method:
$style = new StyleNumber('1', 'A');
var_dump($style->format());  // Check actual vs expected

# 3. Fix the implementation and re-run
```

### Integration Test Failure

```bash
# 1. Start DB
docker-compose up -d db
sleep 5

# 2. Run test
export BCOEM_DB_HOST=127.0.0.1 BCOEM_DB_USER=bcoem BCOEM_DB_PASSWORD=bcoem_password
php vendor/bin/phpunit tests/Integration/Entry/EntryRepositoryIntegrationTest.php::test_insert_entry_and_retrieve_by_id -v

# 3. If failed, query DB to inspect state
mysql -h 127.0.0.1 -u bcoem -pbcoem_password bcoem << 'EOF'
SELECT * FROM baseline_brewing LIMIT 5;
SELECT * FROM baseline_brewer LIMIT 5;
SELECT * FROM baseline_audit_log ORDER BY id DESC LIMIT 5;
EOF

# 4. Fix issue and re-run
```

### E2E Test Failure

```bash
# 1. Start stack
docker-compose up -d

# 2. Run test with headed browser to watch
cd e2e && npm run test:headed

# 3. Interact and observe where it fails

# 4. Check app logs
docker-compose logs web | grep -i error

# 5. Fix the test or app and re-run
npm test
```

---

## CI Failure Diagnosis

### "Unit tests failed" in GitHub Actions

```bash
# 1. Check the CI log for error message
# In GitHub UI: Actions → workflow run → static-and-unit → see logs

# 2. Reproduce locally
php vendor/bin/phpunit --testsuite Unit -v

# 3. Once fixed locally, push to trigger CI again
git push
```

### "Integration tests failed" in GitHub Actions

```bash
# 1. Check CI logs (may show connection error, assertion failure, etc.)

# 2. Reproduce locally with Docker stack
docker-compose up -d db
export BCOEM_DB_HOST=127.0.0.1 ...
php vendor/bin/phpunit --testsuite Integration -v

# 3. Fix and push
git push
```

### "E2E tests failed" in GitHub Actions

```bash
# 1. Check CI logs; look for "Timeout waiting for" or "Navigation failed" -
#    or ERR_SSL_PROTOCOL_ERROR, which as of 2026-07-21 fails every spec
#    locally (see Tier 4 runbook above) due to a .htaccess force-HTTPS rule
#    with no TLS listener configured in Docker. Check whether CI is hitting
#    the same thing before assuming it's a fresh regression.

# 2. Reproduce locally
docker-compose up -d
cd e2e && npm run test:headed

# 3. Check if app is responding
docker-compose logs web | tail -50

# 4. Fix (app, test, or both) and push
git push
```

### "Playwright report upload failed"

```bash
# This usually means test crashed and report couldn't be archived
# Check CI logs for what crashed the test (timeout, crash, etc.)

# Run locally headed to see the failure in detail
cd e2e && npm run test:headed
```

---

## Performance Profiling

### Identify Slow Tests

```bash
# Run with verbose timing
php vendor/bin/phpunit --testsuite Integration -v --testdox

# Look for tests that take >5s

# If slow test found, profile with xdebug or add query logging
# In EntryRepository, add logging to see N+1 queries
```

### Local Stack Performance Check

```bash
# Time each tier
time php vendor/bin/phpunit --testsuite Unit
time docker-compose exec -T web vendor/bin/phpunit --testsuite Integration
time docker-compose exec -T web vendor/bin/phpunit --testsuite Approval
cd e2e && time npm test
```

**Expected Times:** the figures previously here (Unit ~30s, Integration ~60s, Approval ~20s, E2E ~90s, total ~3.5 min) were not reverified this session and are presented as CI-runner estimates in `TESTING.md`/`TESTING_HEALTH_DASHBOARD.md` rather than repeated here as measured fact. On a warm local dev machine, Unit/Integration/Approval each complete in a few seconds — that's not representative of a cold CI runner, so don't use it to judge CI performance. E2E currently doesn't complete at all locally (see Tier 4 above).

---

## CI Schedule & Gating

### What Blocks Merge?

✓ **Unit tests fail** → PR cannot merge  
✓ **Integration tests fail** → PR cannot merge  
✓ **Approval tests fail** → PR cannot merge  
✓ **E2E tests fail** → PR cannot merge (as configured — but if CI hits the same HTTPS/TLS issue found locally 2026-07-21, this gate would currently be unconditionally red; verify against the actual Actions history before relying on it)  
✓ **PHPStan errors** → PR cannot merge (note: `phpstan.neon` is configured at level 0, not level 8 — an "error" here means a level-0 violation, which is a much lower bar than some docs in this repo previously implied)  

### Manual Triggers

```bash
# Re-run CI pipeline for a commit
git push --force-with-lease
# (or just `git push` if no force needed)

# CI re-runs automatically on every push
```

---

## Resources & Links

- **Full Testing Overview:** `TESTING.md` (this file's companion)
- **Test Code:** `tests/` and `e2e/tests/`
- **CI Workflow:** `.github/workflows/ci.yml`
- **Docker Setup:** `docker-compose.yml`
- **PHPUnit Config:** `phpunit.xml`
- **Playwright Config:** `e2e/playwright.config.ts`

