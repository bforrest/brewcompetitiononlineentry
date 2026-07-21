# Phase 3.3 Implementation Plan: Admin Preferences → Registration Workflow

**Date:** 2026-07-21  
**Strategy:** Similar to Phase 3.2 (Workflow-First Extraction)  
**Primary artifact:** AdminPreferences aggregate root with EntryConstraints value object

---

## Overview

Phase 3.3 extracts the Admin Preferences workflow from legacy code into domain-driven design. This workflow manages competition-wide settings (style sets, entry limits, category restrictions) that constrain the Entry and Judging workflows created in Phases 3.1 and 3.2.

**Key constraint:** Preferences are typically locked during active competition. Changes should only be allowed in "Planning" state.

---

## Current State (Legacy)

**Database table:** `preferences` (main settings)
- `prefsStyleSet` (BJCP2025, AABC2025, BA, etc.)
- `prefsEntryLimit` (global entry cap per user)
- `prefsStyleLimits` (JSON: per-style limits or single table limit)
- `prefsRecordLimit`, `prefsTimeZone`, `prefsDateFormat`, `prefsWinnerDelay`, etc.

**Related:** `judging_preferences` (judging-specific settings)
- `jPrefsQueued`, `jPrefsFlightEntries`, `jPrefsMaxBOS`, `jPrefsRounds`

**Legacy files:**
- `admin/site_preferences.admin.php` — settings form (SQL injection vulnerabilities, string concatenation)
- `admin/judging_preferences.admin.php` — judging settings
- `includes/process/process_judging_preferences.inc.php` — form processing
- `setup/site_preferences.setup.php` — initial setup wizard

**Known vulnerabilities (candidate for Phase 3.3):**
- Direct SQL concatenation in preferences queries
- No prepared statements for preferences CRUD
- No audit trail for preference changes
- Form validation occurs after database update (risky)

---

## Design Decisions

### 1. Aggregate Root: AdminPreferences

Single aggregate managing all competition-wide settings. Immutable value objects for logical groups:

```
AdminPreferences (root)
├── StyleSetConfiguration (value object)
│   ├── styleSet: StyleSet enum
│   ├── allowedStyles: StyleId[]
│   └── customExceptions: StyleId[]
├── EntryConstraints (value object)
│   ├── globalEntryLimit: int
│   ├── perStyleLimits: array<StyleId => int>
│   ├── perTableLimit: int
│   └── subCategoryLimits: array<Category => int>
├── JudgingConfiguration (value object)
│   ├── isQueued: bool
│   ├── maxFlightEntries: int
│   ├── maxBosPerStyle: int
│   └── maxRounds: int
└── CompetitionState
    ├── state: CompetitionState enum (Planning, Active, Closed)
    └── stateChangedAt: DateTime
```

### 2. Workflow: Update Preferences (Admin Only)

```
1. Admin navigates to /admin/preferences
2. View renders current settings with forms
3. Admin modifies (e.g., change entry limit from 3 to 5)
4. Form submits to POST /admin/preferences
5. Controller:
   - Validates command (syntax, ranges, conflicts)
   - Checks state (must be Planning or no competition active)
   - Calls AdminPreferencesService->updatePreferences()
6. Service:
   - Fetches current preferences
   - Applies changes to value objects
   - Validates business rules (entry limit >= judging flights, etc.)
   - Persists to database
   - Records audit event
7. Response: Redirect with success message
```

### 3. Constraint Enforcement

Preferences constrain Entry and Judging workflows:

| Preference | Constraint | Enforced By |
|---|---|---|
| `styleSet` | Only entries in styleSet can be submitted | EntryService during creation |
| `globalEntryLimit` | User cannot submit > limit | EntryService, UI form |
| `perStyleLimits` | User cannot submit > limit per style | EntryService, UI form |
| `maxFlightEntries` | Flights queue max entries | FlightQueue ordering |
| `subCategoryLimits` | Limit entries by sub-category | EntryService validation |

When preferences change, **existing entries remain valid** (grandfathered). Only new submissions checked against updated constraints.

### 4. File Structure

```
src/Domain/AdminPreferences/
├── AdminPreferences.php                 # Aggregate root
├── ValueObject/
│   ├── PreferencesId.php
│   ├── StyleSetConfiguration.php        # Style + exceptions
│   ├── EntryConstraints.php             # Limits
│   ├── JudgingConfiguration.php         # Judging params
│   ├── CompetitionState.php             # Active, Planning, Closed
│   └── StyleSet.php                     # Enum: BJCP2025, AABC2025, BA
├── Command/
│   ├── UpdateStyleSetCommand.php
│   ├── UpdateEntryConstraintsCommand.php
│   ├── UpdateJudgingConfigCommand.php
│   └── TransitionCompetitionStateCommand.php
├── Exception/
│   ├── PreferencesException.php
│   ├── PreferencesLockedForCompetitionException.php
│   ├── InvalidStyleSetException.php
│   └── InvalidConstraintException.php
├── Repository/
│   └── AdminPreferencesRepository.php    # Single-row fetch/update
├── Service/
│   ├── AdminPreferencesService.php       # Workflow orchestration
│   ├── PreferencesValidationService.php  # Business rule checks
│   └── StyleCatalogService.php           # Style lookups
└── Factory/
    └── PreferencesFactory.php             # Hydrate from DB
```

---

## Task Breakdown (Similar to Phase 3.2)

### Setup Phase (Week 1)

#### Task 1: Database & Audit Foundation
- Migration: Create `preferences` schema (if not exists, migrate from legacy)
- Migration: Add audit columns (changed_at, changed_by)
- Success: `phinx migrate` succeeds; preferences table visible in DB

#### Task 2: DI & Container Wiring
- Register AdminPreferencesService, PreferencesValidationService, StyleCatalogService
- Register singleton AdminPreferencesRepository
- Extend container config

### Domain Layer (Weeks 1–2)

#### Task 3: Value Objects & Aggregates
- StyleSet enum (BJCP2025, AABC2025, BA)
- StyleSetConfiguration, EntryConstraints, JudgingConfiguration value objects
- AdminPreferences aggregate root
- CompetitionState enum (Planning, Active, Closed)

#### Task 4: Commands
- UpdateStyleSetCommand, UpdateEntryConstraintsCommand, UpdateJudgingConfigCommand
- Symfony validator attributes

#### Task 5: Exceptions
- PreferencesException base class
- PreferencesLockedForCompetitionException (409)
- InvalidStyleSetException (422)
- InvalidConstraintException (422)

#### Task 6: Repository
- AdminPreferencesRepository: getPreferences(), updatePreferences()
- All queries use prepared statements

#### Task 7: Services
- PreferencesValidationService: Validate command, check state
- AdminPreferencesService: Orchestrate update flow
- StyleCatalogService: Load available styles

#### Task 8: Unit Tests
- 5 tests per value object (~25 total)
- 10 tests for aggregate state transitions
- 15 tests for validation service
- 10 tests for repository
- **Target: 60+ unit tests, 100+ assertions**

### Route & Integration (Week 2)

#### Task 9: Admin Controller & Routes
- AdminPreferencesController: getPreferencesForm(), postUpdatePreferences()
- Routes: GET/POST /admin/preferences
- Authorization: Admin-only

#### Task 10: Templates
- admin-preferences-form.php (style set selector, limit forms, judging params)
- admin-competition-state.php (state transition buttons)

#### Task 11: Integration Tests
- AdminPreferencesRepositoryIntegrationTest
- AdminPreferencesServiceIntegrationTest

### Validation & Completion (Week 2)

#### Task 12: E2E Tests
- Admin preference workflow (create, update, lock)
- Constraint enforcement in Entry submission
- Preference audit trail

#### Task 13: Regression & Merge
- Characterization suite passes
- Dual-path verification (if legacy route exists)
- Merge to slim

---

## Critical Business Rules

1. **Preference Lockdown:** Once competition enters Active state, only certain fields can be changed (e.g., entry limit can increase but not decrease mid-competition).

2. **Constraint Consistency:** Entry limit must be ≥ number of flights per location.

3. **Style Set Immutability:** Changing style set after entries exist invalidates category selections. Phase 3.3 prevents style set changes once entries exist.

4. **Grandfathering:** Existing entries are not retroactively invalidated if preferences become stricter.

---

## Success Criteria

| Criterion | Definition |
|---|---|
| **Domain model complete** | AdminPreferences aggregate with value objects fully typed |
| **SQL injection eliminated** | 0 string concatenation in preference queries |
| **Validation enforced** | Preferences changes rejected if constraints violated |
| **Audit trail** | Every preference change logged with user + timestamp |
| **Unit tests passing** | 60+ tests, 100+ assertions |
| **Integration tests passing** | Repository + Service suites with real DB |
| **E2E coverage** | Admin workflow + constraint enforcement tested |
| **No regressions** | Characterization suite passes |

---

## Dependencies & Risks

### Dependencies
- Phase 3.1 complete (Entry workflow, uses preferences for constraints)
- Phase 3.2 complete (Judging config stored in preferences)
- StyleSet enum stable

### Risks
1. **Constraint validation complexity:** Preferences have interdependencies. Ensure validation catches conflicts early.
2. **Preference changes during competition:** May break assumptions in Entry/Judging services. Mitigate by restricting changes during Active state.
3. **Existing entries & retroactivity:** Clarify whether changing limits affects pending vs. accepted entries.

---

## Next Steps

1. **Explore current legacy code** — understand all preference fields + relationships
2. **Design StyleSet enum** — enumerate all valid style sets
3. **Map preference fields to value objects** — grouping logic
4. **Estimate task effort** — refine 2-week plan
5. **Start Task 1: Database** — ensure schema is stable for CRUD

---

**Recommended first step:** Task 1 (Database Foundation) + Task 2 (DI Wiring) in parallel to unblock domain layer work.
