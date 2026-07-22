# Testing Health Dashboard

**Last Updated:** 2026-07-22 (Phase 3.7 Registration — Task 12 full verification pass; every number below was observed from a real run this session, not carried over)  
**Status:** ⚠ MIXED — Unit/Integration/PHPStan/Registration-E2E verified passing; full E2E suite now runs end-to-end (previously fully blocked) but surfaced pre-existing failures in Entry/Export/Judging E2E specs unrelated to Registration (see Tier 4)

---

## Summary

| Metric | Value | Trend | Status |
|--------|-------|-------|--------|
| **Total Test Files** | 95 (87 PHP: 57 Unit + 24 Integration + 6 Approval [5 test classes + 1 shared helper], 8 TS/E2E) | ↑ Phase 3.7 Registration additions | ✓ |
| **Total Assertions** | 1014 Unit + 488 Integration + 102 Approval = 1604 | ↑ from 943 Unit + 271 Integration + 102 Approval (per the prior version of this doc, whose Tier-2 detail row's 271 figure was internally consistent, unlike its mislabeled 577-Unit figure) — real observed deltas this session: Unit 943→1014 (+71), Integration 271→488 (+217) | ✓ |
| **CI Pass Rate (Last 30 runs)** | Not reverified this session — check GitHub Actions directly | — | ⚠ |
| **Average CI Duration** | Not reverified this session for CI. Locally this session: Unit ~3.4s, Integration ~1.3s, E2E (full 34-test suite) ~6.0m | — | ⚠ |
| **Flaky Tests** | 1 known — `RegistrationContainerWiringTest::test_registration_service_resolves` (order-dependent `$GLOBALS['connection']` bootstrap issue in `container.php` when run inside the full Integration suite; passes in isolation). Pre-existing since Task 8/9, confirmed via `git stash` in Task 9, reconfirmed present in this session's runs. | ⚠ Not new | ⚠ |
| **Code Coverage** | Not tracked | ← TODO | ⚠ |
| **SQLi Vulnerabilities** | 173/189 fixed (stale figure, not reverified this session) | — | ⚠ |
| **PHPStan Level** | **0**, not 8 (see `phpstan.neon`) — a known, deliberate policy gap flagged in `Docs/PHASE_3_TRUST_AUDIT.md`, still not decided. This session reconfirmed level 0 is clean (129 files, 0 errors) but did not resolve or revisit the underlying policy question. | — | ⚠ |

---

## Tier Health

### ✓ Tier 1: Unit Tests

**Status:** GREEN

| Check | Pass | Notes |
|-------|------|-------|
| All unit tests pass | ✓ | 57 files, 616 tests, 1014 assertions — real run 2026-07-22: `OK, but there were issues!` (0 failures, 0 errors) |
| PHPStan clean | ✓ | Configured at **level 0**, not level 8 as would be ideal — a known, deliberate policy gap flagged in `Docs/PHASE_3_TRUST_AUDIT.md`, not yet decided. `analyse --memory-limit=1G` reran clean this session: 129 files, **0 errors** — but that only reconfirms level 0 is clean, it doesn't touch the open question of whether to raise the level. |
| Deterministic (no flakes) | ✓ | No I/O, no timing deps |
| Fast execution | ✓ | ~3.4s on a warm local machine this session; CI runtime not reverified |
| No external deps | ✓ | Mocks all I/O |
| Known baseline warnings | ⚠ | 1 distinct warning (HTMLPurifier `DefinitionCache/Serializer` dir missing — cosmetic); PHPUnit's own summary is "8 tests triggered 1 warning," i.e. 8 distinct Registration-domain tests instantiate the purifier and each hits it 3 times (24 invocations total, 1 root cause). 5 deprecations, 5 skipped. No failures, no errors. |

**Coverage:**
- ✓ Domain value objects (Entry, EntryId, BrewerId, StyleNumber, BrewerInfo)
- ✓ AdminPreferences domain (aggregate, value objects, commands, services — Phase 3.3)
- ✓ Judging domain (JudgingTable aggregate, scores, flights — Phase 3.2)
- ✓ Export domain (ExportService, ExportFormatterService, commands — Phase 3.4)
- ✓ **Registration domain (Phase 3.7):** `RegistrationService` (name/address/club/location-preference processing, legacy field-fidelity), `RegisterEntrantCommand`, `RegistrantId`, `RegistrationException`, `RegistrationController` (session + redirect on success)
- ✓ Authorization & middleware (Auth, Session, Tracing, Errors)
- ✓ Security policies (Role mapping, Identity, AccessPolicy)
- ✓ Utility functions (dates, conversion, crypto, URLs, strings)

**Recent Changes:**
- 2026-07-22: Phase 3.7 (Registration) domain unit tests landed — unit file count grew from 48 to 57 (9 new files: `RegistrationServiceTest`, `RegisterEntrantCommandTest`, `RegistrantIdTest`, `RegistrationExceptionTest`, `RegistrationControllerTest`, plus supporting fixtures), test count 577→616, assertions 943→1014
- 2026-07-21: Phase 3.2 (Judging), 3.3 (AdminPreferences), 3.4 (Export) domain unit tests landed — unit file count grew from 25 to 48
- 2026-07-21: A prior code-review pass found and fixed ~40 unit tests that had never actually executed (final-class mocking failures, stale constructor signatures) — see `Docs/PHASE_3_TRUST_AUDIT.md`

---

### ✓ Tier 2: Integration Tests

**Status:** GREEN (1 error, order-dependent and pre-existing — see below; 0 failures)

| Check | Pass | Notes |
|-------|------|-------|
| All integration tests pass | ⚠ | 24 files, 144 tests, 488 assertions — real run 2026-07-22: `Tests: 144, Assertions: 488, Errors: 1, Warnings: 51, Deprecations: 5, Skipped: 28`. The 1 error is `RegistrationContainerWiringTest::test_registration_service_resolves` (`RuntimeException: mysqli connection not initialized in $GLOBALS['connection']`) — reproduced consistently across 3 repeat runs when run as part of the full suite, **passes when run in isolation**. This is the same order-dependent `container.php` bootstrap issue documented as pre-existing in Task 9's report (confirmed there via `git stash` — it predates all Task 9/Registration code). 0 failures. |
| DB connectivity reliable | ✓ | Docker MariaDB InnoDB, transactional rollback |
| Deterministic isolation | ✓ | Each test = one transaction, rollback after |
| Schema up-to-date | ✓ | Migrations applied — audit_log (Phase 3.1), judging indexes (Phase 3.2), admin_preferences (Phase 3.3) |
| No test data leakage | ⚠ | Orphan sweep + rollback covers PHPUnit-originated rows tagged `%@test.example`; does not cover Playwright's `e2e-*@example.com` rows or rows committed by a prior Playwright run against the same DB. Found and manually cleaned 9 (then 13 more after this session's own E2E runs) stray `e2e-*@example.com` user/brewer/staff rows during this task — see `sweepOrphanTestData()` gap noted below. |
| Execution time | ✓ | ~1.3s on a warm local machine this session; CI runtime not reverified |

**Coverage:**
- ✓ EntryRepository CRUD (Phase 3.1): insert, update, delete, select, count
- ✓ EntryService workflow (Phase 3.1): create, update, delete with validation & audit
- ✓ AuditLogger (Phase 3.1): transactional logging of all changes
- ✓ AdminPreferencesRepository (Phase 3.3): getById self-heal, save, recordEvent — against the real `admin_preferences`/`admin_preferences_events` tables
- ✓ Export repositories (Phase 3.4): BrewingExportRepository, ExportService — filters, archive-suffix validation
- ✓ **Registration domain (Phase 3.7):** `RegistrationRepositoryIntegrationTest` (real DB inserts for user/brewer/staff rows), `RegistrationDualPathTest` (legacy vs. modern field-processing equivalence — name, address, club allowlist, judge/steward location preference — against the real DB), `RegistrationContainerWiringTest` (DI resolution, including the CAPTCHA verifier swap)
- ✓ Legacy functions: brewer info, fees, scoring, tokens, passwords
- ✓ Database: migrations, schema integrity, indexes (PhinxMigrationTest now also asserts admin_preferences/admin_preferences_events)

**Recent Changes:**
- 2026-07-22: Phase 3.7 (Registration) integration tests landed — file count grew from 19 to 24, test count 128→144, assertions 271→488
- 2026-07-21: Phase 3.3's DB migration was rebuilding audit columns on the *wrong* legacy tables; the actual `admin_preferences`/`admin_preferences_events` tables the repository needs didn't exist anywhere. Fixed, with a new integration test that would have caught it on day one.
- 2026-07-21: `EntryRepository` was querying a fabricated `brewID` column (the real primary key is `id`) and misreading `brewBrewerID`'s casing — `getById()`, `update()`, and `delete()` were all broken. Fixed; this is the first time these repository's own integration tests have run to completion.
- 2026-07-21: Two new Export integration test files were fatally broken in `setUp()` (non-nullable `\mysqli` property assigned from a possibly-null global; a malformed Symfony validator construction) — fixed.

**Known Issues:**
- **Order-dependent flake (pre-existing, since Task 8/9):** `RegistrationContainerWiringTest::test_registration_service_resolves` fails with `mysqli connection not initialized in $GLOBALS['connection']` when run as part of the full Integration suite (some earlier test's `require_once` of `config.php` completes without leaving `$GLOBALS['connection']` set for this closure to reuse or re-bootstrap); passes standalone. Confirmed pre-existing and unrelated to Registration's own logic; not fixed by this task per scope.
- **Pre-existing, unrelated:** `BrewerInfoTest::testLookupForNonExistentUidReturnsNullishFields` triggers 12 "Trying to access array offset on value of type null" warnings (`lib/common.lib.php:2504-2524`) every run — confirmed present again this session, warnings only (test itself does not fail/error).
- **Pre-existing, unrelated:** `EntryServiceIntegrationTest` can leave orphan `audit_log`/`brewing` rows on rollback failure (hardcoded literal IDs colliding across runs) — not observed failing in this session's runs, but the underlying condition is untouched.
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
- Not rerun this session (Task 12's verification scope was Unit/Integration/PHPStan/E2E per the task brief) — no Registration-domain approval tests exist, so no change expected

---

### ⚠ Tier 4: E2E Tests

**Status:** INFRASTRUCTURE FIXED, PARTIALLY GREEN (real run 2026-07-22) — previously marked BROKEN ("every spec fails at the first navigation" due to an HTTPS/TLS redirect mismatch); that blocker plus two others found earlier this session (a stale container bind-mount and a stale `judging_locations` fixture date) are now fixed, and the full suite runs end-to-end for the first time. **This first real run surfaced pre-existing failures in Entry/Export/Judging specs that were never previously observable** — see breakdown below. None of these are Registration-domain code; Registration's own E2E coverage is 100% green.

| Check | Pass | Notes |
|-------|------|-------|
| All Playwright tests pass | ⚠ | Real run, full suite, `--reporter=list`: **21 passed, 12 failed, 1 did not run, out of 34 total**, ~6.0m wall time. Reproduced consistently (failures confirmed non-flaky by rerunning affected specs standalone). |
| Registration-domain specs | ✓ | `registration-dual-path.spec.ts` (legacy + modern) — 2/2 pass. `smoke.spec.ts`'s "a fresh entrant can register and lands logged in" — pass. `security-invariants.spec.ts`'s registration/login-related checks — all pass. **100% green.** |
| Browser compatibility | ✓ | Chromium via Playwright, real browser, real network — HTTPS/TLS blocker from the prior entry is gone (all navigation now happens over the plain-HTTP `localhost:8080` the Docker vhost actually serves) |
| Stack integration | ✓ | Docker app + DB + host browser containers all start, are reachable, and complete full user flows (register → login → session cookie) |
| Timeout resilience | ⚠ | See per-spec breakdown — several failures are 30s navigation/element timeouts, not app crashes |
| Report artifacts | ✓ | HTML report + screenshots + traces generated correctly for every failure |
| File count | ✓ | 8 files (`admin-journey`, `dual-path-verification`, `entrant-journey`, `export-dual-path`, `judging-dual-path`, `registration-dual-path`, `security-invariants`, `smoke`) — `registration-dual-path.spec.ts` is new this phase |

**Failure breakdown (all confirmed pre-existing / newly-surfaced-by-first-run, none caused by Registration domain work):**
- **`judging-dual-path.spec.ts` (4/4 failed):** every test times out waiting for `input[name="email"]` after `page.goto('${baseUrl}/login')`. Root cause, confirmed by direct investigation: the spec hardcodes `baseUrl = 'http://localhost:8080/bcoem'` (line 18) — the same stale `/bcoem` prefix that `export-dual-path.spec.ts`'s own comment says was already found and fixed there for this Docker setup (app is mounted at DocumentRoot, `/bcoem/login` returns 403, `/login` returns 200). Overriding via the spec's own `TEST_BASE_URL` env var still fails, because the app's actual login UI is a **modal** triggered from the nav bar (see `smoke.spec.ts`: "login modal opens and renders its form fields"), not a standalone page with an `input[name="email"]` — this spec was written against a login page that doesn't match the real UI and, per the previously-BROKEN Tier 4 status, has never actually run before. Not a Registration regression.
- **`export-dual-path.spec.ts` (5/6 failed):** CSV row-count mismatch (`Expected: 15, Received: 1`); HTML export path returns a full page (`<!DOCTYPE html>...`) instead of the expected `<table>` fragment; three specs (`filter selection`, `empty exports`, `audit logs`) fail because `page.goto('/export/preview?format=csv...')` triggers a browser file download instead of navigating, which Playwright's `page.goto()` treats as a hard error ("Download is starting") — the test's assumption (CSV preview renders inline) doesn't match the route's actual behavior (CSV preview forces a download). Only the one auth-enforcement test passes. Export domain, unrelated to Registration.
- **`entrant-journey.spec.ts` (1/2 failed) / `dual-path-verification.spec.ts` (1/2 failed, 1 skipped by serial mode after the failure):** the legacy-route version of each passes; the modern-route version fails on post-registration UI assertions (`h1` not matching `/my entries/i`; `a[href="/entries"]` not visible) — an Entry-domain modern-route UI mismatch, not Registration (registration itself succeeds in both; the failure is in what the entry list page renders afterward).
- **`admin-journey.spec.ts` (1/1 failed):** times out clicking a style checkbox — Playwright's own diagnostics show the click is being intercepted by `<footer class="footer hidden-xs">` overlapping the element (a CSS/layout stacking issue), unrelated to Registration.

**Not independently reverified this session:** whether GitHub Actions CI currently reflects the same 21/12/1 split — check the most recent `integration-and-e2e` Actions run directly.

**Recent Changes:**
- 2026-07-22 (earlier this session, prior to Task 12): fixed the E2E infrastructure blockers — a stale container bind-mount and a stale `judging_locations` fixture date — that were on top of the previously-documented HTTPS/TLS redirect mismatch. All three are now resolved; the full suite executes to completion for the first time.
- 2026-07-22: Added `registration-dual-path.spec.ts` (Phase 3.7) — both legacy and modern registration routes verified to produce equivalent DB state; 2/2 pass.
- 2026-07-21: Added `judging-dual-path.spec.ts` and `export-dual-path.spec.ts` (Phase 3.2/3.4). `export-dual-path.spec.ts`'s auth setup was a fake cookie that never authenticated, and it hardcoded a `/bcoem` path prefix — fixed. `judging-dual-path.spec.ts` still has the equivalent `/bcoem` hardcode plus a page-vs-modal login mismatch (see failure breakdown above) — neither was catchable until this session, since the tier could not run at all before.

---

## Coverage Map: Features vs Tests

| Feature | Unit | Integration | Approval | E2E | Status |
|---------|------|-------------|----------|-----|--------|
| **Entry Create/Read/Update/Delete** | ✓ StyleNumber | ✓ Repository/Service | — | ⚠ Legacy route verified passing; modern route fails post-registration (see Tier 4) | ⚠ PARTIAL (below E2E) |
| **Authorization & Middleware** | ✓ Auth/Roles | — | — | ✓ `security-invariants.spec.ts` — 12/12 pass (admin/entrant access control, session fixation, wrong-password rejection, legacy-hash + new-hash login round-trip) | ✓ FULL (verified E2E) |
| **Entry Window Validation** | — | ✓ Service | — | Not directly E2E-tested | ✓ GOOD |
| **Entry Limit Checks** | — | ✓ Service | — | Not directly E2E-tested | ✓ GOOD |
| **Audit Logging** | — | ✓ AuditLogger | — | Not directly E2E-tested | ✓ GOOD |
| **Brewer Data Fetching** | — | ✓ BrewerInfo | — | — | ✓ GOOD |
| **Scoring & Points** | — | ✓ BestBrewPoints | ✓ Display Place | — | ⚠ PARTIAL |
| **Style Conversions** | — | — | ✓ Snapshots | — | ✓ GOOD |
| **Fee Calculations** | — | ✓ TotalFees | — | — | ✓ GOOD |
| **Export (CSV/PDF)** | ✓ ExportService/Formatter | ✓ Repository/Service | — | ⚠ Verified running, 5/6 specs fail (route behavior mismatches — see Tier 4) | ⚠ PARTIAL; PDF still falls back to CSV (Phase 3.5); export E2E needs follow-up |
| **Judging Workflow** | ✓ JudgingTable/Score | ✓ Table/Score repositories | — | ⚠ Verified running, 4/4 specs fail (stale `/bcoem` hardcode + page-vs-modal login mismatch in the spec file — see Tier 4) | ⚠ PARTIAL; controller/routes were completely unwired until 2026-07-21, E2E spec itself was never validated until this session |
| **AdminPreferences** | ✓ Aggregate/commands/services | ✓ Repository (real DB) | — | — | ✓ GOOD; no controller/routes yet — not reachable via HTTP |
| **Registration (entrant self-registration, Phase 3.7)** | ✓ RegistrationService/Command/ValueObjects/Controller | ✓ Repository + dual-path legacy/modern equivalence | — | ✓ registration-dual-path.spec.ts (legacy + modern), smoke.spec.ts, security-invariants.spec.ts — all pass | ✓ FULL — the only domain in this table with 100% verified passing E2E |
| **Entry list/detail (modern routes)** | — | — | — | ⚠ Verified running, fails post-registration (`h1`/`a[href="/entries"]` assertions) — see Tier 4 | ⚠ PARTIAL; legacy route passes, modern route needs follow-up |
| **Preferences (legacy)** | — | — | — | — | ⚠ PARTIAL — out of Phase 3.7 scope |

**Corrections from the prior version of this table:** Export and Judging were marked "TODO"/"E2E only" — both now have substantial Unit + Integration coverage (Phases 3.2 and 3.4 landed). The E2E column previously said "blocked" across the board; that infrastructure blocker is now fixed and the suite runs, which is *good* news, but it also means the "journey covered" claims for Export/Judging/modern-Entry are no longer just untested designs — they are now **known-failing** E2E specs with concrete, diagnosed root causes (see Tier 4). Registration (Phase 3.7) is the one domain with fully green E2E this session.

---

## Metrics Tracking

### Test Execution Times

**Local, real, this session (2026-07-22):**

- Unit: ~3.4s (616 tests)
- Integration: ~1.3s (144 tests)
- Approval: not rerun this session
- E2E: ~6.0m (34 tests, single worker, `fullyParallel: false` by design — journeys share a seeded DB)

**Not reverified for CI.** Treat local numbers as a warm-machine floor, not a cold-runner estimate.

**Target:** <5 min (acceptable for pre-merge gate)

**If Exceeds:** Investigate slow tests, consider splitting into sub-tiers

### CI Pass Rate

**Not reverified this session.** The figures below (100% across master/slim/PRs) were not re-checked against GitHub Actions. Tier 4's infrastructure blocker is now fixed locally, but CI hasn't been independently confirmed to show the same 21/12/1 split — **check the Actions tab directly** before relying on this number.

**If Falls Below 95%:** Incident review required

### Flaky Tests

**Known Flakes:** 1 — `RegistrationContainerWiringTest::test_registration_service_resolves` (order-dependent, see Tier 2 above)

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
| **Phase 3.7** | 2026-07-22 | 2026-07-22 | Unit + Integration + E2E (Registration) | ✓ Complete **for entrant self-registration (`go=entrant`)** with full legacy field-processing fidelity (name, address, club allowlist, judge/steward location preference) and a dual-path legacy-vs-modern equivalence proof at both the Integration and E2E level. **Explicitly deferred, not built:** admin-driven registration (`filter=admin`), `go=judge`/`go=steward` dedicated entry points, already-logged-in re-registration, confirmation email (`sendPHPMailerMessage`) wiring (flagged in Task 7, unimplemented), `brewerClubsOther` freeform "Other" club path, and non-`en`-language name parsing (structurally supported by Task 7's `processName()` but only the `en` branch has test coverage) — see the plan's own "Deferred / explicitly out of scope" section. This task (Task 12) is the full verification pass: 616 Unit tests / 1014 assertions (0 failures/errors), 144 Integration tests / 488 assertions (1 pre-existing order-dependent error, unrelated), PHPStan clean at level 0 (129 files, 0 errors — level-8 policy gap still open, see above), and 2/2 Registration E2E specs passing. Running the *full* E2E suite for the first time (infra was fixed earlier this session) also surfaced pre-existing, previously-unobservable failures in Entry/Export/Judging E2E specs — not part of this phase, documented in Tier 4 above for follow-up. |

**Correction:** the previous version of this table showed 3.2-3.4 as "TBD/Planned." All three had already landed (with real, previously-undiscovered bugs of their own) by the time this correction was made.

---

## Issues & Incidents

### Resolved (2026-07-22)

**Incident: E2E tier fully blocked**
- **Symptom:** Every Playwright spec failed at first navigation.
- **Root Cause:** Three independent, stacked issues: (1) `.htaccess` forced HTTPS with no TLS listener configured in the Docker dev stack (`ERR_SSL_PROTOCOL_ERROR`); (2) a stale container bind-mount; (3) a stale `judging_locations` fixture date.
- **Fix:** All three resolved earlier this session (before Task 12). The full 34-test suite now runs to completion: 21 passed, 12 failed, 1 skipped-by-serial-mode-after-failure.
- **Lessons:** Fixing the infrastructure blocker doesn't mean the specs behind it were ever correct — several (Judging, Export, modern-Entry) had never actually executed before and turned out to have their own bugs (see Tier 4 for the newly-surfaced failures).

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
- **Missing Judging HTTP handlers:** `templates/Judging/table-form.php` POSTs to endpoints (`postCreateTable`, `postUpdateTable`) and links to a `/judging/locations` page that don't exist in `JudgingController` — flagged, not fabricated.
- **`RegistrationContainerWiringTest` order-dependent flake (since Task 8/9):** fails only inside the full Integration suite (`mysqli connection not initialized in $GLOBALS['connection']`); passes standalone. Confirmed pre-existing via `git stash` in Task 9; reconfirmed present 2026-07-22. Real flakiness, needs a follow-up fix to `container.php`'s connection-bootstrap/reuse logic, not urgent (doesn't affect production — only a test-process artifact).
- **`judging-dual-path.spec.ts` never actually validated against the real app:** hardcodes a stale `/bcoem` base path (same class of bug already fixed in `export-dual-path.spec.ts`) *and* assumes login is a standalone page (`input[name="email"]` on `/login`) when the real UI opens login as a modal from the nav bar. All 4 tests in this file fail. Newly surfaced 2026-07-22 (E2E was fully blocked before, so this was never run). Not Registration-domain, needs a Judging-domain follow-up.
- **`export-dual-path.spec.ts` route-behavior mismatches (5/6 tests fail):** CSV export row-count mismatch (`Expected: 15, Received: 1`); HTML export preview returns a full page instead of a `<table>` fragment; CSV preview (`/export/preview?format=csv...`) triggers a file download rather than rendering inline, which three of the tests don't expect. Newly surfaced 2026-07-22. Not Registration-domain, needs an Export-domain follow-up (could be real app bugs, or E2E specs whose behavioral assumptions were never checked against the real routes).
- **Modern-route Entry list assertions fail post-registration:** `dual-path-verification.spec.ts` and `entrant-journey.spec.ts`'s modern-route tests fail on `h1`/`a[href="/entries"]` assertions after a successful registration + entry creation; the legacy-route equivalents in the same files pass. Newly surfaced 2026-07-22. Not Registration-domain (registration itself succeeds in both) — needs an Entry-domain follow-up on what the modern `/entries` list page actually renders.
- **`admin-journey.spec.ts` UI flakiness:** a style checkbox click is intercepted by an overlapping `<footer class="footer hidden-xs">` element — a CSS stacking/layout issue, unrelated to Registration.

---

## Recommendations & Action Items

### Immediate

- [x] Create TESTING.md overview
- [x] Create TESTING_RUNBOOKS.md
- [x] Create this health dashboard
- [x] Judging integration tests exist now (`JudgingTableRepositoryIntegrationTest`, `JudgingScoreServiceIntegrationTest`)
- [x] **Fix the E2E infra blockers** — HTTPS/TLS redirect, container bind-mount, `judging_locations` fixture date all fixed; full suite runs to completion
- [x] Registration domain (Phase 3.7): Unit/Integration/PHPStan/E2E all verified this session
- [ ] Fix `judging-dual-path.spec.ts` — stale `/bcoem` base path + page-vs-modal login mismatch (4 tests failing)
- [ ] Fix `export-dual-path.spec.ts` — CSV row-count mismatch, HTML preview content mismatch, CSV preview triggers download instead of inline render (5 tests failing)
- [ ] Investigate modern-route Entry list assertions failing post-registration in `dual-path-verification.spec.ts` / `entrant-journey.spec.ts` (2 tests failing)
- [ ] Fix `admin-journey.spec.ts` footer-overlap click interception (1 test failing)
- [ ] Fix `RegistrationContainerWiringTest`'s order-dependent `$GLOBALS['connection']` bootstrap flake in `container.php` (passes standalone, fails in full suite)
- [ ] Decide the `comp_id` question for Export (add the column, or drop the filter)
- [ ] Extend `sweepOrphanTestData()`'s cleanup filter to also match Playwright's `e2e-*@example.com` pattern (currently only matches `%@test.example`) — manually cleaned 9 then 13 stray rows during this session alone

### Short-Term

- [ ] Enable code coverage tracking in CI
- [ ] Set coverage baselines (Unit >80%, Integration >60%)
- [ ] Document how to add/maintain approval test snapshots
- [ ] Add contract tests if API clients emerge
- [ ] Add a test tier (or at least a smoke check) that dispatches real requests through `buildApp()->handle()` — nothing currently catches routing/DI wiring bugs like the ones found and fixed 2026-07-21, since Unit tests mock everything and E2E only exercises a handful of the app's total routes

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

- [ ] All 4 test tiers passing locally & in CI (Tier 4 now runs but has 12 known-failing, pre-existing specs outside Registration scope — see above; do not treat as a release blocker for Registration-specific work, but do not claim "all green" either)
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

