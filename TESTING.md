# Testing Overview & Health Report

**Last Updated:** 2026-07-21 (corrected — see `TESTING_HEALTH_DASHBOARD.md` for the detailed correction log)  
**Status:** Phases 3.1-3.4 have all landed on branch `slim` (with real bugs found and fixed the same day — see the dashboard's Issues & Incidents section); 79 test files (72 PHP, 7 TypeScript). E2E tier is currently broken locally (HTTPS/TLS misconfiguration, unrelated to test logic) — see Tier 4 below.

## Testing Tiers

BCOEM uses a **4-tier testing pyramid** designed to catch bugs at the right level while keeping iteration fast.

### Tier 1: Unit Tests (DB-Free, No Dependencies)

**Purpose:** Fast feedback on isolated logic (no database, no HTTP).

**Location:** `tests/Unit/`

**Test Count:** 48 files across:
- `Security/` — Role mapping, Identity, AccessPolicy enforcement
- `Kernel/Middleware/` — Auth, session, tracing middleware
- `Kernel/` — error handling, logging
- `Legacy/` — legacy file/process handlers
- `Domain/Entry/` — ValueObjects (EntryId, StyleNumber, BrewerInfo)
- `Domain/AdminPreferences/` — aggregate, value objects, commands, services (Phase 3.3)
- `Domain/Judging/` — JudgingTable aggregate, scores, flights (Phase 3.2)
- `Domain/Export/` — ExportService, ExportFormatterService, commands (Phase 3.4)
- Utility functions (dates, conversion, crypto, URLs)

**Database:** Almost entirely no — `tests/bootstrap.php` stubs the file-path/legacy constants most library code needs. One known exception: `HelloWorldRouteTest` builds the real Slim app via `buildApp()`, which resolves `Connection::class` from `$GLOBALS['connection']`; with no DB in this tier, that's a real (expected, pre-existing) error, not a stub failure.

**Run Locally:**
```bash
php vendor/bin/phpunit --testsuite Unit
```

**Run in CI:** 
- Trigger: every push to `master`, `develop`, `docker-baseline-db`, `slim`
- Trigger: every pull request
- Runtime: not reverified this session; treat prior "~30s" figure as a CI-runner estimate, not a current measurement
- Required to pass: ✓ YES (blocks merge)

**Health Metrics:**
- Count: 48 files, 577 tests, 943 assertions
- Coverage: All domain value objects (Entry, AdminPreferences, Judging, Export), middleware logic, utility functions
- Flakiness: None known (deterministic, no timing deps)
- Status: ✓ STABLE (1 known error + 2 known failures, both pre-existing/environmental — see `HelloWorldRouteTest`, `SessionMiddlewareTest`)

---

### Tier 2: Integration Tests (Real Database, No HTTP)

**Purpose:** Verify business logic against live DB (detect schema mismatches, query bugs).

**Location:** `tests/Integration/`

**Test Count:** 19 files:
- `Entry/` — EntryRepositoryIntegrationTest, EntryServiceIntegrationTest, AuditLogIntegrationTest
- `AdminPreferences/` — AdminPreferencesRepositoryIntegrationTest (Phase 3.3, against the real `admin_preferences`/`admin_preferences_events` tables)
- `Domain/Judging/` — JudgingTableRepositoryIntegrationTest, JudgingTableServiceIntegrationTest, JudgingScoreRepositoryIntegrationTest, JudgingScoreServiceIntegrationTest (Phase 3.2)
- `Domain/Export/` — BrewingExportRepositoryIntegrationTest, ExportServiceIntegrationTest (Phase 3.4)
- `BrewerInfoTest.php` — brewer data fetching
- `BestBrewerPointsTest.php` — scoring logic
- `PasswordLegacyMigrationTest.php` — password verification
- `VerifyTokenTest.php` — security tokens
- `DisplayPlaceTest.php` — placement calculations
- `GetTableInfoTest.php` — schema introspection
- `TotalFeesTest.php` — fee calculations
- `PhinxMigrationTest.php` — migration validity (now also asserts admin_preferences/admin_preferences_events exist)
- `ErrorHandlingTest.php` — error recovery

**Database:** YES. Uses Docker MariaDB (InnoDB) with **transactional rollback isolation**.
- Each test runs in a transaction
- Rolls back after test completes (no data persists)
- One-time orphan sweep before class runs (cleans up from stale runs)
- Caveat found this session: this isolation only covers rows *this* tier inserts. A Playwright run against the same local DB commits real, non-transactional rows — `TotalFeesTest` currently sees inflated sums from exactly this. Reseed the DB after running e2e locally.

**Run Locally:**
```bash
# 1. Start database
docker-compose up -d db

# 2. Run tests in container (reads $GLOBALS['connection'] from docker-compose)
docker-compose exec -T web vendor/bin/phpunit --testsuite Integration

# Or run on local PHP (reads $BCOEM_DB_* env vars)
export BCOEM_DB_HOST=127.0.0.1
export BCOEM_DB_USER=bcoem
export BCOEM_DB_PASSWORD=bcoem_password
export BCOEM_DB_NAME=bcoem
php vendor/bin/phpunit --testsuite Integration
```

**Run in CI:**
- Trigger: same as Unit (every push/PR to main branches, including `slim`)
- Database: Docker service (started before tests)
- Runtime: not reverified this session; treat prior "~60s" figure as a CI-runner estimate
- Required to pass: ✓ YES (blocks merge)

**Health Metrics:**
- Count: 19 files, 128 tests, 271 assertions
- Coverage: All repository queries, service workflows, business logic
- Flakiness: Minimal (transactional isolation prevents interference from other PHPUnit runs; does not prevent interference from Playwright — see above)
- Status: ✓ STABLE — 0 errors as of this correction; 2 failures from the Playwright-pollution issue above (not a code regression)

**Known Issues:**
- **Open:** `comp_id` schema mismatch — `BrewingExportRepository`/`ParticipantExportRepository`/`JudgingExportRepository` unconditionally query a `comp_id` column that doesn't exist anywhere in this schema (legacy code only ever touches it behind an `if (SINGLE)` gate that's false for this install). One integration test is explicitly skipped citing this; needs a product decision.
- Migration validation: PhinxMigrationTest checks that migrations create the expected columns/indexes on a real DB, but doesn't validate migration logic semantics beyond that (too expensive to run all migrations in tests).
- Resolved this session (see `TESTING_HEALTH_DASHBOARD.md` for full incident detail): `EntryRepository` was querying a fabricated `brewID` column (the real primary key is `id`) and misreading `brewBrewerID`'s casing when hydrating rows — `getById()`, `update()`, and `delete()` were all broken until this was the first time these tests ran to completion.

---

### Tier 3: Approval Tests (Read-Only Snapshots, No HTTP)

**Purpose:** Regression detect on formatted output (SRM color tables, style conversions, entry info formatting).

**Location:** `tests/Approval/`

**Test Count:** 5 test classes (6 PHP files in the directory; `SnapshotAssertions.php` is a shared helper, not a test), 47 tests, 102 assertions:
- `LinkBuilderApprovalTest.php` — HTML link generation (URL building, parameters)
- `EntryInfoApprovalTest.php` — entry DTO serialization (JSON, CSV)
- `SrmColorApprovalTest.php` — SRM/EBC color code lookup tables
- `StyleConvertApprovalTest.php` — BJCP style mapping (legacy → 2021, etc.)
- `StyleTypeApprovalTest.php` — style category formatting

**Database:** Mostly no, but not entirely as previously claimed here — `EntryInfoApprovalTest` does hit the real database (fails with "No database selected" if run without a DB connection available). The other 4 files are genuinely read-only/hardcoded.

**Snapshots:** Stored in `tests/Approval/__snapshots__/` (git-tracked).
- Approach: [PhpUnit approval testing](https://approvaltests.com/)
- If output changes unexpectedly: test fails, shows diff, you inspect then call `approveAll()` to accept
- Prevents silent formatting regressions

**Run Locally:**
```bash
php vendor/bin/phpunit --testsuite Approval

# If snapshots need updating after intentional changes:
# 1. Check the diff carefully
# 2. Call $this->approveAll() in test
# 3. Re-run and commit updated snapshot files
```

**Run in CI:**
- Trigger: same as Unit/Integration
- Runtime: not reverified this session; treat prior "~20s" figure as a CI-runner estimate
- Required to pass: ✓ YES (blocks merge)
- Special: Snapshot files must be committed (diff shows intent)

**Health Metrics:**
- Count: 5 test classes, 47 tests, 102 assertions
- Coverage: Output formatting, legacy compatibility layers
- Flakiness: None (deterministic output)
- Status: ✓ STABLE

---

### Tier 4: E2E Tests (Full Stack, Real HTTP, Real Browser)

**Purpose:** End-to-end user journeys (form submission, auth flows, multi-step actions).

**Location:** `e2e/tests/`

**Status: 🔴 BROKEN as of 2026-07-21, confirmed locally.** `.htaccess` unconditionally redirects every HTTP request to HTTPS (`RewriteCond %{HTTPS} off`), but the Docker vhost (`docker/apache/vhost.conf`) never configures a TLS listener — every spec fails at its very first `page.goto()` with `ERR_SSL_PROTOCOL_ERROR`, including the simplest possible spec (`smoke.spec.ts`). This is an infrastructure problem, not a test-logic problem, and it isn't specific to any one spec. Whether GitHub Actions CI is also affected wasn't reverified this session — check the Actions tab before trusting this doc's older "STABLE"/"GREEN" claims.

**Test Count:** 7 files with ~20 test scenarios:
- `entrant-journey.spec.ts` — legacy entry creation/edit flow + modern /entries routes
- `dual-path-verification.spec.ts` — same scenario on legacy vs modern routes (regression detect)
- `judging-dual-path.spec.ts` — Judging domain, legacy vs modern routes (Phase 3.2)
- `export-dual-path.spec.ts` — Export domain, legacy vs modern routes (Phase 3.4)
- `admin-journey.spec.ts` — admin login, judging table creation, scoring
- `security-invariants.spec.ts` — authorization checks (unauth access blocked, role enforcement)
- `smoke.spec.ts` — quick smoke tests (homepage loads, login form visible)

**Framework:** [Playwright](https://playwright.dev/) (Chromium browser).

**Stack:** Docker Compose (app + DB), tests run on host (browser → localhost:8080).

**Run Locally:**
```bash
# 1. Start the stack (includes DB seeding + app)
docker-compose up -d

# 2. Wait for app to be ready
curl -sf http://localhost:8080/index.php > /dev/null

# 3. Run E2E tests (headless by default)
cd e2e
npm ci
npx playwright install --with-deps chromium
npm test

# Or run headed (see browser + interact)
npm run test:headed

# View report after failure
npm run report
```

**Run in CI:**
- Trigger: same as other tiers
- Stack: Docker Compose (started in previous job)
- Runtime: not reverified this session; treat prior "~90s" figure as a CI-runner estimate
- Required to pass: ✓ YES (blocks merge)
- Artifacts: Playwright HTML report + video on failure (retained 14 days)

**Health Metrics:**
- Count: 7 files, ~20 test scenarios (design-time count; not currently runnable end-to-end — see Status above)
- Coverage (as designed): User journeys (registration, entry creation, editing, payment), auth flows, admin actions, Judging, Export
- Flakiness: Not assessable until the HTTPS blocker is fixed
- Status: 🔴 BROKEN (see above)

**Test Strategies Used:**
- Dual-path verification: run same scenario on legacy + modern routes, assert identical DB state
- Transactional consistency: create user, make changes, verify audit log
- Error flows: test closed entry window, limit reached, validation errors

---

## Test Execution Matrix

| Tier | Count | Framework | Database | HTTP | CI Runtime | Local Cmd |
|------|-------|-----------|----------|------|------------|-----------|
| **Unit** | 48 files, 577 tests | PHPUnit | ✗ (mostly — see Tier 1 note) | ✗ | not reverified this session | `phpunit --testsuite Unit` |
| **Integration** | 19 files, 128 tests | PHPUnit | ✓ (InnoDB rollback) | ✗ | not reverified this session | `phpunit --testsuite Integration` |
| **Approval** | 5 files, 47 tests | PHPUnit | mostly ✗ (1 file needs DB — see Tier 3 note) | ✗ | not reverified this session | `phpunit --testsuite Approval` |
| **E2E** | 7 files, ~20 scenarios | Playwright | ✓ (Docker) | ✓ | 🔴 currently broken — see Tier 4 | `cd e2e && npm test` |
| **TOTAL** | **79 files, 752+ PHPUnit tests** | — | — | — | — | — |

CI runtime figures from the previous version of this doc (~30s/~60s/~20s/~90s, ~3.3min total) were not reverified this session and are left out rather than repeated as fact — check the most recent GitHub Actions run for current numbers.

---

## CI Workflow: `.github/workflows/ci.yml`

### Job 1: Static Analysis + Unit (no Docker needed)

```yaml
static-and-unit:
  - Checkout code
  - Setup PHP 8.2 + extensions
  - composer install (clean vendor)
  - PHPStan analysis
  - phpunit --testsuite Unit
```

**Correction:** this doc previously said "PHPStan analysis (level 8)." `phpstan.neon` is actually configured at **level 0**. This is a known, deliberate-or-not policy gap flagged in `Docs/PHASE_3_TRUST_AUDIT.md` — not something this correction pass decided to change, just to report accurately.

**Gate:** Must pass before proceeding to DB-dependent tiers.

**Blocks Merge If:** PHPStan error, Unit test fails, risky test detected.

---

### Job 2: Integration + Approval + E2E (150s, Docker required)

```yaml
integration-and-e2e:
  - Checkout code
  - Setup PHP 8.2 + Node 20
  - composer install (clean vendor)
  - docker compose up -d --build
  - Wait for app ready (curl loop)
  - phpunit --testsuite Integration (in container)
  - phpunit --testsuite Approval (in container)
  - npm install + playwright install
  - npm test (E2E on host → docker app)
  - Upload Playwright report on failure
```

**Dependencies:** Requires Job 1 (static-and-unit) to pass.

**Blocks Merge If:** Any test suite fails, app fails to start, Playwright times out. **Note:** if CI's Playwright run hits the same `.htaccess`/TLS issue found locally this session (Tier 4), this job would currently be failing on every push — check the Actions tab rather than assuming either way.

---

### Job 3: Build & Package (conditional, only on tags/releases)

```yaml
build:
  - Checkout code
  - Derive version from git tag
  - Build Docker image (push to GHCR)
  - composer install --no-dev (production only)
  - Package shared-hosting zip
  - Upload artifacts
```

**Trigger:** Only if both Job 1 + Job 2 pass AND ref is a version tag or release event.

**Artifacts:** 
- Docker image at `ghcr.io/geoffhumphrey/brewcompetitiononlineentry:v1.2.3`
- Shared-hosting zip at `bcoem-v1.2.3.zip`

---

## Testing Health Scorecard

### Coverage

| Area | Tier | Status | Notes |
|------|------|--------|-------|
| **Authorization** | Unit + E2E (design) | ✓ Unit complete; E2E unverified | Middleware + central policy tested at Unit level; security-invariants E2E exists but is currently blocked (Tier 4) |
| **Entry Workflow** | Unit + Integration + E2E (design) | ✓ Complete below E2E | Create/read/update/delete; audit logging — all verified against a real DB this session after fixing real bugs in `EntryRepository` |
| **Judging Workflow** | Unit + Integration + E2E (design) | ✓ Complete below E2E | Was "E2E only" as of the previous version of this doc — that was already stale; Unit + Integration coverage has existed since Phase 3.2. The bigger gap found this session: the HTTP layer (controller, routes) was never wired up at all until 2026-07-21. |
| **Legacy Compatibility** | Approval + E2E (design) | ✓ Approval complete; E2E unverified | Snapshot tests verified passing; entrant-journey E2E exists but is blocked (Tier 4) |
| **Database Schema** | Integration | ✓ Complete (migration test) | PhinxMigrationTest asserts migrated columns/indexes exist on a real DB; also caught (this session) that Phase 3.3's own migration built the wrong tables entirely |
| **API Parameterization** | Unit + Integration | ✓ Complete | Connection wrapper enforces prepared statements |
| **Export Workflow** | Unit + Integration + E2E (design) | ✓ Complete below E2E | Was "not yet extracted" as of the previous version — Phase 3.4 landed with real Unit/Integration coverage. Open issue: `comp_id` filter references a column that doesn't exist in this schema. |
| **AdminPreferences** | Unit + Integration | ⚠ Domain only | No controller/routes exist — not reachable via HTTP yet |

### Flakiness & Reliability

| Tier | Flakiness | Root Cause | Mitigation |
|------|-----------|-----------|------------|
| **Unit** | None | Deterministic, no I/O | ✓ Stable |
| **Integration** | Low | Transactional isolation covers PHPUnit-originated rows; does not cover rows a separate Playwright run commits to the same DB (see Tier 2 note) | ⚠ Reseed DB between local Playwright and PHPUnit runs |
| **Approval** | None | Deterministic output | ✓ Stable |
| **E2E** | N/A | Every spec currently fails before reaching any assertion (HTTPS/TLS misconfiguration, see Tier 4) | 🔴 Not assessable until fixed |

**Incident History (see `TESTING_HEALTH_DASHBOARD.md` for full detail on each):**
- 2026-07-21: `EntryRepository` used a fabricated `brewID` column and misread `brewBrewerID`'s casing — `getById()`, `update()`, `delete()` were all broken. Fixed.
- 2026-07-21: The entire Slim routing table was broken (registration-order conflict with the SEF catch-all route, plus a DI-Bridge route-argument binding mismatch) — affected `/entries` and `/export`, not just the Judging routes being wired up at the time. Fixed.
- 2026-07-21: Phase 3.3's AdminPreferences migration built audit columns onto the wrong (legacy) tables; the tables the repository actually needs didn't exist. Fixed.
- Pre-Phase 3: Legacy warnings (undefined array keys in lib) — not test failures, surfaced but don't block

### Recent Changes

| Date | Change | Impact |
|------|--------|--------|
| 2026-07-21 | Phase 3.2 (Judging), 3.3 (AdminPreferences), 3.4 (Export) domain tests landed | Unit file count grew 25 → 48; Integration 14 → 19 |
| 2026-07-21 | Wired up Judging's HTTP layer (was never DI-registered or routed); found and fixed the routing-table and route-argument-binding bugs described above | `/entries`, `/export`, and all new `/judging/*` routes now dispatch correctly |
| 2026-07-21 | Corrected this doc and its companions (`TESTING_HEALTH_DASHBOARD.md`, `TESTING_RUNBOOKS.md`) — prior versions significantly understated test counts, misstated the PHPStan level, and claimed E2E was GREEN when it's currently broken | Documentation accuracy only |
| 2026-07-20 | PHPStan baseline established at **level 0** (not level 8 as previously stated in this doc) | — |
| 2026-07-19 | Phase 2 merge: added middleware/auth tests | +10 unit test files |

---

## Running Tests Locally: Quick Reference

### All Tests (Full Suite)
```bash
# Unit + Integration + Approval (no Docker)
php vendor/bin/phpunit

# Add E2E (requires Docker)
docker-compose up -d
# (wait for app to be ready)
cd e2e && npm test
```

### By Tier
```bash
# Unit only (fast, no dependencies)
php vendor/bin/phpunit --testsuite Unit

# Integration only (requires Docker)
docker-compose up -d db
php vendor/bin/phpunit --testsuite Integration

# Approval only (fast)
php vendor/bin/phpunit --testsuite Approval

# E2E only (requires Docker)
docker-compose up -d
cd e2e && npm test

# E2E with UI (headed browser)
cd e2e && npm run test:headed

# View E2E report
cd e2e && npm run report
```

### Watch for Specific Areas
```bash
# Authorization & middleware
php vendor/bin/phpunit tests/Unit/Security --testsuite Unit
php vendor/bin/phpunit tests/Unit/Kernel/Middleware --testsuite Unit

# Entry workflow (Phase 3.1)
php vendor/bin/phpunit tests/Unit/Domain/Entry --testsuite Unit
php vendor/bin/phpunit tests/Integration/Entry --testsuite Integration

# Legacy compatibility
php vendor/bin/phpunit --testsuite Approval
```

---

## What Each Test Detects

### Unit Tests (Catch)
- Logic errors in domain objects (value objects, aggregates)
- Middleware behavior (auth flow, session handling)
- Utility function bugs
- Bad input validation

### Integration Tests (Catch)
- SQL query bugs (joins, filtering, ordering)
- Repository mapping errors (column names, type casting)
- Database schema mismatches
- Transaction atomicity failures
- Business rule violations (limits, constraints)

### Approval Tests (Catch)
- Output formatting regressions (URLs, style names, JSON serialization)
- Accidental changes to legacy formatting
- Color/code lookup table errors

### E2E Tests (Catch)
- User journey failures (forms, redirects, multi-step workflows)
- Authorization enforcement (can't access unauth pages)
- Full-stack integration issues (middleware → controller → view → database)
- Real browser quirks (JavaScript, cookies, DOM events)

---

## Known Limitations & Future Work

### What's NOT Tested (Yet)

1. **Performance** — no load testing in the suite (standalone harness exists in Docs/)
2. **Concurrency** — no multi-request race conditions (tested manually with Docker load tests)
3. **Real HTTP dispatch below E2E** — nothing exercises `buildApp()->handle()` with a real PSR-7 request outside `HelloWorldRouteTest`. This is exactly the gap that let the routing-table and route-argument-binding bugs (see Recent Changes) ship undetected — Unit tests mock everything, and E2E is currently blocked.
4. **`comp_id` / multi-competition support** — Export domain code assumes a `comp_id` column that doesn't exist in this schema; untested because it's broken, not because it was skipped
5. **Search/Filtering** — table search queries not explicitly tested in unit/integration tiers
6. **PDF export** — falls back to CSV; no dedicated PDF generation logic exists yet to test

### Gaps by Phase

| Phase | What's Tested | What's Missing |
|-------|---|---|
| **Phase 1 (Security)** | Password migration, token verification | Rate limiting, CSRF tokens |
| **Phase 2 (Auth)** | Middleware, authorization policy, role mapping | Multi-session edge cases |
| **Phase 3.1 (Entries)** | CRUD, audit logging, validation | Bulk actions, concurrent edits |
| **Phase 3.2 (Judging)** | Aggregate/value-object Unit tests, repository/service Integration tests, HTTP layer now wired (2026-07-21) | Concurrent judge updates, scoring algorithm; still-missing `postCreateTable`/`postUpdateTable`/locations HTTP handlers that templates already assume exist |
| **Phase 3.3 (AdminPreferences)** | Aggregate, value objects, commands, services (Unit); repository against a real DB (Integration) | No controller/routes at all — not reachable via HTTP |
| **Phase 3.4 (Export)** | ExportService, ExportFormatterService (Unit); repositories/service (Integration) | `comp_id` schema mismatch (open bug); PDF generation; large-scale export performance |

### Recommended Next Steps

1. **Fix the E2E HTTPS/TLS blocker** — nothing in Tier 4 is verifiable until this is resolved
2. **Add a request-dispatch smoke test** — something that calls `buildApp()->handle()` with a real PSR-7 request per major route, to catch routing/DI wiring regressions before E2E (which is currently the only tier that would have caught them, and it's blocked)
3. **Resolve the `comp_id` question for Export** — add the column, or remove the filter
4. **Performance Baseline** — run load tests quarterly; track response times
5. **Code Coverage Report** — integrate PCOV into CI; surface coverage metrics (not tracked)
6. **Contract Tests** — if API clients emerge, add contract tests to prevent breaking changes
7. **Mutation Testing** — periodically run Infection to verify test quality (not in every CI run — expensive)

---

## Troubleshooting Test Failures

### "Cannot connect to database" (Integration tests)

```bash
# Check if DB is running
docker-compose ps db

# If not running
docker-compose up -d db

# Verify connectivity
mysql -h 127.0.0.1 -u bcoem -pbcoem_password bcoem
```

### "Playwright timeout waiting for element"

```bash
# Run headed to see what's happening
cd e2e && npm run test:headed

# Check if app is ready
curl -sf http://localhost:8080/index.php

# If not, rebuild and restart
docker-compose down
docker-compose up -d --build
```

### "net::ERR_SSL_PROTOCOL_ERROR" on the very first `page.goto()` (every E2E spec, 2026-07-21)

This is the current known-broken state of the E2E tier locally — not a per-test bug. `.htaccess` unconditionally 301-redirects every request to `https://`, but the Docker vhost never configures a TLS listener, so the redirect target fails.

```bash
# Confirm it's the redirect, not your test:
curl -v http://localhost:8080/index.php
# Look for a 301 to https://... in the response

# There is no fix in this doc yet - it needs either:
# 1. A TLS-terminating reverse proxy in front of the Docker web container, or
# 2. Removing/conditioning the .htaccess HTTPS-force rule for local/CI dev use, or
# 3. Playwright configured to hit a URL scheme that bypasses the redirect entirely
# Check whether GitHub Actions CI is also affected before assuming this is host-specific.
```

### "PHPStan errors" in CI but not locally

```bash
# Likely due to vendor differences; rebuild vendor locally
rm -rf vendor && composer install

# Then run phpstan
vendor/bin/phpstan analyse --no-progress
```

### "Snapshot mismatch" (Approval tests)

```bash
# Check the diff carefully
php vendor/bin/phpunit tests/Approval/YourTest.php

# If change is intentional:
# 1. Update the snapshot file in tests/Approval/__snapshots__/
# 2. Re-run test to confirm
# 3. Commit snapshot changes
```

---

## Resources

- **PHPUnit Docs:** https://phpunit.de/documentation.html
- **Playwright Docs:** https://playwright.dev/
- **Approval Testing:** https://approvaltests.com/
- **BCOEM CI:** `.github/workflows/ci.yml`
- **Load Testing:** `Docs/docker-loadtest-runbook.md`
- **Integration Test Setup:** `tests/Integration/README.md`

