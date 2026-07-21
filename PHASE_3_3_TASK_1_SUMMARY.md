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
