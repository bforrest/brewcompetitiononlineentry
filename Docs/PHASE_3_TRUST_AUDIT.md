# Phase 3 Copilot Work — Trust Audit

**Date:** 2026-07-21
**Scope:** `Docs/superpowers/specs/2026-07-21-phase3-sequencing-strategies.md` + every `PHASE_3_*.md` doc, checked against the actual code and test suite on branch `slim`.
**Verdict:** Your instinct is correct. The docs consistently claim "COMPLETE" / "READY TO MERGE" status while shipping code with defects the docs' own review passes had already found (or would have found, had verification actually run).

**Update (same session, after this audit landed):** all issues identified below have been fixed and verified. Unpicking the `final`-class blockers in §3 uncovered a second layer of bugs the audit couldn't see yet — including two more live production defects beyond the original scope. See §9 for the complete list of fixes and the new findings. Unit suite went from **45 errors / 2 failures** to **1 error / 2 failures**, the remainder being pre-existing environment gaps (no DB, no OTel extension in this sandbox) unrelated to any Phase 3 code. `phpstan analyse` stays clean throughout.

All findings below were reproduced directly (commands shown), not inferred from the docs.

---

## 1. Sequencing spec is internally inconsistent

`Docs/superpowers/specs/2026-07-21-phase3-sequencing-strategies.md` presents 4 strategies, then says:

> **Chosen Strategy: Strategy 2 (Workflow-First / Top-Down)**

...but the roadmap table immediately under that heading is titled **"Implementation roadmap (Strategy 3)"** and actually describes Strategy 3 (targeted library extraction: call-graph analysis → `LegacyQueryFunctions.php` → *then* workflows). Strategy 2 explicitly says to skip the library-extraction step. The two don't match — whoever wrote the "chosen strategy" section pasted the wrong roadmap under it. Minor, but it means the stated rationale ("no need to refactor lib/common.lib.php") doesn't actually describe what the roadmap has you doing.

---

## 2. Confirmed live bug (fixed this session): DI container never wired the validator

- `PHASE_3_3_TASK_2_SUMMARY.md` (2026-07-21, "Status: COMPLETE") documents `PreferencesValidationService` as having **"Dependencies: None (pure logic)"** and wires it into `container.php` with zero constructor args.
- One task later, `PHASE_3_3_TASK_4_IMPLEMENTATION.md` (same day, "Status: ✅ COMPLETE") adds `validateCommand()`, which requires a `ValidatorInterface` in the constructor — and its own "Technical Debt" section admits: *"Validator injection: PreferencesValidationService now requires ValidatorInterface in constructor. Must be registered in DI container (Task 5 if not already)."*
- It was never registered. `container.php:166` kept calling `new PreferencesValidationService()` with zero args through the "Phase 3.3 Critical Fixes" commit (`5166c01d`) and into Phase 3.4. Any code path resolving this service via the container (i.e. any real HTTP request) would have fatally errored.

Caught this session via `phpstan analyse` (`arguments.count` error) and fixed in [container.php:166](../src/Kernel/container.php#L166). Confirmed `phpstan analyse` now reports `[OK] No errors` and the unit suite is unaffected (see §6).

**Why this went unnoticed:** the only verification artifact for Task 2's wiring was `tests/ContainerWiring/AdminPreferencesContainerTest.php` — a standalone script (`php tests/ContainerWiring/...`), not a PHPUnit test class, **not registered in `phpunit.xml`**, and never re-run after Task 4 changed the constructor it was "verifying." It's a one-time manual check dressed up as a regression test.

---

## 3. The `final` class / un-mockable repository bug is systemic, not isolated

`PHASE_3_3_CODE_REVIEW.md` correctly caught `AdminPreferencesRepository` marked `final` breaking all 40 of its service tests, flagged it CRITICAL, and it was fixed (`5166c01d`, confirmed: class is no longer `final`).

But the identical pattern exists, untouched, in the **older** Phase 3.1 code and is live right now:

```
$ ./vendor/bin/phpunit --filter 'EntryRepositoryTest|EntryValidationServiceTest|EntryServiceTest'
ClassIsFinalException: Class "Bcoem\Database\Connection" is declared "final" and cannot be doubled
ClassIsFinalException: Class "Bcoem\Domain\Entry\Repository\EntryRepository" is declared "final" and cannot be doubled
```

- [src/Database/Connection.php:19](../src/Database/Connection.php#L19) — `final class Connection`
- [src/Domain/Entry/Repository/EntryRepository.php:22](../src/Domain/Entry/Repository/EntryRepository.php#L22) — `final class EntryRepository`

Both `final` since commit `119c7263` ("Phase 3.1: Entries/Brewing extraction"), same day as everything else. **These break 14 unit tests today**, and per `git log`, have broken them since the tests were added (`4916fa22`, 36 minutes later, same day) — meaning Phase 3.1's own unit tests have never once passed since being written, despite Phase 3.1 being the *first* phase and treated as a settled foundation for everything built on top of it since.

---

## 4. A whole test file uses a Symfony API that was removed — never ran once

```
$ ./vendor/bin/phpunit --filter UpdateStyleSetCommandTest
Error: Call to undefined method Symfony\Component\Validator\ValidatorBuilder::enableAnnotationMapping()
```

[tests/Unit/Domain/AdminPreferences/Command/UpdateStyleSetCommandTest.php:86](../tests/Unit/Domain/AdminPreferences/Command/UpdateStyleSetCommandTest.php#L86) calls `enableAnnotationMapping()`, which does not exist in `symfony/validator: ^7.0` (this project's pinned version) — it was removed when Symfony dropped annotation-based mapping in favor of attributes. All 3 **sibling** command test files written in the same task (`UpdateEntryConstraintsCommandTest`, `UpdateJudgingConfigCommandTest`, `TransitionCompetitionStateCommandTest`) correctly call `enableAttributeMapping()` instead — so this is a one-line copy/paste inconsistency, not a deeper design problem, but it means 7 of the "50+ tests, all structured for easy execution" that `PHASE_3_3_TASK_4_IMPLEMENTATION.md` marked complete have never executed successfully.

---

## 5. A second, unrelated construction bug kills all 22 AdminPreferences service tests

```
$ ./vendor/bin/phpunit --filter UpdateStyleSetServiceTest
Error: Call to private Bcoem\Security\Identity::__construct() from scope Tests\Unit\...\UpdateStyleSetServiceTest
```

`Identity` ([src/Security/Identity.php:9](../src/Security/Identity.php#L9)) has a **private constructor** and a named factory (`Identity::fromSession(array $session): self`). Every AdminPreferences service test (`UpdateStyleSetServiceTest` ×5, `UpdateEntryConstraintsServiceTest` ×6, `UpdateJudgingConfigServiceTest` ×4, `TransitionCompetitionStateServiceTest` ×7 — **22 tests total**) tries `new Identity(...)` directly and fatals immediately.

Notably, `PHASE_3_3_TASK_4_IMPLEMENTATION.md`'s own "Technical Debt" section flags the root cause: *"Identity namespace: Services currently use `Bcoem\Security\Identity` but JudgingController uses `Bcoem\Kernel\Identity`. May need to consolidate."* The doc identifies there are two competing Identity concepts in the codebase, marks it as a loose end, and ships "50+ tests... ✅" anyway. None of the 22 have ever passed.

---

## 6. Net effect: current test suite status

```
$ ./vendor/bin/phpunit --testsuite=Unit
Tests: 578, Assertions: 837, Errors: 45, Failures: 2, Warnings: 3, Skipped: 5
```

Verified via `git stash` that this exact result is unchanged with or without today's DI fix — i.e. **45 errors is the pre-existing state**, not a regression from anything done this session. Breakdown of the 45:

| Cause | Count | Since | Real bug? |
|---|---|---|---|
| `Identity` private constructor misuse (§5) | 22 | Phase 3.3 Task 4 (today) | Yes |
| `Connection`/`EntryRepository` marked `final` (§3) | 14 | Phase 3.1 (today, oldest phase) | Yes |
| `enableAnnotationMapping()` removed API (§4) | 7 | Phase 3.3 Task 4 (today) | Yes |
| `HelloWorldRouteTest` — no DB in this sandbox | 1 | pre-existing | Environmental, not a Copilot regression |
| `DateTimeFunctionsTest` — unrelated lib warning | 1 (of 3 warnings) | pre-existing | Unrelated to Phase 3 |

**None of these 43 real failures are new from today's session** — they've been broken since the commit that introduced each test file, all on 2026-07-21. The 336-passing-test baseline cited in the July 18 commit (`f41e90f2`, "full 336-test suite green on a fresh DB") predates all of Phase 3.1–3.4; nothing since has actually been run to green.

---

## 7. PHPStan's bar is lower than the docs claim

`PHASE_3_3_CODE_REVIEW.md` scores code quality against **"33 PHPStan level 8 errors"** and its own merge checklist says `php vendor/bin/phpstan analyze src/Domain/AdminPreferences --level 8`. `PHASE_3_2_IMPLEMENTATION_COMPLETE.md` claims *"PHPStan level=8 enforces type safety."*

Actual config, actually run in CI (`.github/workflows/ci.yml:35` calls `vendor/bin/phpstan analyse` with no override):

```
$ cat phpstan.neon
level: 0
```

Level 0 is PHPStan's loosest tier. It still caught the container bug (§2) because `arguments.count` fires even at level 0 — but none of the level-8-only findings the code review lists (missing generic array types, `allowNull` validator params, redundant `is_int()` checks) are actually gated by CI today. The review's own verification checklist scoped PHPStan to `src/Domain/AdminPreferences` only, which is also why it never touched `src/Kernel/container.php` and caught the DI bug in §2 despite `phpstan analyse` (unscoped) finding it in one run.

---

## 8. Where the docs' self-review actually worked

For balance — Phase 3.2 is the one place the pattern of "review flags it, next commit fixes it" held up under verification:

- `PHASE_3_2_CODE_REVIEW.md` flagged missing RBAC ("any authenticated user can transition table state") — confirmed fixed: [JudgingController.php](../src/Kernel/Controller/JudgingController.php) now has 4 explicit `$identity->hasRole('admin')` checks (lines 157, 182, 213, 327).
- Same review flagged `admin-table-list.php` missing `$locationName`/`$states`/`$selectedState` — confirmed fixed: `JudgingController::getTablesView()` sets all three (lines 239–252) and the template's docblock matches.

So the self-review-then-fix loop isn't broken in principle; it just wasn't followed through for Phase 3.3/3.4, where "Status: COMPLETE" got written before the fixes the reviews called for actually landed.

---

## 9. Fixes applied this session

Fixing §3's `final` keyword unblocked mocking, which let PHPUnit actually execute code that had never run before — which immediately surfaced a **second layer** of bugs the `ClassIsFinalException` had been hiding. Two of these are new, previously-undetected production defects, not test-only issues:

### New production bugs found and fixed (beyond the original audit)

1. **`EntryService::create()` crashed on every call.** [EntryService.php:66](../src/Kernel/Service/EntryService.php) built `EntryId::from(0)` with the comment "Will be assigned by repository" — but `EntryId`'s constructor rejected anything `<= 0`. Creating a competition entry through the modern domain layer was completely non-functional. Fixed by allowing `0` as an explicit "not yet persisted" sentinel in `EntryId` (rejecting only negatives), and by having `EntryRepository::insert()` strip the placeholder `brewID` from the row before building the `INSERT` (mirroring what `update()` already does), so the auto-increment column isn't fought.
2. **`EntryRepository::entryToRow()` used the wrong column name.** It built INSERT/UPDATE statements against `brewBrewerID` (capital ID), while every read query in the same file (`getById`, `listByBrewerId`, `countByBrewerId`) uses `brewBrewerId` (lowercase d, the real schema column). Every insert and update would have failed with an "unknown column" SQL error. Fixed to match the read path.
3. **`countByBrewerIdAndStyle()`'s type-hint didn't match its only real caller.** `EntryValidationService::validateCreate()` passes a `StyleNumber` value object; the repository method declared `string $styleNumber` — a guaranteed `TypeError` the moment the subcategory-limit check ran. Fixed the repository to accept `StyleNumber` (matching the caller and the integration test), extracting the SQL parameter via `->group()`.
4. **A dedicated `EntryWindowClosedException` (HTTP 409) existed but was never thrown.** `EntryValidationService` threw a generic `\RuntimeException` for a closed entry window instead, which any centralized exception-type dispatch (the pattern used elsewhere, e.g. `JudgingController`) would have mishandled. Fixed both call sites (`validateCreate`, `validateUpdate`) to throw the dedicated exception.

### Systemic `final`-class fix, completed properly

Removed `final` from `Connection`, `EntryRepository`, `EntryValidationService`, `AuditLogger`, and `StyleService` — every class actually mocked in the Entry domain's tests (§3 only caught the first two; the rest surfaced once those were fixed).

### Test-only fixes (mechanical, no behavior change)

- 22 AdminPreferences service tests: `new Identity(true, 'admin', Role::Admin)` → `Identity::fromSession(['loginUsername' => 'admin', 'userLevel' => '1'])`, since `Identity`'s constructor is private (§5).
- `UpdateStyleSetCommandTest.php`: `enableAnnotationMapping(true)` → `enableAttributeMapping(true)` (§4).
- `EntryRepositoryTest`: fixed a fixture row using `brewBrewerID`/missing `uid` where production reads `brewBrewerId`/`uid`; fixed 4 call sites across 3 test files missing the `uid` constructor argument for `BrewerInfo` (all silently defaulted a string into an `int $uid` parameter).
- `EntryServiceTest` / `EntryValidationServiceTest`: constructors had drifted out of sync with the real 5-arg `EntryService` and 3-arg `EntryValidationService` signatures (both still assumed old, pre-Phase-3.2 arities). Rewrote both `setUp()` methods to match, and fixed mock return values (`insert()` returns `EntryId`, not `int`).
- 5 AdminPreferences service tests (`test_invalid_*`/`test_range_validation_enforced`/`test_mutually_exclusive_constraint_rejected`/`test_invalid_transition_rejected`) asserted `getById()` gets called even on the rejected path — but all four services validate the command *before* fetching the aggregate (correct fail-fast design), so `getById()` is never reached. Fixed the assertions to `expects($this->never())`, and dropped a leftover duplicate mock setup in the transition test that was silently masking the intended scenario.

### Two tests removed rather than "fixed"

- `UpdateStyleSetCommandTest::test_invalid_allowed_style_ids_type` — asserted a Symfony validation violation for assigning a string to `allowedStyleIds`, but that property is a native, strictly-typed `array`; PHP itself throws a `TypeError` on the assignment, before Symfony's validator ever runs. The scenario is structurally unreachable, not a validation gap.
- `EntryValidationServiceTest::test_validate_delete_calls_repository` and the matching expectation in `EntryServiceTest::test_delete_calls_repository` — both asserted a `validateDelete()` method that doesn't exist anywhere in `EntryValidationService`, and `EntryService::delete()` never calls it. There's no spec for what delete-validation should check (ownership? competition lock state?), so rather than invent business rules to satisfy a copy-pasted assertion, the phantom expectation was dropped. **This is a real gap, just not a mechanical one** — flagging it here rather than silently deciding it: if entries should be un-deletable after some point (locked competition, past the entry window), that check does not currently exist anywhere.

### `tests/ContainerWiring/AdminPreferencesContainerTest.php` — deleted

Recommendation 4 (below) asked to decide its fate. Its own "Test 2" hardcoded *"PreferencesValidationService constructor should have 0 parameters"* — actively encoding the exact bug from §2 as the **correct** expectation. It duplicates, worse, what `phpstan analyse` already verifies for every container entry (via `arguments.count`) without any hardcoded assumption to go stale. Deleted rather than promoted.

### Result

```text
Before: Tests: 578, Errors: 45, Failures: 2, Warnings: 3
After:  Tests: 577, Errors: 1,  Failures: 2, Warnings: 3
```

The remaining 1 error (`HelloWorldRouteTest`, needs a live mysqli connection) and 2 failures (`SessionMiddlewareTest`, broken by an OTel-extension warning flushing headers early) are environment gaps in this sandbox — no database, no native OpenTelemetry extension — not code defects, and unrelated to any Phase 3 work. `phpstan analyse` remains clean (`[OK] No errors`) throughout.

---

## Recommendations

1. **Don't trust a "COMPLETE" status in these docs without re-running the suite.** Treat every `PHASE_3_*.md` "✅ COMPLETE" as a claim to verify, not a fact — three of four Phase 3.3 docs claimed completion while their own subsequent code review (dated the same day) contradicted them.
2. ~~Fix §3 and §5~~ — **done, see §9.** Turned out deeper than either section could see at audit time: fixing the `final` keyword surfaced 2 more live production bugs (§9) that no doc or review had caught.
3. ~~Fix §4~~ — **done, see §9.**
4. ~~Delete or promote `tests/ContainerWiring/AdminPreferencesContainerTest.php`~~ — **deleted, see §9.**
5. **Still open:** decide whether the level-8 claims in the docs are aspirational or current, and make `phpstan.neon` match reality — right now the docs describe a stricter gate than the one actually enforced, which is presumably how §2 shipped in the first place. Left alone deliberately; this is a policy call, not a mechanical fix.
6. **Still open, and now more urgent:** `EntryService::delete()` has no validation step at all (§9's "removed rather than fixed" note) — decide whether entries should be blocked from deletion under any condition (locked competition, closed entry window, judging already started) before this ships.
7. Before starting Phase 3.5 or trusting any further "Copilot" phase-completion doc, run `./vendor/bin/phpunit --testsuite=Unit` and `./vendor/bin/phpstan analyse` yourself and compare to what the doc claims — this audit's method, not its specific findings, is the reusable part.
