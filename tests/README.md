# BCOEM Characterization Tests

Safety-net tests that lock in the current behaviour of `lib/common.lib.php`
and `admin/default.admin.php` before refactoring. Based on the Tornhill
Crime Scene analysis — these two files are the highest-risk hotspots in the
codebase (scores 99% and 89%).

---

## Quick start

```bash
# 1. Install PHP (>=8.1 recommended) and Composer on your server/dev machine
#    e.g. on Ubuntu:  sudo apt install php-cli php-xml php-mbstring composer

# 2. Install PHPUnit
composer install

# 3. Run only the fast Tier 1 (pure-function) tests — no database needed
./vendor/bin/phpunit --testsuite Unit

# 4. Run everything (Integration suite needs a test DB — see below)
./vendor/bin/phpunit
```

Expected initial output (Tier 1 only, ~120 tests):

```
OK (120 tests, 120 assertions)
```

---

## Test tiers

| Suite | Location | DB needed? | What it covers |
|---|---|---|---|
| **Unit** | `tests/Unit/` | No | All pure functions: conversions, strings, crypto, ordinals, dates, URLs, HTML generators |
| **Integration** | `tests/Integration/` | Yes | DB-dependent functions: fee calculations, scoring, brewer data queries |
| **Approval** | `tests/Approval/` | Yes + session state | Full HTML snapshot tests for `admin/default.admin.php` |

---

## Unit test files

| File | Functions covered |
|---|---|
| `ConversionFunctionsTest.php` | `temp_convert`, `weight_convert`, `volume_convert` |
| `OrdinalAndNumberFunctionsTest.php` | `addOrdinalNumberSuffix`, `number_pad`, `readable_number`, `place_heirarchy`, `display_place`, `bjcp_rank`, `srm_color`, `open_or_closed`, `open_limit` |
| `StringUtilitiesTest.php` | `in_string`, `normalizeClubs`, `clean_up_text`, `truncate_string`, `remove_accents`, `scrub_filename`, `clean_filename`, `is_html`, `check_exension`, `admin_relocate`, `search_array`, `display_array_content` |
| `SecurityAndCryptoTest.php` | `obfuscateURL`, `deobfuscateURL`, `simpleEncrypt`, `simpleDecrypt`, `verify_token`, `random_generator`, `currency_info` |
| `HtmlGeneratorsTest.php` | `create_bs_alert`, `create_bs_popover`, `style_number_const`, `designations`, `GetSQLValueString` |
| `DateTimeFunctionsTest.php` | `get_timezone`, `greaterDate`, `getTimeZoneDateTime`, `convert_timestamp` |
| `UrlAndNavigationTest.php` | `build_public_url`, `build_admin_url`, `build_action_link`, `build_form_action`, `prep_redirect_link`, `str_osplit` |

---

## Setting up the Integration test database

The Integration suite (Phase 2) needs a separate MySQL database — **never
point it at production**.

```bash
# 1. Create the test database
mysql -u root -p -e "CREATE DATABASE bcoem_test; GRANT ALL ON bcoem_test.* TO 'test_user'@'localhost' IDENTIFIED BY 'test_pass';"

# 2. Copy config and point it at the test DB
cp site/config.php site/config.test.php
# Edit config.test.php: set $database = 'bcoem_test';

# 3. The test base class (DatabaseTestCase) handles schema and teardown
```

Integration test files (to be written in Phase 2):

- `TotalFeesTest.php` — `total_fees()`, `total_fees_paid()`
- `StyleConvertTest.php` — `style_convert()` (7 modes)
- `GetTableInfoTest.php` — `get_table_info()` (6 modes)
- `BrewerInfoTest.php` — `brewer_info()` (caret-delimited return values)
- `BestBrewerPointsTest.php` — `best_brewer_points()` (2 methods, 7 tiebreakers)
- `DataIntegrityCheckTest.php` — `data_integrity_check()`

---

## Setting up Approval tests

Approval tests (Phase 3) capture full HTML output of `admin/default.admin.php`
under known session states. First run saves the "approved" snapshot; subsequent
runs compare against it.

```bash
# Generate initial approved snapshots
php tests/Approval/generate_snapshots.php

# Then run normally
./vendor/bin/phpunit --testsuite Approval
```

Scenarios covered (to be written in Phase 3):

- Pre-judging, full admin
- Judging in progress, full admin
- Post-judging, winners visible
- Limited admin (userLevel=1)
- PayPal IPN enabled
- Evaluations enabled

---

## What to do when a test fails after a refactor

1. Read the diff — PHPUnit shows exactly what changed.
2. Decide: is this an **intentional** behaviour change or an **accidental regression**?
3. If intentional: update the test assertion and document why in a comment.
4. If accidental: revert the refactor and try a different approach.

This is the whole point of characterization tests — they make regressions
*visible* rather than *silent*.
