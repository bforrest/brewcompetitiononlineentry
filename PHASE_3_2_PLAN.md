# Phase 3.2 Implementation Plan: Judging Workflow Extraction

**Date:** 2026-07-21  
**Strategy:** Workflow-First SQL Parameterization (same as Phase 3.1)  
**Target:** Judging workflow (tables, flights, scores) with state management

---

## Context

Phase 3.1 (Entries/Brewing) is complete and merged to `slim`. Phase 3.2 extracts the **Judging workflow**, which is significantly more complex:

**Complexity factors:**
- **State transitions:** Tables go from planning → active → judged → locked (requires state machine)
- **Concurrent judge access:** Multiple judges score entries simultaneously at same table (requires optimistic locking)
- **Flight management:** Entries queued by flight number and round
- **BOS workflow:** Best-of-Show scoring runs after regular scoring (separate flow)
- **Multiple locations:** Judging can span physical locations with independent scheduling

**SQL injection vulnerabilities:** 11 concentrated in:
- `admin/judging_tables.admin.php` → `includes/process/process_judging_tables.inc.php` (table CRUD)
- `admin/judging_scores.admin.php` → `includes/process/process_judging_scores.inc.php` (score recording + BOS)
- `pub/judge.pub.php` (judge entry point)

**Core tables:**
- `judging_tables` (id, tableName, tableStyles, tableNumber, tableLocation, tableJudges, tableStewards)
- `judging_flights` (id, flightTable, flightNumber, flightEntryID, flightRound)
- `judging_scores` (id, eid, bid, scoreTable, scoreEntry, scorePlace, scoreType, scoreMiniBOS)
- `judging_scores_bos` (id, eid, bid, scoreEntry, scorePlace, scoreType)
- `judging_locations` (id, judgingDate, judgingTime, judgingLocName, judgingLocation, judgingRounds)
- `judging_assignments` (id, assignJudge, assignSteward, assignTable, assignRound)

---

## Design Decisions (5 Strategic Answers)

### 1. State Machine: TableState Enum + Transitions

**Decision:** Create `TableState` value object enforcing valid transitions.

```
Planning → Active → Judged → Locked → Archived
 ↓         ↑                    
 └─────────┘ (can revert Active→Planning in planning mode)
```

**Transitions:**
- Planning → Active: when first judge scores an entry
- Active → Judged: when admin marks table as "judging complete"
- Judged → Locked: when admin finalizes scores (irreversible)
- Any → Archived: when archiving a competition

**Rationale:** Prevents state corruption (e.g., re-opening a locked table). Audit log tracks every state change.

**Implementation:**
```php
enum TableState: string {
    case Planning = 'planning';
    case Active = 'active';
    case Judged = 'judged';
    case Locked = 'locked';
    case Archived = 'archived';
    
    public function canTransitionTo(TableState $target): bool { ... }
}
```

---

### 2. Optimistic Locking for Concurrent Writes

**Decision:** Add `version` column to `judging_scores` table; increment on every update.

**Rationale:** Judge A and Judge B both fetch entry #123's score simultaneously:
- Judge A updates score from 24 to 28 (version 1 → 2)
- Judge B tries to update from 24 to 26 (expects version 1) → fails (version is now 2)
- Judge B retries, fetches latest (28), decides on final score

**Implementation:**
```php
// In migration
ALTER TABLE judging_scores ADD version INT DEFAULT 1;

// In JudgingScoreRepository::update()
WHERE id=? AND version=?
UPDATE judging_scores SET scoreEntry=?, version=version+1 WHERE id=? AND version=?
if (affectedRows == 0) throw ConcurrentModificationException
```

---

### 3. Flight Queue: Immutable Flight List Per Table

**Decision:** `JudgingTable` owns a `FlightQueue` (list of `Flight` objects), not a separate service.

**Rationale:** Simplifies state management; queue operations (add flight, remove flight, reorder) stay with the table that owns them. Audit log records every queue mutation.

**Implementation:**
```php
class JudgingTable {
    private FlightQueue $flights;  // ordered list of Flight objects
    
    public function addFlight(Flight $flight): void {
        $this->flights->add($flight);
        $this->recordAudit('flight_added', $flight->id());
    }
    
    public function removeFlight(FlightId $id): void {
        $this->flights->remove($id);
        $this->recordAudit('flight_removed', $id);
    }
}

class Flight {
    public function __construct(
        private readonly FlightId $id,
        private readonly EntryId $entryId,
        private readonly int $flightNumber,
        private readonly int $round
    ) {}
}
```

---

### 4. BOS as Separate Aggregate

**Decision:** Create `BestOfShowScoring` aggregate (not nested under `JudgingTable`).

**Rationale:** BOS is a separate workflow:
- Happens AFTER regular judging is locked
- References judges' final scores but is independently scored
- Can be re-done without affecting regular scores
- Has own state machine (pending → scoring → locked)

**Keeps Phase 3.2 scope narrow:** Defer detailed BOS extraction to Phase 3.2b or 3.3.

**For Phase 3.2:** Create stub `BestOfShowScoring` entity + repository; detail scoring logic later.

---

### 5. Dual Routes: Legacy + Modern Coexist

**Decision:** Same as Phase 3.1: both old (`sections/judge.sec.php`) and new (`POST /judging/scores`) routes live in parallel.

**Routes:**
- GET `/judging/tables` → list of tables
- POST `/judging/tables` → create table
- GET `/judging/tables/{id}` → table detail + flight queue
- POST `/judging/tables/{id}/flights` → add flight
- DELETE `/judging/tables/{id}/flights/{flightId}` → remove flight
- POST `/judging/scores` → record judge's score (both modern + legacy routes call same service)
- GET `/judging/tables/{id}/scores` → admin view of scores at table
- POST `/judging/tables/{id}/lock` → finalize scores

**Admin routes:**
- GET `/admin/judging/dashboard` → all tables + assignment status
- POST `/admin/judging/tables/{id}/state` → transition table state

---

## File Structure

```
src/Domain/Judging/
├── JudgingTable.php              # Aggregate root
├── BestOfShowScoring.php         # Aggregate root (stub for Phase 3.2)
├── ValueObject/
│   ├── TableId.php
│   ├── JudgeId.php
│   ├── FlightId.php
│   ├── TableState.php            # enum + transitions
│   ├── Flight.php                # immutable flight item
│   └── FlightQueue.php           # ordered collection
├── Command/
│   ├── CreateJudgingTableCommand.php
│   ├── UpdateJudgingTableCommand.php
│   ├── RecordScoreCommand.php
│   ├── LockTableCommand.php
│   ├── AddFlightCommand.php
│   └── RemoveFlightCommand.php
├── Exception/
│   ├── JudgingException.php      # abstract base
│   ├── TableNotFoundException.php
│   ├── InvalidStateTransitionException.php
│   ├── ConcurrentModificationException.php
│   ├── InvalidScoreException.php
│   └── TableAlreadyLockedException.php
├── Repository/
│   ├── JudgingTableRepository.php
│   └── JudgingScoreRepository.php
├── Service/
│   ├── JudgingTableService.php
│   ├── JudgingScoreService.php   # score recording + validation
│   ├── JudgingValidationService.php
│   └── FlightQueueService.php
├── Factory/
│   ├── JudgingTableFactory.php
│   └── FlightFactory.php
└── Adapter/
    └── LegacyJudgingAdapter.php  # temporary facades
```

---

## Task Breakdown (Phase 3.2)

### Setup Phase (Week 1)

#### **Task 1: Database Migrations — State & Locking** [2 days]

**Deliverables:**
- `db/migrations/20260XXX_add_judging_table_state.php`
  - Add `tableState` column to `judging_tables` (enum: planning, active, judged, locked, archived; default: planning)
  - Add `tableStateChanged` timestamp (audit trail)
- `db/migrations/20260XXX_add_judging_scores_version.php`
  - Add `version` column to `judging_scores` (int, default 1)
  - Add `scoreUpdated` timestamp (for conflict detection)
- `db/migrations/20260XXX_add_judging_indexes.php`
  - Index `judging_scores` by `(scoreTable, version)` for state queries
  - Index `judging_flights` by `(flightTable, flightRound, flightNumber)` for queue ordering

**Success check:**
- Migrations apply without errors
- Indexes visible in `SHOW CREATE TABLE`
- Audit log table already has `action` + `entity` to track state changes

**Dependency:** None  
**Effort:** 2 days

---

#### **Task 2: TableState Enum & State Machine** [1 day]

**Deliverables:**
- `src/Domain/Judging/ValueObject/TableState.php`
  ```php
  enum TableState: string {
      case Planning = 'planning';
      case Active = 'active';
      case Judged = 'judged';
      case Locked = 'locked';
      case Archived = 'archived';
      
      public function canTransitionTo(TableState $target): bool {
          return match($this) {
              TableState::Planning => in_array($target, [TableState::Active, TableState::Archived]),
              TableState::Active => in_array($target, [TableState::Planning, TableState::Judged, TableState::Archived]),
              TableState::Judged => in_array($target, [TableState::Locked, TableState::Archived]),
              TableState::Locked => in_array($target, [TableState::Archived]),
              TableState::Archived => false,
          };
      }
      
      public function label(): string { ... }
  }
  ```
- Unit tests for transitions

**Success check:**
- All transitions work correctly
- Invalid transitions throw `InvalidStateTransitionException`
- `canTransitionTo()` is accurate

**Dependency:** Task 1  
**Effort:** 1 day

---

### Domain Layer (Week 1–2)

#### **Task 3: Flight & FlightQueue ValueObjects** [2 days]

**Deliverables:**
- `src/Domain/Judging/ValueObject/FlightId.php` — typed ID
- `src/Domain/Judging/ValueObject/Flight.php` — immutable flight (flightNumber, entryId, round)
- `src/Domain/Judging/ValueObject/FlightQueue.php` — ordered collection of flights
  - Methods: `add()`, `remove()`, `getByNumber()`, `all()`, `count()`, `iterator()`
  - Immutable: returns new instance on mutation (copy-on-write)
- Unit tests

**Success check:**
- Flight created immutably
- FlightQueue maintains order by flightNumber
- Cannot add duplicate flight numbers
- Can iterate in order

**Dependency:** Task 2  
**Effort:** 2 days

---

#### **Task 4: JudgingTable Aggregate Root** [3 days]

**Deliverables:**
- `src/Domain/Judging/JudgingTable.php`
  ```php
  class JudgingTable {
      public function __construct(
          private readonly TableId $id,
          private readonly string $name,
          private readonly TableState $state,
          private FlightQueue $flights,
          private readonly LocationId $location,
          private readonly int $entryLimit
      ) {}
      
      public function recordScore(EntryId $eid, int $score): void
      public function transitionTo(TableState $target): void
      public function addFlight(Flight $flight): void
      public function removeFlight(FlightId $fid): void
  }
  ```
- `src/Domain/Judging/ValueObject/TableId.php`
- Unit tests

**Success check:**
- JudgingTable immutable except for state/flights mutations
- State transitions validated
- Flight operations work correctly

**Dependency:** Task 3  
**Effort:** 3 days

---

#### **Task 5: JudgingScore ValueObject + JudgingScoreCommand** [1 day]

**Deliverables:**
- `src/Domain/Judging/ValueObject/Score.php` — immutable (entry, table, score, place, type, miniBOS)
- `src/Domain/Judging/Command/RecordScoreCommand.php` — with symfony/validator + version field
  ```php
  class RecordScoreCommand {
      #[Positive]
      public int $entryId;
      #[Positive]
      public int $tableId;
      #[Between(min: 0, max: 50)]
      public float $score;
      public ?string $place;
      public int $version;  // for optimistic locking
  }
  ```
- Unit tests

**Success check:**
- Score validated (score 0–50, valid entry/table IDs)
- Version field present for concurrency check
- Command validates successfully

**Dependency:** Task 4  
**Effort:** 1 day

---

#### **Task 6: Exceptions (Domain-Specific)** [1 day]

**Deliverables:**
- `src/Domain/Judging/Exception/JudgingException.php` — abstract base
- `src/Domain/Judging/Exception/TableNotFoundException.php`
- `src/Domain/Judging/Exception/InvalidStateTransitionException.php`
- `src/Domain/Judging/Exception/ConcurrentModificationException.php`
- `src/Domain/Judging/Exception/InvalidScoreException.php`
- `src/Domain/Judging/Exception/TableAlreadyLockedException.php`

Each with `getHttpStatus(): int` and `isExpected(): bool`

**Success check:**
- All exceptions instantiate correctly
- HTTP status codes appropriate (409 for concurrency, 422 for validation, 404 for not found)

**Dependency:** Task 5  
**Effort:** 1 day

---

#### **Task 7: JudgingTableRepository (All Queries)** [4 days]

**Deliverables:**
- `src/Domain/Judging/Repository/JudgingTableRepository.php`
  - Read: `getById()`, `getByLocation()`, `listByState()`, `countByState()`
  - Write: `insert()`, `update()`, `updateState()`
  - Helpers: `rowToTable()`, `tableToRow()`
- All queries use prepared statements via `Connection`
- Integration tests against real DB

**Success check:**
- All CRUD operations work
- State transitions reflected in DB
- PHPStan clean (no `mysqli_*` outside Connection)
- Integration test: insert table, verify state=planning, transition to active, verify state=active

**Dependency:** Task 2, Task 4  
**Effort:** 4 days

---

#### **Task 8: JudgingScoreRepository (Scores + Versioning)** [3 days]

**Deliverables:**
- `src/Domain/Judging/Repository/JudgingScoreRepository.php`
  - Read: `getByTableAndEntry()`, `listByTable()`, `getByIdAndVersion()`
  - Write: `insert()`, `update()` (with version check)
  - Helpers: `rowToScore()`, `scoreToRow()`
- Optimistic locking: update queries include version in WHERE clause
- Integration tests

**Success check:**
- Insert score with version=1
- Concurrent update attempt with stale version raises `ConcurrentModificationException`
- Successful update increments version
- Integration test: record score from judge A, judge B hits concurrency error, retries and succeeds

**Dependency:** Task 1, Task 5  
**Effort:** 3 days

---

#### **Task 9: JudgingValidationService** [2 days]

**Deliverables:**
- `src/Domain/Judging/Service/JudgingValidationService.php`
  - `validateCreateTable()` — check judge availability, location exists
  - `validateScore()` — check entry exists, table not locked, score valid (0–50)
  - `validateStateTransition()` — enforce state machine rules
- Wraps legacy functions for limit checking (via adapter)
- Unit tests

**Success check:**
- Score validation catches invalid scores (negative, >50)
- State transition validation prevents locked→active
- Cannot score at locked table
- Unit test: try to score at locked table, catches `TableAlreadyLockedException`

**Dependency:** Task 2, Task 6  
**Effort:** 2 days

---

#### **Task 10: JudgingTableService (Core Workflow)** [3 days]

**Deliverables:**
- `src/Domain/Judging/Service/JudgingTableService.php`
  - `create(CreateJudgingTableCommand, Identity): TableId`
  - `update(UpdateJudgingTableCommand, Identity): void`
  - `addFlight(TableId, Flight, Identity): void`
  - `removeFlight(TableId, FlightId, Identity): void`
  - `transitionState(TableId, TableState, Identity): void`
- All write methods call `AuditLogger::record()` in same transaction
- Unit tests

**Success check:**
- Create table, verify TableId returned
- Add flight, verify audit log entry
- Transition state, verify audit log + state change
- Unit test: create table, verify state=planning; add flight; transition to active; verify both in audit log

**Dependency:** Task 7, Task 9, Task 10  
**Effort:** 3 days

---

#### **Task 11: JudgingScoreService (Judge Workflow)** [2 days]

**Deliverables:**
- `src/Domain/Judging/Service/JudgingScoreService.php`
  - `recordScore(RecordScoreCommand, Identity): void` — with optimistic locking retry logic
  - `listScoresForTable(TableId): array<Score>`
  - `listScoresForEntry(EntryId): array<Score>`
- Handles `ConcurrentModificationException` with retry (up to 3 attempts)
- Validates score via `JudgingValidationService`
- Audits every score change
- Unit tests

**Success check:**
- Record score successfully
- Concurrent modification caught + retried
- Score audit logged with before/after JSON
- Unit test: simulate concurrent score updates, verify retry succeeds

**Dependency:** Task 8, Task 9  
**Effort:** 2 days

---

### Route & Integration Layer (Week 2–3)

#### **Task 12: JudgingController & Routes** [2 days]

**Deliverables:**
- `src/Kernel/Controller/JudgingController.php` with methods:
  - `getTablesList(Request): Response` — all tables + state
  - `getTableDetail(Request, int $id): Response` — table + flights + scores
  - `postCreateTable(Request): Response` — admin only
  - `postAddFlight(Request, int $tableId): Response` — admin only
  - `postRemoveFlightCommand(Request, int $tableId, int $flightId): Response` — admin only
  - `postRecordScore(Request, int $tableId): Response` — judge + admin
  - `postTransitionState(Request, int $tableId): Response` — admin only
- Register routes in `src/Kernel/app.php`:
  - `GET /judging/tables` → `getTablesList`
  - `POST /judging/tables` → `postCreateTable` (admin only)
  - `GET /judging/tables/{id}` → `getTableDetail` (admin + judge assigned to table)
  - `POST /judging/tables/{id}/flights` → `postAddFlight` (admin only)
  - `DELETE /judging/tables/{id}/flights/{flightId}` → `postRemoveFlight` (admin only)
  - `POST /judging/scores` → `postRecordScore` (judge)
  - `POST /judging/tables/{id}/state` → `postTransitionState` (admin only)
- Update `config/access_policy.php`:
  - `section:judging|action:list` → `Role::Admin`
  - `section:judging|action:score` → `Role::Judge`
  - etc.

**Success check:**
- GET `/judging/tables` returns 200, lists tables
- POST `/judging/tables` with valid data creates table (admin only)
- POST `/judging/scores` records score (judge)
- POST `/judging/tables/{id}/state` transitions state (admin)
- Unauthorized attempts return 403

**Dependency:** Task 10, Task 11  
**Effort:** 2 days

---

#### **Task 13: Templates (Admin + Judge Views)** [2 days]

**Deliverables:**
- `templates/Judging/table-list.php` — list of tables by location + state
- `templates/Judging/table-detail.php` — table info + flight queue + live scores
- `templates/Judging/judge-scoresheet.php` — judge scoring interface (entry details + score input)
- `templates/Judging/admin-state-transition.php` — state change modal
- Helper function: `stateLabel()` for state display + color coding

**Success check:**
- Judge scoresheet renders entry data correctly
- Admin can view flights + scores at table
- State transition form validates input
- Playwright loads scoresheet, inspects form, verifies entry data

**Dependency:** Task 12  
**Effort:** 2 days

---

#### **Task 14: Tests (Unit + Integration + E2E)** [3 days]

**Deliverables:**

**Unit tests** (`tests/Unit/Domain/Judging/`):
- `JudgingTableServiceTest.php` — create, add flight, transition state
- `JudgingScoreServiceTest.php` — record score, concurrent modification
- `JudgingValidationServiceTest.php` — validation rules
- `TableStateTest.php` — state machine transitions
- `FlightQueueTest.php` — queue operations

**Integration tests** (`tests/Integration/Judging/`):
- `JudgingTableRepositoryTest.php` — CRUD + state
- `JudgingScoreRepositoryTest.php` — score recording + version checks
- `ConcurrentScoringTest.php` — simulate two judges scoring same entry
- `FlightQueueIntegrationTest.php` — add/remove flights, verify order

**E2E tests** (`e2e/tests/`):
- Update `admin-journey.spec.ts` to test new routes (alongside legacy)
- Create `judge-scoring.spec.ts` — judge flows scoresheet, records scores
- Create `concurrent-scoring.spec.ts` — two judges simultaneously score entries

**Success check:**
- All unit tests pass
- All integration tests pass with real DB
- E2E: judge scoresheet loads, records score, sees it appear in admin view
- Concurrent test: two judges' scores both recorded without conflict

**Dependency:** Task 12, Task 13  
**Effort:** 3 days

---

### Validation & Completion (Week 3–4)

#### **Task 15: Dual-Path Equivalence & Regression** [2 days]

**Deliverables:**
- Run E2E tests on both legacy (`sections/judge.sec.php`) + modern routes (`POST /judging/scores`)
- Verify both paths create identical `judging_scores` rows
- Verify both paths create identical `audit_log` entries (timestamp only differs)
- Run characterization suite; zero regressions
- PHPStan analysis: no `mysqli_*` outside Connection, no legacy globals outside Legacy namespace

**Success check:**
- Dual-path tests pass 5+ runs
- Audit log entries identical (action, entity, before/after JSON)
- 0 characterization test regressions
- PHPStan clean

**Dependency:** Task 14  
**Effort:** 2 days

---

#### **Task 16: Code Review & Merge** [1 day]

**Deliverables:**
- Create commit on `slim` with all Phase 3.2 changes
- Code review: architecture consistency, SQL parameterization, test quality
- Merge to `slim` (do NOT merge to master until explicitly requested)

**Success check:**
- All changes reviewed + approved
- CI passes (all tests, PHPStan, linting)
- Merged to `slim`

**Dependency:** Task 15  
**Effort:** 1 day

---

## Summary Timeline

| Phase | Tasks | Duration | Business Value |
|-------|-------|----------|-----------------|
| Setup (Week 1) | 1–2 | 3 days | State machine foundation; version column for concurrency |
| Domain Layer (Week 1–2) | 3–11 | 22 days | Core extraction; JudgingTable + score recording working |
| Route & Integration (Week 2–3) | 12–14 | 7 days | Both old + new routes functional; tests passing |
| Validation (Week 3–4) | 15–16 | 3 days | Regression-free; merged to slim |
| **Total** | 16 tasks | **~4 weeks** | **Judging workflow fully extracted + tested** |

---

## Critical Dependencies & Risks

### Dependencies
- Task 2 must complete before Task 4 (need state machine)
- Task 7 must complete before Task 10 (service depends on repository)
- Task 8 must complete before Task 11 (score service depends on score repo)
- Task 12 must complete before Task 14 (E2E tests need controller)

### Risks

1. **Concurrent modification complexity:** Optimistic locking can be tricky if judge's browser retries requests. *Mitigation:* Add retry logic in service; test with explicit concurrent scenario.

2. **State machine brittleness:** If future phases need new states, current enum may not scale. *Mitigation:* Keep enum small; use separate "substates" in DB if needed later.

3. **Flight queue ordering:** `FlightQueue` must maintain order; if misorderd, judges see wrong entry sequence. *Mitigation:* Unit test ordering thoroughly; integration test full lifecycle.

4. **BOS scope creep:** BOS scoring is complex; Phase 3.2 creates stub. *Mitigation:* Defer detailed BOS to Phase 3.2b or Phase 3.3; stub is sufficient for regular scoring workflow.

5. **Judge role access:** Legacy code relies on session `userLevel` + judge table join. Modern code uses Identity + role-based access policy. Divergence possible. *Mitigation:* AuthenticationMiddleware reads session exactly once; policy rules tested E2E.

---

## Next Steps

1. **Review & approve** task breakdown (or request adjustments)
2. **Start Task 1** (migrations) once approved
3. **Parallel execution:** Tasks 1–2 can run in parallel with Task 3–5
4. **Each task has explicit success criteria** — don't move to next until criteria met

---

## Critical Files to Create/Modify

**Create (38+ files):**
- Domain objects: 10 files (ValueObject, Aggregate, Commands)
- Exceptions: 7 files
- Repositories: 2 files
- Services: 4 files
- Controllers: 1 file
- Templates: 3 files
- Tests: 12+ files
- Migrations: 3 files

**Modify (3 files):**
- `src/Kernel/container.php` — register services
- `src/Kernel/app.php` — add routes
- `config/access_policy.php` — add judging permissions
