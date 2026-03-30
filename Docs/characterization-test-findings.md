# Characterization Test Findings — `lib/common.lib.php`

**Project:** Brew Competition Online Entry & Management (BCOE&M)
**Date:** 2026-03-28
**Scope:** Tier 1 unit characterization tests — pure functions with no database dependency
**Test suite:** `tests/Unit/` — 235 tests, 259 assertions, 0 failures, 5 intentional skips
**Method:** Tests written to pin *current* behavior as a refactoring safety net (Code as a Crime Scene / characterization test approach). Bugs below are cases where current behavior is demonstrably wrong or likely unintentional.

---

## How to Read This Document

Each entry follows the same structure:

- **Location** — file and line numbers in the source
- **Severity** — High / Medium / Low
- **What it does now** — the pinned (buggy) behavior captured by the characterization test
- **What it should do** — the correct intended behavior
- **Recommended fix** — the minimal code change

Severity ratings:
- **High** — produces silently wrong results that callers cannot easily detect; likely causes real bugs in production
- **Medium** — incorrect in edge cases, or breaks callers that rely on documented behavior
- **Low** — PHP deprecation warning or cosmetic off-by-one that only manifests at boundary values

---

## BUG-001 — `in_string()` returns `null` instead of `false` on no match

**Location:** `lib/common.lib.php`, lines 59–61
**Severity:** High

**Current behavior:**

```php
function in_string($needle, $haystack) {
    if (strpos(strtolower($haystack), strtolower($needle)) !== false) {
        return true;
    }
    // no else branch — falls off the end, returns null
}
```

When `$needle` is not found, the function returns `null` (PHP's implicit return value) rather than `false`. Any caller doing a strict comparison (`=== false`) will get the wrong answer because `null !== false`. Callers doing a loose comparison (`== false`) may accidentally pass because `null == false` is `true` in PHP, but this is fragile and inconsistent.

**What it should do:** Return `false` explicitly when the needle is not found.

**Recommended fix:**

```php
function in_string($needle, $haystack) {
    if (strpos(strtolower($haystack), strtolower($needle)) !== false) {
        return true;
    }
    return false;
}
```

---

## BUG-002 — `search_array()` returns array-of-arrays, and `null` on no match

**Location:** `lib/common.lib.php`, lines 41–57
**Severity:** High

**Current behavior:**

```php
function search_array($array, $key, $value) {
    // $result is never initialized
    foreach ($array as $k => $val) {
        if ($val[$key] == $value) {
            $result[] = $val;   // appends the full row
        }
    }
    return $result ?? null;    // null if nothing matched
}
```

Two issues:

1. **Return shape:** The function returns an *array of matching rows* (e.g., `[['id'=>2,'name'=>'Bob']]`), not the matched row itself. Callers expecting a single flat row will receive an extra nesting level.
2. **No-match return:** Because `$result` is never initialized, when nothing matches the function returns `null` (not `false` or an empty array). Callers checking `=== false` will never see a "not found" signal.

**What it should do:** Either (a) return the first matching row directly and `false`/`null` on no match, documenting that behavior, or (b) always return an array (possibly empty) so callers can do `count()` or `empty()`.

**Recommended fix (option a — return first match):**

```php
function search_array($array, $key, $value) {
    foreach ($array as $val) {
        if ($val[$key] == $value) {
            return $val;
        }
    }
    return false;
}
```

**Recommended fix (option b — always return array):**

```php
function search_array($array, $key, $value) {
    $result = [];
    foreach ($array as $val) {
        if ($val[$key] == $value) {
            $result[] = $val;
        }
    }
    return $result;
}
```

---

## BUG-003 — `build_public_url()` ignores its `$sef` parameter

**Location:** `lib/common.lib.php`, line 154
**Severity:** High

**Current behavior:**

```php
function build_public_url($page, $extra = "", $sef = "") {
    // ...
    if ($_SESSION['prefsSEF'] == "Y") {   // reads session, never reads $sef
        // SEF path
    } else {
        // non-SEF path
    }
}
```

The `$sef` parameter is declared but never referenced inside the function body. SEF (Search Engine Friendly URL) mode is controlled exclusively by `$_SESSION['prefsSEF']`. A caller passing `$sef = "Y"` or `$sef = "N"` will have their value silently ignored.

**What it should do:** Either use the `$sef` parameter (allowing the caller to override the session value), or remove the parameter from the signature to avoid misleading callers.

**Recommended fix:**

```php
function build_public_url($page, $extra = "", $sef = "") {
    $useSef = ($sef !== "") ? $sef : ($_SESSION['prefsSEF'] ?? "");
    if ($useSef == "Y") {
        // SEF path
    } else {
        // non-SEF path
    }
}
```

**Note:** This function also has a PHP 8.x deprecation — `$sef` is an optional parameter that comes after required parameters `$page`. See DEP-002.

---

## BUG-004 — `build_admin_url()` is entirely commented out

**Location:** `lib/common.lib.php`, lines 177–199
**Severity:** High

**Current behavior:** The entire function body is wrapped in a `/* */` block comment. Calling `build_admin_url()` results in a fatal "undefined function" error.

**What it should do:** If the function is intentionally removed, it should be deleted and all call sites updated. If it was temporarily disabled (e.g., during a refactor), it should be restored and tested. Having a dead function signature sitting in the file with its body commented out is a maintenance hazard.

**Recommended fix:** Either restore the function or delete the dead code entirely. Search for all call sites with `grep -r "build_admin_url" .` before taking action.

---

## BUG-005 — `designations()` returns `"<br />"` for an empty string input

**Location:** `lib/common.lib.php`, lines 63–70 (approximate)
**Severity:** Medium

**Current behavior:**

```php
function designations($designations) {
    $output = "";
    foreach (explode(",", $designations) as $d) {
        $output .= "<br />" . trim($d);
    }
    return $output;
}
```

`explode(",", "")` returns `[""]` — an array with one empty string element. The loop runs once, producing `"<br />"`. Callers expecting an empty string for an empty-designation entry will instead get a lone `<br />` tag in their HTML.

**What it should do:** Return `""` when the input is empty or contains only whitespace.

**Recommended fix:**

```php
function designations($designations) {
    if (trim($designations) === "") return "";
    $output = "";
    foreach (explode(",", $designations) as $d) {
        $output .= "<br />" . trim($d);
    }
    return $output;
}
```

---

## BUG-006 — `display_array_content()` rtrim bug leaves trailing separator in method `"2"`

**Location:** `lib/common.lib.php`, lines 212–214
**Severity:** Medium

**Current behavior:**

```php
function display_array_content($array, $method = "1") {
    $a = implode(", ", $array);  // e.g. "a, b, "  (trailing separator if array built with trailing comma)
    if ($method == "1") {
        return "<ul>" . /* ... */ . "</ul>";
    }
    if ($method == "2") {
        $b = rtrim($a, ", ");  // intended to strip trailing ", "
        return $b;             // BUG: should return $b, but line actually re-reads $a
    }
    // method "3" etc.
}
```

The rtrim is applied to `$a` and stored in `$b`, but the `return` statement on the next line returns `$a` again instead of `$b`. Result: the trailing `", "` is never stripped for method `"2"`. The characterization test pins this behavior as `"a, b, "` (with trailing separator).

**What it should do:** Method `"2"` should return the comma-separated string with no trailing separator.

**Recommended fix:**

```php
if ($method == "2") {
    $b = rtrim($a, ", ");
    return $b;   // verify that $b (not $a) is on this line
}
```

---

## BUG-007 — `random_generator()` never produces exactly `$digits` characters

**Location:** `lib/common.lib.php`, lines 385–416
**Severity:** Medium

**Current behavior:**

```php
function random_generator($digits = 10, $method = "1") {
    $random = "";
    $i = 0;
    while ($i < $digits) {
        // pick a character from the pool...
        $random .= $char;        // appends one character
        $random .= rand(1, 10);  // ALSO appends a random digit 1-9 every iteration
        $i++;
    }
    return substr($random, 0, $digits);
}
```

Each loop iteration appends *two* things: the selected character AND a random digit from `rand(1,10)` (which produces 1–9 as strings, but also `10` as a two-character string). The final `substr` clips back to `$digits`, but the composition of the output is non-deterministic — the ratio of "pool characters" to "injected digits" varies per call. The length is correct after `substr`, but the character distribution is skewed.

Additionally, **method `"1"` is alphanumeric and method `"2"` is numeric-only** — the opposite of what the method numbers imply intuitively.

**What it should do:** Produce exactly `$digits` characters drawn from the appropriate pool with uniform distribution.

**Recommended fix:**

```php
function random_generator($digits = 10, $method = "1") {
    $pool = ($method == "2")
        ? '0123456789'
        : 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $random = "";
    for ($i = 0; $i < $digits; $i++) {
        $random .= $pool[random_int(0, strlen($pool) - 1)];
    }
    return $random;
}
```

Note: `random_int()` is cryptographically secure; prefer it over `rand()` for token generation.

---

## BUG-008 — `display_place()` unconditionally requires `config.php` (hits database on every call)

**Location:** `lib/common.lib.php`, line 2631
**Severity:** Medium

**Current behavior:**

```php
function display_place($place, $method = 0) {
    require(ROOT . "config.php");   // line 2631 — runs BEFORE any method check
    // ...
    if ($method == 0) {
        return $place . ordinalSuffix($place);  // pure, no DB needed
    }
    if ($method == 1) {
        // DB query...
    }
}
```

`config.php` is required unconditionally at the top of the function, before checking `$method`. This means even `method = 0` (which is a pure ordinal-suffix operation) triggers a database connection attempt. In environments where the database is unavailable (tests, CLI scripts, etc.) the function fails regardless of which method is requested.

**What it should do:** Only require `config.php` (and open a DB connection) for methods that actually need the database.

**Recommended fix:**

```php
function display_place($place, $method = 0) {
    if ($method == 0) {
        return $place . ordinalSuffix($place);
    }
    require(ROOT . "config.php");
    // DB-dependent methods below...
}
```

---

## BUG-009 — `readable_number()` off-by-one: boundary values 100 and 1000 produce wrong output

**Location:** `lib/common.lib.php`, lines 3135 and 3145
**Severity:** Low

**Current behavior:**

```php
// Hundreds
if ($a > 100) { ... }   // should be >= 100; misses exact value 100

// Thousands loop
while ($a > $p) { ... } // should be >= $p; misses exact value 1000
```

- `readable_number(100)` → `' '` (a space) instead of `'one hundred'`
- `readable_number(1000)` → `'ten hundred '` instead of `'one thousand'`
- `readable_number(101)` and `readable_number(1001)` work correctly because `101 > 100` and `1001 > 1000` are both `true`

**What it should do:**

- `readable_number(100)` → `'one hundred'`
- `readable_number(1000)` → `'one thousand'`

**Recommended fix:**

Line 3145: change `if ($a > 100)` to `if ($a >= 100)`
Line 3135: change `while ($a > $p)` to `while ($a >= $p)`

---

## BUG-010 — `verify_token()` hits the database despite appearing to be a utility function

**Location:** `lib/common.lib.php`, lines 4346–4378
**Severity:** Medium (architectural / testability concern)

**Current behavior:** The function signature `verify_token($token)` looks like a pure validation utility, but the implementation immediately requires `config.php` and runs a SQL query against the `tokens` table. There is no way to test this function without a live database connection.

**What it should do:** This is an architectural observation rather than a correctness bug — the function does what it's supposed to do. However, the tight coupling to the database makes it impossible to unit-test and difficult to reuse in CLI or batch contexts.

**Recommended fix (for Phase 2 refactoring):** Extract the database lookup into a repository class or a separate `fetch_token()` function, leaving `verify_token()` to receive a token row and perform pure validation logic. Move tests for this function to the Integration test suite (Tier 2) where a seeded test database is available.

---

## PHP Deprecations (PHP 8.2+/8.5+)

These are not behavioral bugs today but will become errors in future PHP versions. PHP 8.5 already emits deprecation notices for all of them; they will become fatal errors in PHP 9.

---

### DEP-001 — `(double)` cast deprecated; use `(float)`

**Location:** `lib/common.lib.php`, line 386
**Severity:** Low

```php
// Current (deprecated):
$value = (double) $input;

// Fix:
$value = (float) $input;
```

---

### DEP-002 — Optional parameter before required parameter in `build_public_url()`

**Location:** `lib/common.lib.php`, line 154
**Severity:** Low

```php
// Current (deprecated):
function build_public_url($page, $extra = "", $sef = "") { ... }
//                                 ^^^^^^^^^^^ optional before required $page
```

Actually `$page` is required and `$extra`/`$sef` are optional — this is the correct order and not itself the problem. Re-check the exact deprecation message in test output; it may relate to a different function signature at this line. The deprecation notice should be inspected against the exact PHP 8.5 message to identify the precise cause.

---

### DEP-003 — Case statements using `;` instead of `:`

**Location:** `lib/common.lib.php`, lines 2398–2399
**Severity:** Low

```php
// Current (deprecated in PHP 8.5):
switch ($rank) {
    case "GM";    // should be colon, not semicolon
    case "NM";
    // ...
}

// Fix:
    case "GM":
    case "NM":
```

---

### DEP-004 — Optional parameter before required parameter in `eval_exits()`

**Location:** `lib/common.lib.php`, line 4630
**Severity:** Low

The function signature has an optional parameter appearing before a required one, triggering a PHP 8.5 deprecation notice. Reorder so all required parameters come first.

---

## Behavioral Observations (Not Bugs, But Surprising)

These are behaviors that were unexpected during test writing but are arguably intentional design decisions. They are documented here so future developers do not re-discover them and waste time.

### OBS-001 — `remove_accents()` maps `Ü` → `"Ue"` and `Æ` → `"Ae"` (not `"U"` / `"AE"`)

**Location:** `lib/common.lib.php`, lines 4675+

The `$chars` replacement array contains triplicate keys. PHP's `strtr()` uses last-definition-wins for duplicate keys. Late entries in the array are German transliteration conventions (`Ü→Ue`, `Ö→Oe`, `Æ→Ae`) that override the earlier single-letter Latin-1 substitutions. This is probably intentional for German beer competition use cases, but it means `remove_accents("Über")` returns `"Ueber"` not `"Uber"`.

### OBS-002 — `GetSQLValueString()` with type `"double"` returns a *quoted* string, not a float literal

**Location:** `lib/common.lib.php`, lines 529–557

`GetSQLValueString(3.14, "double")` returns `'3.14'` (the SQL string literal with quotes) not `3.14` (a bare numeric literal). For MySQL this is harmless because MySQL coerces quoted numbers in numeric columns, but it is not technically correct SQL for a floating-point value.

### OBS-003 — `search_array()` uses loose comparison (`==`)

**Location:** `lib/common.lib.php`, line 48

The match check `if ($val[$key] == $value)` uses loose equality. Passing `$value = 0` will match any row where the column is falsy (empty string, null, false, "0"). This is a classic PHP loose-comparison pitfall. Consider switching to `===` if type-safe matching is desired.

---

## Summary Table

| ID | Function | Line(s) | Severity | Category |
|----|----------|---------|----------|----------|
| BUG-001 | `in_string()` | 59–61 | High | Wrong return type |
| BUG-002 | `search_array()` | 41–57 | High | Wrong return shape + type |
| BUG-003 | `build_public_url()` | 154 | High | Ignored parameter |
| BUG-004 | `build_admin_url()` | 177–199 | High | Entire function missing |
| BUG-005 | `designations()` | 63–70 | Medium | Empty-string edge case |
| BUG-006 | `display_array_content()` | 212–214 | Medium | rtrim variable typo |
| BUG-007 | `random_generator()` | 385–416 | Medium | Skewed distribution + wrong length logic |
| BUG-008 | `display_place()` | 2631 | Medium | Unnecessary DB dependency |
| BUG-009 | `readable_number()` | 3135, 3145 | Low | Off-by-one at boundary values |
| BUG-010 | `verify_token()` | 4346–4378 | Medium | Untestable DB coupling |
| DEP-001 | `random_generator()` | 386 | Low | `(double)` cast deprecated |
| DEP-002 | `build_public_url()` | 154 | Low | Optional-before-required param |
| DEP-003 | `bjcp_rank()` | 2398–2399 | Low | Case `;` instead of `:` |
| DEP-004 | `eval_exits()` | 4630 | Low | Optional-before-required param |

---

## Recommended Fix Order

1. **DEP-003** — Fix the `bjcp_rank()` semicolon case statements. Trivial one-line change, eliminates noisy deprecation warnings immediately.
2. **BUG-001 / BUG-002** — Fix `in_string()` and `search_array()` return types. High-severity, minimal code change, high test coverage confidence.
3. **BUG-005** — Fix `designations("")` empty-string guard. One-liner.
4. **BUG-006** — Fix the `display_array_content()` rtrim variable name. One character change (`$a` → `$b`).
5. **BUG-009** — Fix `readable_number()` off-by-one. Two character changes (`>` → `>=`).
6. **BUG-003** — Fix `build_public_url()` to honour its `$sef` parameter. Low risk with existing session-based tests.
7. **BUG-004** — Decide fate of `build_admin_url()`. Needs investigation of call sites before touching.
8. **BUG-007** — Rewrite `random_generator()` with uniform distribution.
9. **BUG-008** — Refactor `display_place()` to guard the DB require behind a method check.
10. **DEP-001 / DEP-002 / DEP-004** — Remaining deprecations; fix during any other pass through those functions.
11. **BUG-010** — Architectural refactor of `verify_token()`. Phase 2 work.

---

*Generated from characterization test session — 2026-03-28. All expected values in the test suite reflect current (buggy) behavior so that tests remain green during refactoring. Update test assertions when bugs are intentionally fixed.*
