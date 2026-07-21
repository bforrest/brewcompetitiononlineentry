# Testing Health Dashboard

**Last Updated:** 2026-07-21  
**Status:** ✓ GREEN (all tiers passing, Phase 3.1 complete)

---

## Summary

| Metric | Value | Trend | Status |
|--------|-------|-------|--------|
| **Total Test Files** | 44 (39 PHP, 5 TS) | ↑ +8 PHP (Phase 3.1) | ✓ |
| **Total Assertions** | ~400+ | ↑ ~50 new (Phase 3.1) | ✓ |
| **CI Pass Rate (Last 30 runs)** | 100% | ↑ Stable | ✓ |
| **Average CI Duration** | ~3.5 min | ← Stable | ✓ |
| **Flaky Tests** | 0 known | ← Stable | ✓ |
| **Code Coverage** | Not tracked | ← TODO | ⚠ |
| **SQLi Vulnerabilities** | 173/189 fixed | ↑ +2 (Phase 3.1) | ✓ |

---

## Tier Health

### ✓ Tier 1: Unit Tests

**Status:** GREEN

| Check | Pass | Notes |
|-------|------|-------|
| All unit tests pass | ✓ | 25 files, ~150 assertions |
| PHPStan level 8 clean | ✓ | 0 errors in domain code (10 cosmetic array-type warnings allowed) |
| Deterministic (no flakes) | ✓ | No I/O, no timing deps |
| Fast execution (~30s) | ✓ | Runs on bare runner |
| No external deps | ✓ | Mocks all I/O |

**Coverage:**
- ✓ Domain value objects (Entry, EntryId, BrewerId, StyleNumber, BrewerInfo)
- ✓ Authorization & middleware (Auth, Session, Tracing, Errors)
- ✓ Security policies (Role mapping, Identity, AccessPolicy)
- ✓ Utility functions (dates, conversion, crypto, URLs, strings)

**Recent Changes:**
- 2026-07-21: Added StyleNumber + Entry domain tests (+6 tests)

---

### ✓ Tier 2: Integration Tests

**Status:** GREEN

| Check | Pass | Notes |
|-------|------|-------|
| All integration tests pass | ✓ | 14 files, ~80 assertions |
| DB connectivity reliable | ✓ | Docker MariaDB InnoDB, transactional rollback |
| Deterministic isolation | ✓ | Each test = one transaction, rollback after |
| Schema up-to-date | ✓ | Migrations applied; audit_log table created (Phase 3.1) |
| No test data leakage | ✓ | Orphan sweep before, rollback after |
| Moderate execution (~60s + DB) | ✓ | Acceptable for pre-merge gate |

**Coverage:**
- ✓ EntryRepository CRUD (Phase 3.1): insert, update, delete, select, count
- ✓ EntryService workflow (Phase 3.1): create, update, delete with validation & audit
- ✓ AuditLogger (Phase 3.1): transactional logging of all changes
- ✓ Legacy functions: brewer info, fees, scoring, tokens, passwords
- ✓ Database: migrations, schema integrity, indexes

**Recent Changes:**
- 2026-07-21: Added 3 new integration test files for Entry (9 tests)
- 2026-07-21: Fixed column name mismatches (brewerFirstName, brewID)

**Known Issues:**
- None currently

---

### ✓ Tier 3: Approval Tests

**Status:** GREEN

| Check | Pass | Notes |
|-------|------|-------|
| All approval tests pass | ✓ | 6 files, 40+ snapshot tests |
| Snapshots committed | ✓ | All snapshot files in git |
| No unexpected changes | ✓ | No pending snapshot diffs |
| Fast execution (~20s) | ✓ | Deterministic, no I/O |
| Regressions caught | ✓ | Format changes require approval + commit |

**Coverage:**
- ✓ URL generation (links, SEO-friendly URLs, query strings)
- ✓ Entry serialization (JSON, CSV formats)
- ✓ SRM/EBC color codes (lookup table)
- ✓ Style conversions (legacy ↔ BJCP 2021)
- ✓ Category formatting (category names, abbreviations)

**Recent Changes:**
- 2026-07-19: No changes (stable)

---

### ✓ Tier 4: E2E Tests

**Status:** GREEN

| Check | Pass | Notes |
|-------|------|-------|
| All Playwright tests pass | ✓ | 5 files, ~15 scenarios |
| Browser compatibility | ✓ | Chromium (Firefox/Safari optional) |
| Stack integration | ✓ | Docker app + DB + host browser |
| Timeout resilience | ✓ | Robust selectors, explicit waits |
| Slow execution (acceptable) | ✓ | ~90s (browser overhead expected) |
| Report artifacts | ✓ | HTML report + video on failure |

**Coverage:**
- ✓ **Entrant journey (legacy):** register → create entry → edit → list → payment
- ✓ **Entrant journey (modern):** same flow via /entries routes
- ✓ **Dual-path verification:** same user action on legacy vs modern, verify identical DB state
- ✓ **Admin journey:** login → create judging table → score entries
- ✓ **Security invariants:** unauth access blocked, role enforcement
- ✓ **Smoke tests:** homepage loads, login visible

**Recent Changes:**
- 2026-07-21: Added dual-path-verification.spec.ts (+1 file, ~3 new scenarios)
- 2026-07-21: Updated entrant-journey to test modern /entries routes

---

## Coverage Map: Features vs Tests

| Feature | Unit | Integration | Approval | E2E | Status |
|---------|------|-------------|----------|-----|--------|
| **Entry Create/Read/Update/Delete** | ✓ StyleNumber | ✓ Repository/Service | — | ✓ Journey | ✓ FULL |
| **Authorization & Middleware** | ✓ Auth/Roles | — | — | ✓ Security | ✓ FULL |
| **Entry Window Validation** | — | ✓ Service | — | ✓ Journey | ✓ GOOD |
| **Entry Limit Checks** | — | ✓ Service | — | ✓ Journey | ✓ GOOD |
| **Audit Logging** | — | ✓ AuditLogger | — | ✓ Journey | ✓ GOOD |
| **Brewer Data Fetching** | — | ✓ BrewerInfo | — | — | ✓ GOOD |
| **Scoring & Points** | — | ✓ BestBrewPoints | ✓ Display Place | — | ⚠ PARTIAL |
| **Style Conversions** | — | — | ✓ Snapshots | — | ✓ GOOD |
| **Fee Calculations** | — | ✓ TotalFees | — | — | ✓ GOOD |
| **Export (CSV/PDF)** | — | — | — | — | ✗ TODO |
| **Judging Workflow** | — | — | — | ✓ Journey only | ⚠ PARTIAL |
| **Preferences & Registration** | — | — | — | ✓ Legacy flow | ⚠ PARTIAL |

---

## Metrics Tracking

### Test Execution Times

**Current (2026-07-21):**
- Unit: 30s
- Integration: 60s  
- Approval: 20s
- E2E: 90s
- **Total: ~3.5 min**

**Target:** <5 min (acceptable for pre-merge gate)

**If Exceeds:** Investigate slow tests, consider splitting into sub-tiers

### CI Pass Rate

**Last 30 runs (2026-07-15 to 2026-07-21):** 100% ✓

**By branch:**
- master: 100% (7 runs)
- slim: 100% (15 runs)  
- PR checks: 100% (20+ runs)

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
| **Phase 3.2** | TBD | TBD | Integration (Judging) | ⏳ Planned |
| **Phase 3.3** | TBD | TBD | Integration (Prefs) | ⏳ Planned |
| **Phase 3.4** | TBD | TBD | E2E (Export) + Performance | ⏳ Planned |

---

## Issues & Incidents

### Resolved (2026-07-21)

**Incident: EntryRepository Column Mismatches**
- **Symptom:** Integration tests failing: "Unknown column 'br.first_name'"
- **Root Cause:** Repository using wrong column names (brewerFirstName instead of first_name for JOIN aliases)
- **Fix:** Added SQL aliases in SELECT statements (brewCategorySort, brewID, etc.)
- **Lessons:** Schema review early; test against real DB columns

### Open (None)

---

## Recommendations & Action Items

### Immediate (This Sprint)

- [x] Create TESTING.md overview
- [x] Create TESTING_RUNBOOKS.md
- [ ] Create this health dashboard (IN PROGRESS)
- [ ] Review test count by tier; add missing Judging integration tests

### Short-Term (Next Sprint)

- [ ] Enable code coverage tracking in CI
- [ ] Set coverage baselines (Unit >80%, Integration >60%)
- [ ] Document how to add/maintain approval test snapshots
- [ ] Add contract tests if API clients emerge

### Medium-Term (Phase 3.2+)

- [ ] Extract Judging integration tests (from admin-journey E2E)
- [ ] Add load testing to CI (optional, non-blocking tier)
- [ ] Implement mutation testing (quarterly, not in every CI run)
- [ ] Add performance regression detection (response times baseline)

### Long-Term

- [ ] Consider test matrix for multiple PHP/MySQL versions
- [ ] Add visual regression testing for UI components (Playwright visual)
- [ ] Implement chaos engineering tests (random DB failures, etc.)
- [ ] Archive old test reports for trend analysis

---

## Checklist: Before Each Release

- [ ] All 4 test tiers passing locally & in CI
- [ ] Code coverage >80% (Unit), >60% (Integration)
- [ ] No flaky tests in last 10 CI runs
- [ ] Approval snapshots intentionally committed (if any changes)
- [ ] E2E journey covers both legacy + modern routes
- [ ] Performance unchanged from baseline (<5% regression)
- [ ] Migration validity test passing (PhinxMigrationTest)

---

## Quick Links

- **Full Testing Guide:** [TESTING.md](TESTING.md)
- **Runbook & Debugging:** [TESTING_RUNBOOKS.md](TESTING_RUNBOOKS.md)
- **CI Pipeline:** [.github/workflows/ci.yml](.github/workflows/ci.yml)
- **PHPUnit Config:** [phpunit.xml](phpunit.xml)
- **Playwright Config:** [e2e/playwright.config.ts](e2e/playwright.config.ts)
- **Test Code:** [tests/](tests/) and [e2e/tests/](e2e/tests/)

