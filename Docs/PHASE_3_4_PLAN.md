# Phase 3.4: Export Hardening Implementation Plan

**Date:** 2026-07-21  
**Status:** Planning  
**Scope:** Extract legacy export system; eliminate 11 SQL injection vulnerabilities; implement parameterized queries across 5 export DB query files

---

## Context

Phase 3.1-3.3 completed domain extraction for Entries, Judging, and AdminPreferences workflows. Phase 3.4 targets export functionality—read-heavy, lower mutation complexity, but critical for security (export is user-facing public-accessible reports).

**Current State:**
- `output/export.output.php` — orchestrates export rendering (CSV, HTML, PDF, XML)
- `includes/db/output_*.db.php` — 5 query files with 164+ sprintf-based dynamic SQL statements
- All queries vulnerable to SQL injection via unsanitized parameters: `$filter`, `$bid`, `$sort`, `$_SESSION['comp_id']`
- No prepared statements; all parameters concatenated directly into SQL

**Goal:** Migrate export queries to domain layer with parameterized queries; maintain export output parity.

---

## Architecture Design

### 1. Domain Layer: Export

```
src/Domain/Export/
├── Export.php                          # Aggregate root (immutable)
├── ValueObject/
│   ├── ExportId.php
│   ├── ExportFormat.php               # (CSV, HTML, PDF, XML)
│   ├── ExportFilter.php               # (paid, nopay, required, winners, circuit, etc.)
│   ├── ExportView.php                 # (all, default, not_received)
│   ├── ReportData.php                 # Immutable report DTO
│   └── ExportMetadata.php             # format, filter, view, generated_at
├── Command/
│   ├── GenerateExportCommand.php      # DTO with symfony/validator
│   ├── ExportBrewingCommand.php       # (entries export)
│   ├── ExportParticipantsCommand.php  # (judges, stewards, staff, etc.)
│   └── ExportJudgingCommand.php       # (results/scores)
├── Exception/
│   ├── ExportException.php            # (abstract base)
│   ├── InvalidExportFilterException.php
│   ├── InvalidArchiveException.php
│   └── AccessDeniedException.php
├── Repository/
│   ├── ExportRepository.php           # Query isolation layer (all queries)
│   ├── BrewingExportRepository.php    # Brewing/entries exports (3 query files)
│   ├── ParticipantExportRepository.php # Participant exports (1 query file)
│   └── JudgingExportRepository.php    # Judging exports (1 query file)
├── Service/
│   ├── ExportService.php              # Orchestrate export generation
│   ├── BrewingExportService.php       # Brewing-specific logic
│   ├── ParticipantExportService.php   # Participant-specific logic
│   ├── JudgingExportService.php       # Judging-specific logic
│   └── ExportFormatterService.php     # Format output (CSV, HTML, PDF, XML)
├── Factory/
│   └── ReportDataFactory.php          # Hydrate report DTO from query results
└── Adapter/
    └── LegacyExportAdapter.php        # Temporary wrapper for output formatting logic
```

### 2. Route & Integration Layer

**Controller:** `src/Kernel/Controller/ExportController.php`
- `GET /export` — form (filter selection, format choice)
- `POST /export` — generate + download (inline or attachment)
- `GET /export/{type}/preview` — preview without download

**Routes in `src/Kernel/app.php`:**
```php
'GET /export'           → ExportController::getExportForm
'POST /export'          → ExportController::postExport
'GET /export/preview'   → ExportController::getExportPreview
```

**Authorization:** `config/access_policy.php`
```
section:export|action:view  → Role::Judge (can view own results)
section:export|action:admin → Role::Admin (can view all exports)
```

---

## Database Layer Mapping (Query Consolidation)

### Current Files → Consolidated Repository Methods

**`includes/db/output_entries_export.db.php` (126 lines)**
→ `BrewingExportRepository::getEntriesByFilter(ExportFilter $filter): array`

Consolidates queries with sprintf patterns:
- `paid` filter → `brewPaid = 1 AND brewReceived = 1`
- `nopay` filter → `brewPaid <> 1 OR brewPaid IS NULL`
- `required` filter → `brewInfo IS NOT NULL OR brewComments...`
- `winners` filter → archive table + sort by tableNumber

**Vulnerabilities eliminated:**
- Line 4: `sprintf("WHERE id='%s'", $bid)` → prepared statement with `WHERE id = ?`
- Line 5: `sprintf("AND comp_id='%s'", $_SESSION['comp_id'])` → parameter binding
- Line 47-50: `"_".$sort` (table name concat) → whitelisted sort enum + sprintf table builder
- Line 49: `sprintf("... FROM %s", $judging_tables_db_table.$archive_suffix)` → safe enum-based suffix

**`includes/db/output_email_export.db.php` (14 lines)**
→ `BrewingExportRepository::getEmailExportData(): array`

Consolidates:
- Judges export
- Stewards export
- Staff export
- Available judges/stewards

**Vulnerabilities eliminated:**
- Line 5: `sprintf("WHERE id='%s'", $bid)` → prepared statement
- Line 12, 18, 24: `sprintf("AND b.comp_id='%s'", $_SESSION['comp_id'])` → parameter binding

**`includes/db/output_entries_export_extend.db.php`**
→ `BrewingExportRepository::getEntriesExtended(): array`

**`includes/db/output_entries_export_winner.db.php`**
→ `BrewingExportRepository::getWinnerExportData(): array`

**`includes/db/output_participants_export.db.php` (23 lines)**
→ `ParticipantExportRepository::getParticipants(ExportFilter $filter): array`

**Vulnerabilities eliminated:**
- Line 14, 9: `sprintf("WHERE ... '%s'", $bid)` → prepared statement

**`output/export.output.php` (archive query, line 86)**
→ `ExportService::loadArchivePreferences(string $archiveSuffix): array`

**Vulnerabilities eliminated:**
- Line 86: `sprintf("WHERE archiveSuffix='%s'", $filter)` → prepared statement with `WHERE archiveSuffix = ?`

---

## Implementation Strategy

### Phase 3.4 Task Breakdown

#### **Task 1: Export Domain Objects (ValueObjects, Commands, Exceptions)** [2 days]

**Deliverables:**
- `Export.php` — immutable aggregate root
- ValueObjects: ExportId, ExportFormat (enum: CSV, HTML, PDF, XML), ExportFilter (enum + business logic), ExportView, ReportData, ExportMetadata
- Commands: GenerateExportCommand, ExportBrewingCommand, ExportParticipantsCommand, ExportJudgingCommand (with Symfony validators)
- Exceptions: 3 custom exception types with `getHttpStatus()` and `isExpected()` methods

**Success check:**
- Each class instantiates; validators validate commands
- Unit tests pass

---

#### **Task 2: Export Repositories (Query Consolidation)** [4 days]

**Deliverables:**
- `ExportRepository.php` — base interface/traits
- `BrewingExportRepository.php` — consolidates 4 query files
  - `getEntriesByFilter(ExportFilter $filter): array` (replaces lines 13-50 of output_entries_export.db.php)
  - `getEmailExportData(string $exportType): array` (replaces output_email_export.db.php)
  - All queries use prepared statements via `Connection::select()`
- `ParticipantExportRepository.php` — consolidates output_participants_export.db.php
- All queries use `WHERE` placeholders `?` and bind parameters

**Critical Details:**
- Replace `sprintf()` concatenation with parameterized queries
- Enum-based filter whitelisting prevents injection in filter logic
- Archive suffix validation prevents table name injection
- All `$_SESSION['comp_id']` safely bound as parameter

**Success check:**
- Integration test: query each export type; verify results match legacy (row counts, data values)
- PHPStan: no `mysqli_*` outside Connection
- No `sprintf()` used for query construction (only table/column names via constants)

---

#### **Task 3: Export Services** [2 days]

**Deliverables:**
- `ExportService.php` — orchestrate export generation + format selection
- `BrewingExportService.php` — brewing-specific filtering logic
- `ParticipantExportService.php` — participant filtering
- `JudgingExportService.php` — judging/results filtering
- `ExportFormatterService.php` — output format handler (CSV, HTML, PDF, XML)

**Success check:**
- Unit test: `ExportService::generate()` returns ReportData with correct shape
- Test: different filters produce expected result counts
- Test: format selection (CSV vs HTML) produces valid output (mime type, encoding)

---

#### **Task 4: Export Controller & Routes** [1.5 days]

**Deliverables:**
- `ExportController.php` with methods:
  - `getExportForm(): Response` — renders filter form (GET /export)
  - `postExport(Request): Response` — POST handler; calls service; streams file (POST /export)
  - `getExportPreview(Request): Response` — preview without download (GET /export/preview)
- Register routes in `src/Kernel/app.php`
- Add access policy in `config/access_policy.php` (admin-only or role-based)

**Success check:**
- GET /export returns 200, renders form
- POST /export with valid filter returns file stream (application/csv or similar)
- PHPStan passes; no direct `output_*.db.php` calls

---

#### **Task 5: Tests (Unit + Integration + E2E)** [2 days]

**Deliverables:**

**Unit tests:**
- `ExportServiceTest.php` — test filtering logic
- `ExportFormatterServiceTest.php` — test format output (CSV rows, XML structure)
- `ExportCommandTest.php` — validate command constraints

**Integration tests:**
- `BrewingExportRepositoryIntegrationTest.php` — query each filter; verify against legacy DB state
- `ExportServiceIntegrationTest.php` — full export generation
- `ExportLegacyParity.php` — dual-path test: legacy export vs modern service; compare row counts, field values

**E2E tests:**
- `export-flow.spec.ts` — user navigates to export form, selects filter + format, downloads file, verifies content

**Success check:**
- All unit tests pass
- Integration tests pass with real DB
- E2E tests pass; file downloads work; legacy parity confirmed

---

#### **Task 6: Regression & Verification** [1 day]

**Deliverables:**
- Run full characterization suite → 0 new failures
- PHPStan level 8 → 0 errors
- Verify Phase 3.1-3.3 tests still pass (no breakage)
- Commit + merge to slim

**Success check:**
- All checks pass; Phase 3.4 ready for Phase 3.5 (or complete)

---

## Critical Considerations

### 1. Archive Table Handling
- Current code: `sprintf("SELECT * FROM %s", $judging_tables_db_table.$archive_suffix)`
- Archive suffix comes from `$filter` (user input)
- **Fix:** Whitelist valid suffixes (enum); use prepared statement for archiveSuffix filter, not table name itself
- Example: `SELECT * FROM judging_tables_2024_results WHERE archiveSuffix = ?` (safer than dynamic table names)

### 2. Output Formatting
- Legacy code mixes query logic with CSV/HTML/PDF generation
- **Extraction strategy:** Repository returns raw data array; Formatter handles output serialization
- Ensures data layer is clean; presentation concerns isolated

### 3. Session State
- `$_SESSION['comp_id']` used in many queries
- **Approach:** Pass as parameter to services; avoid global access; inject via Identity object

### 4. Performance
- Export queries are read-heavy; no N+1 expected (already optimized joins)
- Archive table queries may benefit from indexing on `archiveSuffix` (low effort)

---

## File Structure Summary

**New Files (15-17 files):**
- 6 domain files (Export aggregate, ValueObjects, exceptions)
- 4 command files (command DTOs)
- 3 service files (export services)
- 3 repository files (consolidated queries)
- Controller + Routes + Config updates (3 files)
- 8-10 test files (unit + integration + E2E)

**Delete/Deprecate (0 files):**
- Keep legacy `output/export.output.php` and `includes/db/output_*.db.php` for now (parallel routing)
- Sunset after Phase 3.5 (after 2-3 export paths tested, legacy routes rewrites deprecated)

---

## Success Criteria (Phase 3.4 Gates)

| Criterion | Definition | Passes when |
|-----------|-----------|---------|
| **All queries parameterized** | No sprintf()+WHERE, no direct concatenation in queries | Grep finds 0 instances of `sprintf("SELECT\|sprintf("WHERE` in domain layer; all via Connection::select($sql, $params) |
| **Exports work end-to-end** | Can select filter + format, download file, verify contents match legacy | E2E test passes; CSV/HTML output byte-for-byte identical to legacy or semantically equivalent |
| **Access control enforced** | Only authorized roles can access export endpoint | Controller has `#[IsGranted]` attribute; unauthorized request returns 403 |
| **No regressions** | Phase 3.1-3.3 tests still pass; characterization suite unchanged | Full test suite passes; 0 new failures |
| **PHPStan clean** | No type/SQL injection warnings | `phpstan analyze src/Domain/Export --level=8` returns 0 errors |
| **Archive safety** | Archive table doesn't suffer table name injection | `archiveSuffix` parameter validates enum; table name not built from user input |

---

## Next Steps After Phase 3.4

- **Phase 3.5:** Sunset legacy export routes (parallel routing cleanup)
- **Phase 3.6:** Other workflows (if any remain from lib/common.lib.php)
- **Phase 3 Completion:** All SQL injection vulnerabilities eliminated; lib/common.lib.php calls reduced to <20

---

## Estimated Timeline

| Task | Effort | Owner | Start | End |
|------|--------|-------|-------|-----|
| Task 1: Domain objects | 2 days | - | 2026-07-22 | 2026-07-23 |
| Task 2: Export repositories | 4 days | - | 2026-07-23 | 2026-07-27 |
| Task 3: Export services | 2 days | - | 2026-07-27 | 2026-07-28 |
| Task 4: Controller & routes | 1.5 days | - | 2026-07-28 | 2026-07-29 |
| Task 5: Tests | 2 days | - | 2026-07-29 | 2026-07-31 |
| Task 6: Regression & verification | 1 day | - | 2026-07-31 | 2026-08-01 |
| **Total** | **12.5 days** | - | **2026-07-22** | **2026-08-01** |

---

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|-----------|
| Archive table name injection (if not careful) | High | Use enum-based archive suffix validation; never concat into table name; use WHERE filtering instead |
| Export format changes (CSV/XML structure) | Medium | Unit test exact format output; compare against legacy byte-for-byte or use diff tool |
| Performance regression on large exports | Medium | Index `archive.archiveSuffix`; measure query time before/after |
| Missing export types/filters | Medium | Comprehensive E2E coverage; test all filter combinations (paid, nopay, required, winners, circuit) |

