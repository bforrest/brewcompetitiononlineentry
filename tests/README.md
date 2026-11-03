# PHPUnit tests (Unit tier)

Ported from the `testing-baseline` branch (written for 3.x) and adapted to the
2.7 code on this branch. Only the Unit tier is here; the Integration and
Approval tiers still need the database prefix and schema work described in
[TESTING.md](../TESTING.md#phpunit-suite).

```bash
composer install            # first time only
vendor/bin/phpunit          # 214 tests, 13 skipped, no database needed
```

`tests/bootstrap.php` defines the path constants and stubs, then loads
`lib/common.lib.php`, so no database or `site/config.php` is needed.

## How the port treated differences between 3.x and 2.7

| Situation | What the test does |
| --- | --- |
| 2.7 behaves differently but not wrongly | The test pins 2.7's behaviour, with a comment. Examples: `in_string()` returns `NULL` (not `FALSE`) on no match; `search_array()` returns a list of matches; `currency_info()` has 28 currencies. |
| 2.7 has a bug that 3.x fixed | The test keeps the correct expectation and is **skipped** with the reason. Remove the skip when the fix is backported. |
| The function doesn't exist in 2.7 | The test was **deleted**. |

### Skipped tests (2.7 bugs, each one a backport candidate)

| Function | Bug in 2.7 |
| --- | --- |
| `designations('')` | Returns `'<br />'` instead of `''` |
| `random_generator()` (3 tests) | Result is not exactly `$digits` long (`rand(1,10)` can add two characters; the letter is optional) |
| `readable_number()` (2 tests) | `>` instead of `>=` at the 100 and 1000 boundaries: 100 gives `' '`, 1000 gives `'ten hundred '` |
| `display_array_content()` method 2 | Three `rtrim()` calls each overwrite the last, so a trailing `', '` remains |
| `getTimeZoneDateTime()` `'year'` format | Not in 2.7 |

### Deleted tests (functions not in 2.7)

`create_bs_alert()` and `create_bs_popover()` (18 tests; 3.x Bootstrap 5
helpers), `simpleEncrypt()`/`simpleDecrypt()` (2), and
`password_verify_legacy()`/`password_needs_legacy_upgrade()` (8). 2.7 checks
passwords with phpass directly in `includes/logincheck.inc.php`; that path is
covered by the Playwright security specs.

### Known noise

Two benign PHP warnings are printed (`get_timezone()` with an invalid offset,
and `search_array()` when nothing matches). They are the code's behaviour, not
test failures; `failOnWarning` is intentionally off.
