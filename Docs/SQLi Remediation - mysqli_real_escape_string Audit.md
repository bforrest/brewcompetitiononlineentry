# SQL Injection Audit: `mysqli_real_escape_string()` Usage

**Date:** 2026-07-14
**Scope:** Full-codebase grep of every `mysqli_real_escape_string()` call site, cross-referenced against how (or whether) the return value is used.
**Related:** Confirms and significantly expands P1-SEC-002 from the 2026-03-24 Technical Review (see `Technical Review Follow-up - release-3.0.3.md`).

## Headline finding

`mysqli_real_escape_string()` is called **519 times** across **27 files**. Of those, **511 calls (98.5%) discard the return value** — the function runs, computes an escaped copy of the string, and the result is thrown away. The original, unescaped variable is what actually gets used in the SQL query. Functionally, **escaping is not happening almost anywhere it's invoked** — the calls are dead code that only give the *appearance* of sanitization.

Only **8 call sites** use the function correctly.

## Two distinct anti-patterns

### Pattern A — escaping the input value, but discarding the result

```php
// includes/logincheck.inc.php:28-29 (unauthenticated login path)
mysqli_real_escape_string($connection,$loginUsername);
mysqli_real_escape_string($connection,$entered_password);
```

`$loginUsername` is unchanged after these lines. It's later interpolated directly into the login SQL query via `sprintf()`. This is the exact mechanism for **unauthenticated SQL injection on login** — no account needed.

Also present in: `qr.php:70`, `includes/process/process_delete.inc.php`, `includes/process/process_judging_scores.inc.php:17`, and others.

**Fix:** trivial — capture the return value:
```php
$loginUsername = mysqli_real_escape_string($connection,$loginUsername);
```

### Pattern B — escaping the *entire finished query string*, after building it with raw input

```php
// includes/process.inc.php:264-266
$updateSQL = sprintf("UPDATE $brewer_db_table SET brewerDiscount='%s' WHERE uid='%s'", "Y", $id);
mysqli_real_escape_string($connection,$updateSQL);
$result = mysqli_query($connection,$updateSQL) or die("A database error occurred.");
```

```php
// lib/common.lib.php:4132 — same shape, 5 sites
$updateSQL = sprintf("UPDATE %s SET ... WHERE id=%s;", $prefix."brewing", $ba_category, ...);
mysqli_real_escape_string($connection,$updateSQL);
$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
```

```php
// ajax/save.ajax.php:557,562 — same shape
$sql = sprintf("UPDATE `%s` SET %s='HJ' WHERE bid='%s' AND assignTable='%s'", $prefix.$action, $go, $input, $id);
mysqli_real_escape_string($connection,$sql);
$result = mysqli_query($connection,$sql) or die ("A database error occurred.");
```

This is **worse than Pattern A**, and *cannot* be fixed by simply capturing the return value. By the time this call runs, the SQL string is already fully assembled — if any interpolated value (`$id`, `$input`, `$go`, etc.) contained attacker-controlled data, the injection already happened during `sprintf()`. Escaping the whole query string afterward would only mangle the query's own quote characters (`'`, `` ` ``) if the result were even used — it does not, and structurally cannot, sanitize the individual values that were substituted in. This call is vestigial: it appears to be a copy-pasted habit rather than an intentional control.

**Fix:** delete the dead call; escape each interpolated *value* individually, at the point it's passed into `sprintf()` — see the working template below.

## A correct pattern already exists in this codebase

Eight sites already do this the right way — inline, per-value escaping as an argument to `sprintf()`:

```php
// includes/process/process_brewer.inc.php:593
$query_staff_assign = sprintf(
    "SELECT id,uid,staff_steward FROM %s WHERE uid='%s'",
    $prefix."staff",
    mysqli_real_escape_string($connection,$uid)
);
```

```php
// includes/process/process_judging_assignments.inc.php:70
$query_flights = sprintf(
    "SELECT id FROM %s WHERE (bid='%s' AND assignTable='%s' ...)",
    $prefix."judging_assignments",
    mysqli_real_escape_string($connection,sterilize($_POST['bid'.$random])),
    mysqli_real_escape_string($connection,sterilize($_POST['assignTable'.$random])),
    ...
);
```

This is the pattern to standardize on for the tactical fix — but see the recommendation below on why it's still second-best.

## File inventory (discarded-return sites only)

| Tier | File | Discarded calls | Exposure |
|---|---|---|---|
| **P0** | `includes/logincheck.inc.php` | 4 (lines 28,29,84,89) | Unauthenticated — login form |
| **P1** | `includes/process.inc.php` | 11 | Authenticated user actions |
| **P1** | `includes/process/process_special_best_data.inc.php` | 3 | Authenticated |
| **P1** | `includes/process/process_delete.inc.php` | 2 | Authenticated |
| **P1** | `includes/process/process_prefs.inc.php` | 1 | Authenticated |
| **P1** | `includes/process/process_judging_scores.inc.php` | 1 (line 17; line 41 is a *correct* usage) | Authenticated (judge role) |
| **P1** | `ajax/save.ajax.php` | 2 | Authenticated, AJAX |
| **P1** | `ajax/tables_mode.ajax.php` | 3 | Authenticated, AJAX |
| **P1** | `qr.php` | 1 | Public-facing QR check-in flow |
| **P2** | `lib/common.lib.php` | 5 | Shared library, multiple callers |
| **P2** | `lib/update.lib.php` | 1 | Update flow |
| **P2** | `site/bootstrap.php` | 2 | Bootstrap/init |
| **P2** | `setup.php` | 1 | Install wizard |
| **P3** | `setup/install_db.setup.php` | 2 | One-time install, admin-run |
| **P3** | `eval/install_eval_db.eval.php` | 3 | One-time install, admin-run |
| **P3** | `update/*.php` (10 files) | ~437 | One-time version-migration scripts, admin-run, not attacker-reachable in normal operation |

Full per-line listing is reproducible with:
```bash
grep -rnE "^\s*mysqli_real_escape_string\(" --include="*.php" .
```

## Remediation recommendation

**Two tracks — do both, in this order:**

### Track A (immediate, low-risk): fix the discard bug in live request paths (P0/P1/P2 rows above, ~37 sites)
- Pattern A sites: capture the return value (`$var = mysqli_real_escape_string($connection,$var);`).
- Pattern B sites: delete the dead whole-string call; move escaping onto each interpolated variable at its `sprintf()` argument position, following the `process_brewer.inc.php:593` template above.
- Start with `includes/logincheck.inc.php` — it's the only unauthenticated entry point in this list and already flagged as the top open P1 item.
- Leave the `update/*.php`, `setup/*.php`, `eval/*.php` migration scripts (P3, ~450 sites) for a later pass — they run once, only by an admin during install/upgrade, and are not part of the normal attack surface. Fixing them is cheap but not urgent.

### Track B (correct long-term fix): move to parameterized queries
`mysqli_prepare()` / `bind_param()` has **zero usage anywhere in this codebase** (confirmed by grep) — every single query, including the 8 "correct" examples above, is built by string interpolation with manual escaping as the only defense. Escaping is inherently fragile (this audit is proof: it silently degrades to a no-op with one missing `$var =`). Prepared statements remove the entire class of bug because user input is never parsed as SQL syntax in the first place, escaped or not.

```php
// current (fragile even when "correct")
$q = sprintf("SELECT * FROM %s WHERE uid='%s'", $table, mysqli_real_escape_string($connection,$uid));
$result = mysqli_query($connection, $q);

// prepared-statement equivalent
$stmt = mysqli_prepare($connection, "SELECT * FROM $table WHERE uid = ?");
mysqli_stmt_bind_param($stmt, "s", $uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
```

Given the scale (172 `mysqli_query()` call sites total), a full migration to prepared statements is a substantial refactor, not a quick patch. Recommend treating it as a separate, planned initiative — prioritized by the same tiers as Track A (unauthenticated login first, then authenticated user-input endpoints) — rather than attempting it inline with the Track A bug fix. Track A closes the immediate hole cheaply; Track B is the durable fix.

## Suggested immediate next step

Fix `includes/logincheck.inc.php` now (Track A, Pattern A, 4 lines) — it is the only unauthenticated SQL injection vector on this list and has been open since the 2026-03-24 review without being touched by the interim `7eb0d260` fix commit.
