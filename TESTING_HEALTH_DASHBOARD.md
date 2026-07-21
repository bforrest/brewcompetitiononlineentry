# Testing Health Dashboard

**Last Updated:** 2026-07-21 (corrected — previous version was stale: test counts, PHPStan level, and E2E status were all wrong)  
**Status:** ⚠ MIXED — Unit/Integration/Approval verified passing; E2E currently broken (see Tier 4)

---

## Summary

| Metric | Value | Trend | Status |
|--------|-------|-------|--------|
| **Total Test Files** | 79 (72 PHP: 48 Unit + 19 Integration + 5 Approval, 7 TS/E2E) | ↑ Phase 3.2/3.3/3.4 additions | ✓ |
| **Total Assertions** | 577 Unit + 271 Integration + 102 Approval = 950+ | ↑ substantially since last count | ✓ |
| **CI Pass Rate (Last 30 runs)** | Not reverified this session — check GitHub Actions directly | — | ⚠ |
| **Average CI Duration** | Not reverified this session (local runs are sub-3s per tier on this dev machine, but that's not representative of a cold CI runner) | — | ⚠ |
| **Flaky Tests** | 0 known | ← Stable | ✓ |
| **Code Coverage** | Not tracked | ← TODO | ⚠ |
| **SQLi Vulnerabilities** | 173/189 fixed (stale figure, not reverified this session — Phase 3.4 Export hardening fixed 11 more separately; total needs recount) | — | ⚠ |
| **PHPStan Level** | **0**, not 8 (see `phpstan.neon`) — a known, deliberate policy gap flagged in `Docs/PHASE_3_TRUST_AUDIT.md`, not yet decided | — | ⚠ |

---

## Tier Health

### ✓ Tier 1: Unit Tests

**Status:** GREEN

| Check | Pass | Notes |
|-------|------|-------|
| All unit tests pass | ✓ | 48 files, 577 tests, 943 assertions |
| PHPStan clean | ✓ | Configured at **level 0** (`phpstan.neon`), not level 8 as previously stated here — see `Docs/PHASE_3_TRUST_AUDIT.md` for the open policy question of whether to raise it |
| Deterministic (no flakes) | ✓ | No I/O, no timing deps |
| Fast execution | ✓ | Sub-3s on a warm local machine; CI runtime not reverified this session |
| No external deps | ✓ | Mocks all I/O |
| Known baseline error/failures | ⚠ | 1 error + 2 failures, pre-existing and environmental (missing DB/OTel extension in the bare Unit-tier sandbox — see `HelloWorldRouteTest`, `SessionMiddlewareTest`), not regressions |

**Coverage:**
- ✓ Domain value objects (Entry, EntryId, BrewerId, StyleNumber, BrewerInfo)
- ✓ AdminPreferences domain (aggregate, value objects, commands, services — Phase 3.3)
- ✓ Judging domain (JudgingTable aggregate, scores, flights — Phase 3.2)
- ✓ Export domain (ExportService, ExportFormatterService, commands — Phase 3.4)
- ✓ Authorization & middleware (Auth, Session, Tracing, Errors)
- ✓ Security policies (Role mapping, Identity, AccessPolicy)
- ✓ Utility functions (dates, conversion, crypto, URLs, strings)

**Recent Changes:**
- 2026-07-21: Phase 3.2 (Judging), 3.3 (AdminPreferences), 3.4 (Export) domain unit tests landed — unit file count grew from 25 to 48
- 2026-07-21: A prior code-review pass found and fixed ~40 unit tests that had never actually executed (final-class mocking failures, stale constructor signatures) — see `Docs/PHASE_3_TRUST_AUDIT.md`

---

### ✓ Tier 2: Integration Tests

**Status:** GREEN (0 errors as of this correction; 2 pre-existing failures, see below)

| Check | Pass | Notes |
|-------|------|-------|
| All integration tests pass | ⚠ | 19 files, 128 tests, 271 assertions. 0 errors, 2 failures — both `TotalFeesTest` picking up real rows an earlier local Playwright run committed to this dev DB (documented gotcha: e2e and PHPUnit fee tests must not share a DB), not a code regression. Reseed the DB to clear them. |
| DB connectivity reliable | ✓ | Docker MariaDB InnoDB, transactional rollback |
| Deterministic isolation | ✓ | Each test = one transaction, rollback after |
| Schema up-to-date | ✓ | Migrations applied — audit_log (Phase 3.1), judging indexes (Phase 3.2), admin_preferences (Phase 3.3) |
| No test data leakage | ⚠ | Orphan sweep + rollback covers PHPUnit-originated rows; does not cover rows committed by a Playwright run against the same DB (see failures above) |
| Execution time | ✓ | Sub-3s on a warm local machine; CI runtime not reverified this session |

**Coverage:**
- ✓ EntryRepository CRUD (Phase 3.1): insert, update, delete, select, count
- ✓ EntryService workflow (Phase 3.1): create, update, delete with validation & audit
- ✓ AuditLogger (Phase 3.1): transactional logging of all changes
- ✓ AdminPreferencesRepository (Phase 3.3): getById self-heal, save, recordEvent — against the real `admin_preferences`/`admin_preferences_events` tables
- ✓ Export repositories (Phase 3.4): BrewingExportRepository, ExportService — filters, archive-suffix validation
- ✓ Legacy functions: brewer info, fees, scoring, tokens, passwords
- ✓ Database: migrations, schema integrity, indexes (PhinxMigrationTest now also asserts admin_preferences/admin_preferences_events)

**Recent Changes:**
- 2026-07-21: Phase 3.3's DB migration was rebuilding audit columns on the *wrong* legacy tables; the actual `admin_preferences`/`admin_preferences_events` tables the repository needs didn't exist anywhere. Fixed, with a new integration test that would have caught it on day one.
- 2026-07-21: `EntryRepository` was querying a fabricated `brewID` column (the real primary key is `id`) and misreading `brewBrewerID`'s casing — `getById()`, `update()`, and `delete()` were all broken. Fixed; this is the first time these repository's own integration tests have run to completion.
- 2026-07-21: Two new Export integration test files were fatally broken in `setUp()` (non-nullable `\mysqli` property assigned from a possibly-null global; a malformed Symfony validator construction) — fixed.

**Known Issues:**
- **Open, real bug**: Export repositories (`BrewingExportRepository`, `ParticipantExportRepository`, `JudgingExportRepository`) unconditionally query a `comp_id` column that doesn't exist anywhere in this schema — the legacy code only ever touches it behind an `if (SINGLE)` gate that's `false` for this install. Needs a product decision (add the column, or drop the filter). One integration test is explicitly skipped citing this.
- A Phase 3.2 migration (`20260721160003_add_judging_indexes.php`) used an invalid Phinx `addIndex()` option and indexed a nonexistent column — this silently blocked *every migration after it* from ever applying to a real database. Fixed 2026-07-21.

---

### ✓ Tier 3: Approval Tests

**Status:** GREEN

| Check | Pass | Notes |
|-------|------|-------|
| All approval tests pass | ✓ | 5 test classes, 47 tests, 102 assertions |
| Snapshots committed | ✓ | All snapshot files in git |
| No unexpected changes | ✓ | No pending snapshot diffs |
| Fast execution | ✓ | Sub-1s on a warm local machine |
| Regressions caught | ✓ | Format changes require approval + commit |
| DB-free | ✗ | Corrected: this tier is **not** fully DB-free as previously claimed — `EntryInfoApprovalTest` hits the real database (fails with "No database selected" if run without a DB connection available) |

**Coverage:**
- ✓ URL generation (links, SEO-friendly URLs, query strings)
- ✓ Entry serialization (JSON, CSV formats)
- ✓ SRM/EBC color codes (lookup table)
- ✓ Style conversions (legacy ↔ BJCP 2021)
- ✓ Category formatting (category names, abbreviations)

**Recent Changes:**
- No functional changes; corrected this doc's claim that the tier has no DB dependency

---

### 🔴 Tier 4: E2E Tests

**Status:** BROKEN (verified locally 2026-07-21) — previously marked GREEN, which was inaccurate for the current state of this repo

| Check | Pass | Notes |
|-------|------|-------|
| All Playwright tests pass | ✗ | **Every spec fails at the first navigation.** `.htaccess` unconditionally redirects every HTTP request to HTTPS (`RewriteCond %{HTTPS} off` → 301), but the Docker vhost (`docker/apache/vhost.conf`) never configures a TLS listener — the redirect target throws `ERR_SSL_PROTOCOL_ERROR`. Confirmed against `smoke.spec.ts` (the simplest possible spec) and `export-dual-path.spec.ts` locally; this is an infrastructure issue, not a test-logic issue. |
| Browser compatibility | — | Not verifiable until the above is fixed |
| Stack integration | ✓ | Docker app + DB + host browser containers all start and are reachable |
| Timeout resilience | — | Not verifiable until the above is fixed |
| Report artifacts | ✓ | HTML report + video on failure still generate correctly for the failures above |
| File count | ✓ | 7 files (not 5 as previously stated): `admin-journey`, `dual-path-verification`, `entrant-journey`, `export-dual-path`, `judging-dual-path`, `security-invariants`, `smoke` |

**Coverage (as designed — currently unverifiable end-to-end due to the above):**
- **Entrant journey (legacy):** register → create entry → edit → list → payment
- **Entrant journey (modern):** same flow via /entries routes
- **Dual-path verification:** same user action on legacy vs modern, verify identical DB state (3 separate specs now: general, export, judging)
- **Admin journey:** login → create judging table → score entries
- **Security invariants:** unauth access blocked, role enforcement
- **Smoke tests:** homepage loads, login visible

**Not independently reverified this session:** whether GitHub Actions CI currently passes this tier. The `.htaccess` rule is a committed, shared file, so if CI uses the same Docker stack it should hit the identical redirect — but CI's network path may differ enough (or this may be a recent regression) that it's still green there. **Recommend checking the most recent `integration-and-e2e` job run in GitHub Actions before trusting either this doc's old "GREEN" claim or assuming CI is also broken.**

**Recent Changes:**
- 2026-07-21: Fixed two real bugs found while trying to get `export-dual-path.spec.ts` running: an invalid `expect(page).toContainText(...)` Playwright matcher, and a fake-cookie auth stub that never actually authenticated (replaced with the real `loginAsAdmin` helper). Neither fix was verifiable end-to-end locally because of the HTTPS issue above.

**Recent Changes:**
- 2026-07-21: Added `judging-dual-path.spec.ts` and `export-dual-path.spec.ts` (Phase 3.2/3.4)
- 2026-07-21: `export-dual-path.spec.ts`'s auth setup was a fake cookie that never authenticated, and it hardcoded a `/bcoem` path prefix that doesn't exist in this Docker setup (the app is mounted at root — see `docker/apache/vhost.conf`). Fixed both, but see the HTTPS blocker above — unverified end-to-end.

---

## Coverage Map: Features vs Tests

| Feature | Unit | Integration | Approval | E2E | Status |
|---------|------|-------------|----------|-----|--------|
| **Entry Create/Read/Update/Delete** | ✓ StyleNumber | ✓ Repository/Service | — | Designed, unverified (E2E blocked) | ✓ FULL (below E2E) |
| **Authorization & Middleware** | ✓ Auth/Roles | — | — | Designed, unverified (E2E blocked) | ✓ FULL (below E2E) |
| **Entry Window Validation** | — | ✓ Service | — | Designed, unverified (E2E blocked) | ✓ GOOD |
| **Entry Limit Checks** | — | ✓ Service | — | Designed, unverified (E2E blocked) | ✓ GOOD |
| **Audit Logging** | — | ✓ AuditLogger | — | Designed, unverified (E2E blocked) | ✓ GOOD |
| **Brewer Data Fetching** | — | ✓ BrewerInfo | — | — | ✓ GOOD |
| **Scoring & Points** | — | ✓ BestBrewPoints | ✓ Display Place | — | ⚠ PARTIAL |
| **Style Conversions** | — | — | ✓ Snapshots | — | ✓ GOOD |
| **Fee Calculations** | — | ✓ TotalFees | — | — | ✓ GOOD |
| **Export (CSV/PDF)** | ✓ ExportService/Formatter | ✓ Repository/Service | — | Designed, unverified (E2E blocked) | ✓ GOOD; PDF still falls back to CSV (Phase 3.5) |
| **Judging Workflow** | ✓ JudgingTable/Score | ✓ Table/Score repositories | — | Designed, unverified (E2E blocked) | ✓ GOOD; controller/routes were completely unwired until 2026-07-21 |
| **AdminPreferences** | ✓ Aggregate/commands/services | ✓ Repository (real DB) | — | — | ✓ GOOD; no controller/routes yet — not reachable via HTTP |
| **Preferences & Registration (legacy)** | — | — | — | Designed, unverified (E2E blocked) | ⚠ PARTIAL |

**Corrections from the prior version of this table:** Export and Judging were marked "TODO"/"E2E only" — both now have substantial Unit + Integration coverage (Phases 3.2 and 3.4 landed). The E2E column across the board was overstating confidence: every spec is currently blocked by the HTTPS issue in Tier 4 above, so "journey covered" claims are about test *design*, not verified passing runs.

---

## Metrics Tracking

### Test Execution Times

**Not reverified this session for CI.** Local runs on this dev machine (warm, no fresh `composer install`) are much faster than the previous figures here (Unit and Integration both complete in ~2-3s locally), but that's not a fair proxy for a cold GitHub Actions runner. Treat the numbers below as historical/CI-oriented estimates, not current measurements:

- Unit: ~30s (CI estimate, unverified)
- Integration: ~60s (CI estimate, unverified)
- Approval: ~20s (CI estimate, unverified)
- E2E: currently N/A — every spec fails immediately (see Tier 4)

**Target:** <5 min (acceptable for pre-merge gate)

**If Exceeds:** Investigate slow tests, consider splitting into sub-tiers

### CI Pass Rate

**Not reverified this session.** The figures below (100% across master/slim/PRs) were not re-checked against GitHub Actions and should not be trusted given Tier 4 is confirmed broken locally using the same committed `.htaccess`/Docker config CI uses — **check the Actions tab directly** before relying on this number.

**If Falls Below 95%:** Incident review required

### Flaky Tests

**Known Flakes:** 0

**If Any Detected:**
1. Log the flake (test name, error, branch)
2. Add tags: `@flaky @<root_cause>`
3. Reduce timeout or add retry logic
4. Investigate root cause (timing, async I/O, DB load, etc.)

### Code Coverage

**Status:** Not tracked (TODO)

**Recommended Approach:**
1. Enable PCOV extension in phpunit.yml
2. Generate coverage report: `--coverage-html coverage/`
3. Track coverage % per tier (Unit: >80%, Integration: >60%)
4. Block if coverage drops >5%

---

## Phase Progress: Testing Tiers

| Phase | Entry | Exit | Tests Added | Status |
|-------|-------|------|-------------|--------|
| **Phase 0** | 2026-07-18 | 2026-07-19 | Unit + Approval basics | ✓ Complete |
| **Phase 1** | 2026-07-19 | 2026-07-20 | Unit (security/auth) | ✓ Complete |
| **Phase 2** | 2026-07-20 | 2026-07-21 | Unit (middleware) | ✓ Complete |
| **Phase 3.1** | 2026-07-21 | 2026-07-21 | Unit + Integration + E2E (Entry) | ✓ Complete |
| **Phase 3.2** | 2026-07-21 | 2026-07-21 | Unit + Integration (Judging) | ✓ Landed, but its HTTP layer (controller wiring, routes) was never actually connected until a same-day follow-up fix — see incidents below |
| **Phase 3.3** | 2026-07-21 | 2026-07-21 | Unit + Integration (AdminPreferences) | ✓ Domain layer landed; its own DB migration built the wrong tables until fixed same-day; no controller/routes exist yet, not reachable via HTTP |
| **Phase 3.4** | 2026-07-21 | 2026-07-21 | Unit + Integration (Export) | ✓ Landed; PDF format still falls back to CSV; `comp_id` filter references a column that doesn't exist in this schema (open) |

**Correction:** the previous version of this table showed 3.2-3.4 as "TBD/Planned." All three had already landed (with real, previously-undiscovered bugs of their own) by the time this correction was made.

---

## Issues & Incidents

### Resolved (2026-07-21)

**Incident: EntryRepository Column Mismatches (round 1)**
- **Symptom:** Integration tests failing: "Unknown column 'br.first_name'"
- **Root Cause:** Repository using wrong column names (brewerFirstName instead of first_name for JOIN aliases)
- **Fix:** Added SQL aliases in SELECT statements
- **Lessons:** Schema review early; test against real DB columns

**Incident: EntryRepository Column Mismatches (round 2 — the "brewID"/"brewID" fix above didn't actually fix it)**
- **Symptom:** `getById()`, `update()`, `delete()` all threw `mysqli_sql_exception: Unknown column 'b.brewID'` the first time these integration tests ran to completion against a real DB.
- **Root Cause:** Every query in `EntryRepository` referenced a fabricated `brewID` column; the real primary key is just `id`. A second, separate bug: `$row['brewBrewerId']` (lowercase d) was read from a fetched row whose real column is `brewBrewerID` (capital ID) — MySQL resolves column names case-insensitively in the query itself, but the PHP array key from the fetched row matches the schema's declared casing exactly, so the read silently missed.
- **Fix:** Corrected both column-name references throughout `EntryRepository`.
- **Lessons:** Repository-level integration tests had never actually reached these code paths before (masked by other bugs); "the test file exists" isn't the same as "the test ever ran."

**Incident: Entire Slim routing table was broken (not Judging-specific)**
- **Symptom:** Wiring up Phase 3.2's `JudgingController` (which had never been registered to any route) surfaced that `/entries`, `/export`, and even `/__kernel_hello` all returned HTTP 500 too — confirmed via `git stash` that this predates today's changes.
- **Root Cause:** Two independent bugs: (1) FastRoute requires static routes registered before any variable/catch-all route that could match the same shape; the SEF catch-all was registered first. (2) DI-Bridge's `ControllerInvoker` binds route placeholders as individually-named parameters, not Slim's usual `$args` array — every route with a URL parameter was broken.
- **Fix:** Reordered route registration; converted controller signatures to match DI-Bridge's actual binding convention.
- **Lessons:** No test exercised routes through the real Slim dispatch pipeline before this — Unit tests mock everything, and E2E is blocked (see Tier 4). Middle-layer coverage (dispatch a real PSR-7 request through `buildApp()->handle()`) is a real gap.

**Incident: AdminPreferences migration built the wrong tables**
- **Symptom:** Every real call to `AdminPreferencesRepository::getById()`/`save()` threw "table doesn't exist."
- **Root Cause:** Phase 3.3's migration added audit columns to the legacy `preferences`/`judging_preferences` tables; the actual `admin_preferences`/`admin_preferences_events` tables the repository reads/writes were never created by any migration.
- **Fix:** Added the correct migration, plus an integration test that exercises it against a real DB.

### Open

- **`comp_id` schema mismatch (Phase 3.4 Export):** repositories unconditionally query a column that doesn't exist in this schema. Needs a product decision.
- **E2E tier blocked:** `.htaccess` forces HTTPS with no TLS listener configured in the Docker dev stack. See Tier 4 above.
- **Missing Judging HTTP handlers:** `templates/Judging/table-form.php` POSTs to endpoints (`postCreateTable`, `postUpdateTable`) and links to a `/judging/locations` page that don't exist in `JudgingController` — flagged, not fabricated.

---

## Recommendations & Action Items

### Immediate

- [x] Create TESTING.md overview
- [x] Create TESTING_RUNBOOKS.md
- [x] Create this health dashboard
- [x] Judging integration tests exist now (`JudgingTableRepositoryIntegrationTest`, `JudgingScoreServiceIntegrationTest`)
- [ ] **Fix the E2E HTTPS/TLS blocker** — nothing in Tier 4 can be verified until this is resolved
- [ ] Decide the `comp_id` question for Export (add the column, or drop the filter)
- [ ] Reseed the local dev DB — `TotalFeesTest` is currently reading rows a stray Playwright run committed

### Short-Term

- [ ] Enable code coverage tracking in CI
- [ ] Set coverage baselines (Unit >80%, Integration >60%)
- [ ] Document how to add/maintain approval test snapshots
- [ ] Add contract tests if API clients emerge
- [ ] Add a test tier (or at least a smoke check) that dispatches real requests through `buildApp()->handle()` — nothing currently catches routing/DI wiring bugs like the ones found and fixed 2026-07-21, since Unit tests mock everything and E2E is blocked

### Medium-Term

- [ ] Add load testing to CI (optional, non-blocking tier)
- [ ] Implement mutation testing (quarterly, not in every CI run)
- [ ] Add performance regression detection (response times baseline)
- [ ] Build the missing Judging HTTP handlers (create/update table, locations page) that templates already assume exist

### Long-Term

- [ ] Consider test matrix for multiple PHP/MySQL versions
- [ ] Add visual regression testing for UI components (Playwright visual)
- [ ] Implement chaos engineering tests (random DB failures, etc.)
- [ ] Archive old test reports for trend analysis

---

## Checklist: Before Each Release

- [ ] All 4 test tiers passing locally & in CI (Tier 4 is currently unverifiable locally — see above)
- [ ] Code coverage >80% (Unit), >60% (Integration)
- [ ] No flaky tests in last 10 CI runs
- [ ] Approval snapshots intentionally committed (if any changes)
- [ ] E2E journey covers both legacy + modern routes
- [ ] Performance unchanged from baseline (<5% regression)
- [ ] Migration validity test passing (PhinxMigrationTest — now also covers admin_preferences/admin_preferences_events)

---

## Quick Links

- **Full Testing Guide:** [TESTING.md](TESTING.md)
- **Runbook & Debugging:** [TESTING_RUNBOOKS.md](TESTING_RUNBOOKS.md)
- **CI Pipeline:** [.github/workflows/ci.yml](.github/workflows/ci.yml)
- **PHPUnit Config:** [phpunit.xml](phpunit.xml)
- **Playwright Config:** [e2e/playwright.config.ts](e2e/playwright.config.ts)
- **Test Code:** [tests/](tests/) and [e2e/tests/](e2e/tests/)

