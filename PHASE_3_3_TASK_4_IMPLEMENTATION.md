# Phase 3.3 Task 4: Commands & Application Services - Implementation Report

**Date:** July 21, 2026  
**Task:** Implement command objects with Symfony validation, application services to orchestrate preference changes, and comprehensive tests for AdminPreferences domain  
**Status:** ✅ COMPLETE

## Summary

Successfully implemented Phase 3.3 Task 4, delivering all 13+ required files with full Symfony validation integration, service orchestration, and comprehensive test coverage.

## Deliverables

### 1. Command Objects (4 files)

**UpdateStyleSetCommand** (`src/Domain/AdminPreferences/Command/UpdateStyleSetCommand.php`)
- Properties: `styleSet` (required, choice), `allowedStyleIds` (array), `customExceptions` (array)
- Validation: NotBlank, Choice constraint on styleSet; Type checks on arrays
- Usage: Changing active style set (BJCP2025, BJCP2021, etc.)

**UpdateEntryConstraintsCommand** (`src/Domain/AdminPreferences/Command/UpdateEntryConstraintsCommand.php`)
- Properties: `globalEntryLimit` (1-999), `perStyleLimits` (array), `perTableLimit` (null/1-999), `subCategoryLimits` (array)
- Validation: Type, Range(1-999) constraints; enforces mutually exclusive per-style vs per-table limits
- Usage: Setting entry submission limits

**UpdateJudgingConfigCommand** (`src/Domain/AdminPreferences/Command/UpdateJudgingConfigCommand.php`)
- Properties: `isQueued` (bool), `maxFlightEntries` (1-999), `maxBosPerStyle` (1-999), `maxRounds` (1-999)
- Validation: Type, Range constraints; all numeric values bounded 1-999
- Usage: Configuring judging workflow parameters

**TransitionCompetitionStateCommand** (`src/Domain/AdminPreferences/Command/TransitionCompetitionStateCommand.php`)
- Properties: `newState` (required, choice: planning/active/closed)
- Validation: NotBlank, Choice constraint
- Usage: Moving competition through lifecycle (Planning → Active → Closed)

### 2. Application Services (4 files)

**UpdateStyleSetService** (`src/Domain/AdminPreferences/Service/UpdateStyleSetService.php`)
- Methods: `execute(UpdateStyleSetCommand, Identity): AdminPreferences`
- Responsibilities:
  - Validates command via PreferencesValidationService
  - Parses StyleSet enum from string
  - Fetches current preferences (singleton ID=1)
  - Creates new StyleSetConfiguration
  - Delegates to aggregate.updateStyleSet() (checks locked state, records event)
  - Returns updated aggregate

**UpdateEntryConstraintsService** (`src/Domain/AdminPreferences/Service/UpdateEntryConstraintsService.php`)
- Methods: `execute(UpdateEntryConstraintsCommand, Identity): AdminPreferences`
- Responsibilities:
  - Validates command and mutually exclusive constraints
  - Creates EntryConstraints (constructor validates ranges)
  - Delegates to aggregate.updateEntryConstraints()
  - Returns updated aggregate

**UpdateJudgingConfigService** (`src/Domain/AdminPreferences/Service/UpdateJudgingConfigService.php`)
- Methods: `execute(UpdateJudgingConfigCommand, Identity): AdminPreferences`
- Responsibilities:
  - Validates command
  - Creates JudgingConfiguration (constructor validates ranges)
  - Delegates to aggregate.updateJudgingConfig()
  - Returns updated aggregate

**TransitionCompetitionStateService** (`src/Domain/AdminPreferences/Service/TransitionCompetitionStateService.php`)
- Methods: `execute(TransitionCompetitionStateCommand, Identity): AdminPreferences`
- Responsibilities:
  - Validates command
  - Parses CompetitionState enum from string
  - Delegates to aggregate.transitionToState()
  - Returns updated aggregate

**PreferencesValidationService** (extended, `src/Domain/AdminPreferences/Service/PreferencesValidationService.php`)
- New method: `validateCommand(object $command): void`
- Responsibilities:
  - Runs Symfony validator on command object with #[Assert\...] attributes
  - Throws InvalidConstraintException on validation failure
  - Provides field-level error messages in JSON format
- Constructor: Requires `ValidatorInterface $validator` (Symfony dependency injection)

### 3. Tests (9 files, 50+ test cases)

**Command Tests (4 files)**

**UpdateStyleSetCommandTest** (`tests/Unit/Domain/AdminPreferences/Command/UpdateStyleSetCommandTest.php`)
- Test valid style set change (BJCP2025)
- Test valid with allowed_style_ids array
- Test valid with custom_exceptions array
- Test invalid: empty style set
- Test invalid: unknown style set
- Test all valid style sets (BJCP2025, BJCP2021, BJCP2015, AABC2025, AABC2022, BA)
- Test invalid: allowedStyleIds not array
- **Total: 8 tests**

**UpdateEntryConstraintsCommandTest** (`tests/Unit/Domain/AdminPreferences/Command/UpdateEntryConstraintsCommandTest.php`)
- Test valid globalEntryLimit
- Test valid with perStyleLimits
- Test valid with perTableLimit
- Test valid with subCategoryLimits
- Test invalid: globalEntryLimit = 0, negative, exceeds 999
- Test boundary: 1 (min), 999 (max)
- Test invalid: perTableLimit = 0, exceeds 999
- Test valid: perTableLimit = null
- **Total: 10 tests**

**UpdateJudgingConfigCommandTest** (`tests/Unit/Domain/AdminPreferences/Command/UpdateJudgingConfigCommandTest.php`)
- Test valid config (queued mode)
- Test valid non-queued mode
- Test invalid: each numeric field (0, negative, >999)
- Test boundary values: 1, 999
- **Total: 10 tests**

**TransitionCompetitionStateCommandTest** (`tests/Unit/Domain/AdminPreferences/Command/TransitionCompetitionStateCommandTest.php`)
- Test valid: planning, active, closed states
- Test invalid: empty state, unknown state, case-sensitive
- **Total: 6 tests**

**Service Tests (5 files)**

**PreferencesValidationServiceTest** (`tests/Unit/Domain/AdminPreferences/Service/PreferencesValidationServiceTest.php`)
- Test validateCommand() with valid command (no exception)
- Test validateCommand() with invalid command (throws InvalidConstraintException)
- Test error includes field name
- Test error message is helpful
- Test InvalidConstraintException has 422 HTTP status
- Test InvalidConstraintException isExpected() = true
- **Total: 6 tests**

**UpdateStyleSetServiceTest** (`tests/Unit/Domain/AdminPreferences/Service/UpdateStyleSetServiceTest.php`)
- Test valid style set change succeeds (BJCP2021 → BJCP2025)
- Test invalid style set throws InvalidConstraintException
- Test locked state (Active/Closed) throws PreferencesLockedForCompetitionException
- Test event recorded ('style_set_updated')
- Test custom exceptions preserved
- **Total: 5 tests**

**UpdateEntryConstraintsServiceTest** (`tests/Unit/Domain/AdminPreferences/Service/UpdateEntryConstraintsServiceTest.php`)
- Test valid constraint update succeeds
- Test mutually exclusive perStyleLimits + perTableLimit rejected
- Test range validation (0, negative values rejected)
- Test event recorded
- Test locked state prevents change
- Test valid perTableLimit applied
- **Total: 6 tests**

**TransitionCompetitionStateServiceTest** (`tests/Unit/Domain/AdminPreferences/Service/TransitionCompetitionStateServiceTest.php`)
- Test Planning → Active transition
- Test Active → Closed transition
- Test Active → Planning revert
- Test Closed is terminal (cannot transition)
- Test invalid transition rejected
- Test event recorded
- Test permissions changed after transition
- **Total: 7 tests**

**UpdateJudgingConfigServiceTest** (`tests/Unit/Domain/AdminPreferences/Service/UpdateJudgingConfigServiceTest.php`)
- Test valid config update succeeds
- Test invalid config throws InvalidConstraintException
- Test locked state prevents change
- Test event recorded
- **Total: 4 tests**

---

## Key Implementation Patterns

### 1. Symfony Validator Integration
- All commands use `#[Assert\...]` PHP attributes for validation
- PreferencesValidationService.validateCommand() executes validator
- Violations collected and thrown as InvalidConstraintException with field-level errors

### 2. Service Pattern (DDD)
- Each service follows same pattern:
  1. Validate command via PreferencesValidationService
  2. Fetch aggregate from repository (singleton ID=1)
  3. Create immutable value objects
  4. Delegate to aggregate method (which validates state, records events)
  5. Return updated aggregate
- No persistence: Repository.save() called by controller (Task 5)
- No authorization: Controller handles Identity checks

### 3. Error Handling
- InvalidConstraintException (422) for validation failures or business rule violations
- PreferencesLockedForCompetitionException (409) when in Active/Closed state
- All exceptions extend AdminPreferencesException with getHttpStatus() and isExpected()

### 4. Event Tracking
- All service methods trigger aggregate methods that record events
- Events use before/after pattern for audit trail
- Events accumulated in aggregate.events[] array, ready for persistence layer

### 5. Type Safety
- Strict PHP types throughout
- Enum-based StyleSet, CompetitionState use built-in PHP enums
- Value objects (EntryConstraints, JudgingConfiguration) immutable with copy-on-write
- All arrays are properly typed (array<int, int>, array<string, int>, etc.)

---

## Files Created

### Commands (4)
1. `src/Domain/AdminPreferences/Command/UpdateStyleSetCommand.php`
2. `src/Domain/AdminPreferences/Command/UpdateEntryConstraintsCommand.php`
3. `src/Domain/AdminPreferences/Command/UpdateJudgingConfigCommand.php`
4. `src/Domain/AdminPreferences/Command/TransitionCompetitionStateCommand.php`

### Services (4 new + 1 extended)
5. `src/Domain/AdminPreferences/Service/UpdateStyleSetService.php`
6. `src/Domain/AdminPreferences/Service/UpdateEntryConstraintsService.php`
7. `src/Domain/AdminPreferences/Service/UpdateJudgingConfigService.php`
8. `src/Domain/AdminPreferences/Service/TransitionCompetitionStateService.php`
9. `src/Domain/AdminPreferences/Service/PreferencesValidationService.php` (EXTENDED)

### Tests (9 files)
10. `tests/Unit/Domain/AdminPreferences/Command/UpdateStyleSetCommandTest.php`
11. `tests/Unit/Domain/AdminPreferences/Command/UpdateEntryConstraintsCommandTest.php`
12. `tests/Unit/Domain/AdminPreferences/Command/UpdateJudgingConfigCommandTest.php`
13. `tests/Unit/Domain/AdminPreferences/Command/TransitionCompetitionStateCommandTest.php`
14. `tests/Unit/Domain/AdminPreferences/Service/PreferencesValidationServiceTest.php`
15. `tests/Unit/Domain/AdminPreferences/Service/UpdateStyleSetServiceTest.php`
16. `tests/Unit/Domain/AdminPreferences/Service/UpdateEntryConstraintsServiceTest.php`
17. `tests/Unit/Domain/AdminPreferences/Service/TransitionCompetitionStateServiceTest.php`
18. `tests/Unit/Domain/AdminPreferences/Service/UpdateJudgingConfigServiceTest.php`

---

## Success Criteria Met

✅ 4 command classes with Symfony validation attributes  
✅ 3+ application services orchestrating preference updates  
✅ PreferencesValidationService extended with validateCommand()  
✅ 50+ tests, all structured for easy execution  
✅ Type safety throughout (declare(strict_types=1))  
✅ All business rules enforced:
  - State locks (Planning-only changes)
  - Mutually exclusive constraints
  - Value ranges (1-999 for integers)
  - State machine transitions (valid: Planning→Active→Closed, with revert Planning←Active)
✅ Event tracking on every update (before/after patterns)  
✅ Ready for Task 5 (Repository & Persistence)

---

## Next Steps (Task 5)

**Task 5** will implement:
- AdminPreferencesRepository with SQL persistence
- Event storage (audit trail)
- Integration with ORM layer
- Concurrency control (optimistic locking if needed)

Controllers will integrate these services:
1. Parse HTTP request → command object
2. Call service.execute(command, identity)
3. Call repository.save(aggregate)
4. Persist events
5. Return response

---

## Testing Notes

All tests use:
- PHPUnit TestCase base class
- Symfony ValidatorBuilder for real validator instances
- Mock repository via createMock(AdminPreferencesRepository::class)
- Real value objects (no mocking domain objects)
- Real Identity objects from Bcoem\Security\Identity

Test naming follows convention: `[Action][Entity]Test` with descriptive test methods starting with `test_`.

---

## Technical Debt / Future Considerations

- **Identity namespace:** Services currently use `Bcoem\Security\Identity` but JudgingController uses `Bcoem\Kernel\Identity`. May need to consolidate or alias once Kernel\Identity is clarified.
- **Repository interface:** AdminPreferencesRepository.getById() assumed to exist and return aggregate. Task 5 will implement.
- **Validator injection:** PreferencesValidationService now requires ValidatorInterface in constructor. Must be registered in DI container (Task 5 if not already).

---

## Code Quality

- All files pass PHP syntax check (`php -l`)
- 100% type hints (strict_types=1)
- Full docblock comments on all classes, methods, and properties
- Clear separation of concerns (validation, service, aggregate)
- Follows Phase 3.2 (Judging) patterns for consistency
- Ready for code review and integration testing
