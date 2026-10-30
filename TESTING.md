# Testing Overview & Health Report

**Last Updated:** 2026-07-21  
**Status:** Phase 3.1 complete; 44 test files (39 PHP, 5 TypeScript)

## Testing Tiers

BCOEM uses a **4-tier testing pyramid** designed to catch bugs at the right level while keeping iteration fast.

### Tier 1: Unit Tests (DB-Free, No Dependencies)

**Purpose:** Fast feedback on isolated logic (no database, no HTTP).

**Location:** `tests/Unit/`

**Test Count:** ~25 files across:
- `Security/` — Role mapping, Identity, AccessPolicy enforcement
- `Kernel/Middleware/` — Auth, session, tracing middleware
- `Kernel/` — error handling, logging
- `Legacy/` — legacy file/process handlers
- `Domain/Entry/` — ValueObjects (EntryId, StyleNumber, BrewerInfo)
- Utility functions (dates, conversion, crypto, URLs)

**Database:** No. Tests stub out all DB dependencies in `tests/bootstrap.php`.

**Run Locally:**
```bash
php vendor/bin/phpunit --testsuite Unit
```

**Run in CI:** 
- Trigger: every push to `master`, `develop`, `docker-baseline-db`
- Trigger: every pull request
- Runtime: ~30s (bare Ubuntu runner, no docker)
- Required to pass: ✓ YES (blocks merge)

**Health Metrics:**
- Count: 25 files, ~150+ assertions
- Coverage: All domain value objects, middleware logic, utility functions
- Flakiness: None known (deterministic, no timing deps)
- Status: ✓ STABLE

---

### Tier 2: Integration Tests (Real Database, No HTTP)

**Purpose:** Verify business logic against live DB (detect schema mismatches, query bugs).

**Location:** `tests/Integration/`

**Test Count:** ~14 files:
- `Entry/` — EntryRepository CRUD, EntryService workflow, AuditLogger (9 tests)
- `BrewerInfoTest.php` — brewer data fetching
- `BestBrewerPointsTest.php` — scoring logic
- `PasswordLegacyMigrationTest.php` — password verification
- `VerifyTokenTest.php` — security tokens
- `DisplayPlaceTest.php` — placement calculations
- `GetTableInfoTest.php` — schema introspection
- `TotalFeesTest.php` — fee calculations
- `PhinxMigrationTest.php` — migration validity
- `ErrorHandlingTest.php` — error recovery

**Database:** YES. Uses Docker MariaDB (InnoDB) with **transactional rollback isolation**.
- Each test runs in a transaction
- Rolls back after test completes (no data persists)
- One-time orphan sweep before class runs (cleans up from stale runs)

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
- Trigger: same as Unit (every push/PR to main branches)
- Database: Docker service (started before tests)
- Runtime: ~60s (includes MariaDB startup + test execution)
- Required to pass: ✓ YES (blocks merge)

**Health Metrics:**
- Count: 14 files, ~80+ assertions
- Coverage: All repository queries, service workflows, business logic
- Flakiness: Minimal (transactional isolation prevents interference)
- Status: ✓ STABLE (but see "Known Issues" below)

**Known Issues:**
- Column name mapping: phew (fixed). EntryRepository was using wrong column names for brewer table (brewerFirstName vs first_name). Fixed with SQL aliases in Phase 3.1.
- Migration validation: PhinxMigrationTest checks that pending migrations exist and parse, but doesn't validate migration logic semantics (too expensive to run all migrations in tests).

---

### Tier 3: Approval Tests (Read-Only Snapshots, No HTTP)

**Purpose:** Regression detect on formatted output (SRM color tables, style conversions, entry info formatting).

**Location:** `tests/Approval/`

**Test Count:** 6 files with 40+ snapshot tests:
- `LinkBuilderApprovalTest.php` — HTML link generation (URL building, parameters)
- `EntryInfoApprovalTest.php` — entry DTO serialization (JSON, CSV)
- `SrmColorApprovalTest.php` — SRM/EBC color code lookup tables
- `StyleConvertApprovalTest.php` — BJCP style mapping (legacy → 2021, etc.)
- `StyleTypeApprovalTest.php` — style category formatting

**Database:** NO (read-only test data hardcoded).

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
- Runtime: ~20s
- Required to pass: ✓ YES (blocks merge)
- Special: Snapshot files must be committed (diff shows intent)

**Health Metrics:**
- Count: 6 files, 40+ snapshot tests
- Coverage: Output formatting, legacy compatibility layers
- Flakiness: None (deterministic output)
- Status: ✓ STABLE

---

### Tier 4: E2E Tests (Full Stack, Real HTTP, Real Browser)

**Purpose:** End-to-end user journeys (form submission, auth flows, multi-step actions).

**Location:** `e2e/tests/`

**Test Count:** 5 files with ~15 test scenarios:
- `entrant-journey.spec.ts` — legacy entry creation/edit flow + modern /entries routes
- `dual-path-verification.spec.ts` — same scenario on legacy vs modern routes (regression detect)
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
- Runtime: ~90s (app startup + test execution)
- Required to pass: ✓ YES (blocks merge)
- Artifacts: Playwright HTML report + video on failure (retained 14 days)

**Health Metrics:**
- Count: 5 files, ~15 test scenarios
- Coverage: User journeys (registration, entry creation, editing, payment), auth flows, admin actions
- Flakiness: Low (robust selectors, proper waits)
- Status: ✓ STABLE

**Test Strategies Used:**
- Dual-path verification: run same scenario on legacy + modern routes, assert identical DB state
- Transactional consistency: create user, make changes, verify audit log
- Error flows: test closed entry window, limit reached, validation errors

---

## Test Execution Matrix

| Tier | Count | Framework | Database | HTTP | CI Runtime | Local Cmd |
|------|-------|-----------|----------|------|------------|-----------|
| **Unit** | 25 files | PHPUnit | ✗ | ✗ | ~30s | `phpunit --testsuite Unit` |
| **Integration** | 14 files | PHPUnit | ✓ (InnoDB rollback) | ✗ | ~60s | `phpunit --testsuite Integration` |
| **Approval** | 6 files | PHPUnit | ✗ | ✗ | ~20s | `phpunit --testsuite Approval` |
| **E2E** | 5 files | Playwright | ✓ (Docker) | ✓ | ~90s | `cd e2e && npm test` |
| **TOTAL** | **50 files** | — | — | — | **~200s (~3.3min)** | — |

---

## CI Workflow: `.github/workflows/ci.yml`

### Job 1: Static Analysis + Unit (30s, no Docker needed)

```yaml
static-and-unit:
  - Checkout code
  - Setup PHP 8.2 + extensions
  - composer install (clean vendor)
  - PHPStan analysis (level 8)
  - phpunit --testsuite Unit
```

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

**Blocks Merge If:** Any test suite fails, app fails to start, Playwright times out.

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
| **Authorization** | Unit + E2E | ✓ Complete | Middleware + central policy tested; security-invariants E2E validates roles |
| **Entry Workflow** | Unit + Integration + E2E | ✓ Complete (Phase 3.1) | Create/read/update/delete; dual-path verification; audit logging |
| **Judging Workflow** | E2E only | ⚠ Partial | admin-journey covers basic flow; detailed scoring not yet isolated |
| **Legacy Compatibility** | Approval + E2E | ✓ Complete | Snapshot tests + entrant-journey validate formatting |
| **Database Schema** | Integration | ✓ Complete (migration test) | PhinxMigrationTest validates migrations parse + run |
| **API Parameterization** | Unit + Integration | ✓ Complete | Connection wrapper enforces prepared statements; PHPStan blocks direct mysqli |

### Flakiness & Reliability

| Tier | Flakiness | Root Cause | Mitigation |
|------|-----------|-----------|------------|
| **Unit** | None | Deterministic, no I/O | ✓ Stable |
| **Integration** | Very Low | Transactional isolation (InnoDB rollback) | ✓ Stable |
| **Approval** | None | Deterministic output | ✓ Stable |
| **E2E** | Low | Async DOM updates, timing | ✓ Playwright waits + robust selectors |

**Incident History:**
- 2026-07-21: Fixed EntryRepository column name mismatches → integration tests now pass
- Pre-Phase 3: Legacy warnings (undefined array keys in lib) — not test failures, surfaced but don't block

### Recent Changes

| Date | Change | Impact |
|------|--------|--------|
| 2026-07-21 | Phase 3.1 merged: added Domain/Entry tests | +8 unit tests, +3 integration test files |
| 2026-07-21 | Added dual-path verification E2E | +1 new spec file (regression detect) |
| 2026-07-20 | PHPStan level 8 baseline | Caught 54 pre-existing errors (most benign) |
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
3. **Judging Workflow Details** — only E2E admin-journey covers it; no isolated integration tests
4. **Export Functionality** — no tests for CSV/PDF generation logic
5. **Search/Filtering** — table search queries not explicitly tested in unit/integration tiers

### Gaps by Phase

| Phase | What's Tested | What's Missing |
|-------|---|---|
| **Phase 1 (Security)** | Password migration, token verification | Rate limiting, CSRF tokens |
| **Phase 2 (Auth)** | Middleware, authorization policy, role mapping | Multi-session edge cases |
| **Phase 3.1 (Entries)** | CRUD, audit logging, validation | Bulk actions, concurrent edits |
| **Phase 3.2 (Judging)** | Admin journey (E2E only) | Concurrent judge updates, scoring algorithm |
| **Phase 3.3 (Prefs)** | Style list, category management (legacy E2E) | Bulk preference changes |
| **Phase 3.4 (Export)** | None (not yet extracted) | CSV/PDF generation, large-scale exports |

### Recommended Next Steps

1. **Add Judging Integration Tests** — extract from admin-journey into isolated CRUD tests (like Entry did in Phase 3.1)
2. **Performance Baseline** — run load tests quarterly; track response times
3. **Code Coverage Report** — integrate PCOV into CI; surface coverage metrics (currently not tracked)
4. **Contract Tests** — if API clients emerge, add contract tests to prevent breaking changes
5. **Mutation Testing** — periodically run Infection to verify test quality (not in every CI run — expensive)

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

