# Phase 3.3 Task 1: Database & Audit Foundation - Summary

**Date:** 2026-07-21  
**Branch:** slim  
**Status:** COMPLETE - Two migration files created

## Overview

Created Phinx migrations to add audit columns (`changedAt`, `changedBy`) to both the `preferences` and `judging_preferences` tables, establishing the database foundation for the AdminPreferences service (Phase 3.3).

## Migrations Created

### 1. Migration: 20260721170001_ensure_preferences_audit.php
**File:** `/Users/barryforrest/Projects/brewcompetitiononlineentry/db/migrations/20260721170001_ensure_preferences_audit.php`  
**Purpose:** Create or update `preferences` table with audit columns

**Key Features:**
- **Idempotent Design:** Checks if table exists; if not, creates it; if it exists, only adds missing audit columns
- **Fresh Install Path:** Creates entire table with all 82 known columns plus 2 audit columns (84 total)
- **Upgrade Path:** For existing installs, uses ALTER TABLE to add `changedAt` and `changedBy` only if not already present
- **Phinx Conventions:** Uses logical table name (no prefix); uses Phinx adapter for prefix handling

### 2. Migration: 20260721170002_ensure_judging_preferences_audit.php
**File:** `/Users/barryforrest/Projects/brewcompetitiononlineentry/db/migrations/20260721170002_ensure_judging_preferences_audit.php`  
**Purpose:** Create or update `judging_preferences` table with audit columns

**Key Features:**
- **Idempotent Design:** Same pattern as Migration 1
- **Fresh Install Path:** Creates entire table with all 13 known columns plus 2 audit columns (15 total)
- **Upgrade Path:** For existing installs, adds audit columns via ALTER TABLE if not already present
- **Phinx Conventions:** Uses logical table name; prefix handled by TablePrefixAdapter

## Preferences Table Schema

### Existing Columns (82)
**Email Configuration (8 cols):**
- prefsEmailSMTP, prefsEmailHost, prefsEmailFrom, prefsEmailUsername, prefsEmailPassword, prefsEmailEncrypt, prefsEmailPort, prefsGoogleAccount

**Payment Methods (7 cols):**
- prefsPaypal, prefsPaypalAccount, prefsPaypalIPN, prefsCurrency, prefsCash, prefsCheck, prefsCheckPayee, prefsTransFee

**Entry Management (11 cols):**
- prefsEntryLimit, prefsEntryForm, prefsRecordLimit, prefsRecordPaging, prefsEntryLimitPaid
- prefsUserEntryLimit, prefsUserSubCatLimit, prefsUserEntryLimitDates, prefsUSCLEx, prefsUSCLExLimit
- prefsPayToPrint

**Display & UI (11 cols):**
- prefsDisplayWinners, prefsWinnerDelay, prefsWinnerMethod, prefsDisplaySpecial
- prefsTheme, prefsDateFormat, prefsTimeFormat, prefsContact, prefsTimeZone
- prefsCompLogoSize, prefsCAPTCHA

**Sponsors & Special Categories (2 cols):**
- prefsSponsors, prefsSponsorLogos

**Beer Styles & Content (4 cols):**
- prefsSelectedStyles, prefsStyleSet, prefsStyleLimits, prefsHideRecipe

**Best Brewer Award (8 cols):**
- prefsShowBestBrewer, prefsBestBrewerTitle, prefsFirstPlacePts, prefsSecondPlacePts, prefsThirdPlacePts, prefsFourthPlacePts, prefsHMPts, prefsTieBreakRule1-6 (6 cols)

**Best Club Award (3 cols):**
- prefsShowBestClub, prefsBestClubTitle, prefsBestUseBOS

**Special Options (10 cols):**
- prefsBOSMead, prefsBOSCider, prefsSpecific, prefsLanguage, prefsProEdition
- prefsUseMods, prefsSEF, prefsSpecialCharLimit, prefsAutoPurge
- prefsEmailRegConfirm, prefsEmailCC, prefsShipping, prefsDropOff
- prefsEval, prefsScoringCOA, prefsMHPDisplay

**New Audit Columns (2 cols):**
- `changedAt` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP, NOT NULL) - when preference was last changed
- `changedBy` (INT UNSIGNED, NULL) - FK to users.id; who made the change; NULL for system changes

## Judging Preferences Table Schema

### Existing Columns (13)
- jPrefsQueued, jPrefsFlightEntries, jPrefsMaxBOS, jPrefsRounds
- jPrefsCapJudges, jPrefsCapStewards, jPrefsBottleNum
- jPrefsJudgingOpen, jPrefsJudgingClosed, jPrefsScoresheet
- jPrefsMinWords, jPrefsScoreDispMax, jPrefsTablePlanning

### New Audit Columns (2)
- `changedAt` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP, NOT NULL)
- `changedBy` (INT UNSIGNED, NULL) - FK to users.id

## Design Rationale

### Idempotent Migrations
Both migrations follow Phinx best practices by:
1. Checking if the table exists before creating
2. Checking if audit columns exist before adding them
3. Using `hasColumn()` checks to avoid re-adding existing columns
4. Making it safe to re-run without errors (migration framework requirement)

### Single-Row Semantics
Both preferences tables are single-row by application design (id=1 is the canonical row). While the database schema doesn't enforce this via constraints, the application code maintains this invariant. The audit columns provide a record of what changed and who changed it in cases where an admin modifies settings.

### Audit Column Design
- **changedAt:** Timestamp set to CURRENT_TIMESTAMP, allowing queries like "show preferences as they were at time X" when used with audit_log
- **changedBy:** Nullable int pointing to users.id; NULL indicates system-initiated changes (e.g., bulk updates, version upgrades)

### Phinx Conventions
- Uses logical table names (no `baseline_` prefix) - the phinx.php TablePrefixAdapter handles prefixing transparently
- Both migrations use the `change()` method (not separate up/down) as per Phase 3.2 pattern
- Includes descriptive comments on each column for schema documentation

## Verification

**Syntax Check:** Both PHP files passed PHP syntax validation
- Migration 1: 127 lines, 12 KB
- Migration 2: 61 lines, 4.0 KB

**Not Yet Run:** Migrations have been created but not executed. They will be run as part of the deployment/setup process after they are reviewed and integrated into the slim branch.

## Next Steps (Phase 3.3)

1. **AdminPreferences ValueObject:** Implement ValueObject to represent preferences state
2. **AdminPreferencesRepository:** Read/update preferences from database, use changedBy/changedAt
3. **UpdatePreferencesCommand:** Domain command to record preference changes
4. **AdminPreferencesController:** HTTP endpoints to GET/PUT preferences with authorization checks
5. **Integration Tests:** Verify audit trail is recorded correctly

## Files Modified/Created
- ✅ Created: `/Users/barryforrest/Projects/brewcompetitiononlineentry/db/migrations/20260721170001_ensure_preferences_audit.php`
- ✅ Created: `/Users/barryforrest/Projects/brewcompetitiononlineentry/db/migrations/20260721170002_ensure_judging_preferences_audit.php`

---

## Correction (reviewed 2026-07-21): "COMPLETE" was wrong — wrong tables entirely

A follow-up review found that the two migrations above added audit columns to the
**legacy** `preferences`/`judging_preferences` tables, but
`AdminPreferencesRepository` (the class this task was supposed to lay a DB
foundation for) reads/writes two entirely different tables —
`admin_preferences` and `admin_preferences_events` — that **no migration
anywhere created**. Confirmed by grepping every migration file for the
repository's actual column names (`competitionState`, `styleSet`,
`globalEntryLimit`, `isQueued`, etc.) — zero matches. This was also flagged as
an open item in the independent `PHASE_3_3_CODE_REVIEW.md` ("Migration
creating `admin_preferences` table — not yet done"), which this task should
have closed and didn't.

**Impact confirmed against the live Docker dev DB:** every real call to
`AdminPreferencesRepository::getById()`/`save()` threw `Table
'bcoem.baseline_admin_preferences' doesn't exist`. Not caught earlier because
all `AdminPreferences` unit tests mock the repository, and no integration
test existed for it.

**While fixing this, testing against the live DB also surfaced two more bugs:**

1. **The entire migration chain was silently broken** since Phase 3.2:
   `db/migrations/20260721160003_add_judging_indexes.php` passed `'comment'`
   as an `addIndex()` option, which Phinx rejects (`"comment" is not a valid
   index option`) — this blocked every migration after it, including both of
   this task's own migrations, from ever applying to a real database. The
   same migration also indexed a column, `assignJudge`, that doesn't exist on
   `judging_assignments` (the real judge/brewer reference column is `bid`).
   Both fixed.

2. **`AdminPreferencesRepository::save()` and `::preferencesToArray()` had
   never worked** — they accessed value-object properties directly
   (`$prefs->styleSetConfig()->styleSet->value`) when every property on
   `StyleSetConfiguration`/`EntryConstraints`/`JudgingConfiguration` is
   `private readonly` with a getter method, and called two undefined methods
   (`AdminPreferences::state()`, `::changedAt()` — the real methods are
   `competitionState()` and `stateChangedAt()`). Fixed all of it.

**What was added:**
- `db/migrations/20260721170003_create_admin_preferences.php` — creates
  `admin_preferences` (14 columns matching the repository's actual
  read/write shape) and `admin_preferences_events`.
- `tests/Integration/PhinxMigrationTest.php` — three new tests asserting
  both tables' columns/indexes exist (matching this repo's established
  convention for verifying migrations against the real schema).
- `tests/Integration/AdminPreferences/AdminPreferencesRepositoryIntegrationTest.php`
  — exercises `getById()` (self-healing default row), `save()`, and
  `recordEvent()` against the real DB. This is the test that would have
  caught all of the above on day one.

**Verified:** ran `vendor/bin/phinx migrate` against the local Docker DB
clean from scratch; all 3 new integration tests + all `PhinxMigrationTest`
tests pass; full Unit suite and `phpstan analyse` show no regressions.

**Also discovered, NOT fixed (out of scope for this task):**
`EntryRepositoryIntegrationTest::test_count_by_brewer_id_and_style` fails
against a real DB — `IntegrationTestCase::insertEntry()`'s
`brewCategorySort` fixture value (`substr($style, 0, 2)`) doesn't match how
`StyleNumber::group()` splits a style code, so 2-character styles like
`'1A'` store `brewCategorySort = '1A'` instead of `'1'`. This is the first
time this Integration test suite has actually been run against a live DB in
this engagement — pre-existing, unrelated to AdminPreferences, flagged for
separate follow-up.
