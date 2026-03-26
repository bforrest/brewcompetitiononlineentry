# Principal Engineer Code Review
## Brew Competition Online Entry & Management (BCOEM) v3.0.2

**Review date:** 2026-03-24 · **Codebase branch:** `my-3.0.2`

---

## 1. Architecture Overview

### Technology Stack
- **Language:** PHP (procedural, no framework)
- **Database:** MySQL via raw `mysqli` extension + bundled `MysqliDb` wrapper
- **Templating:** Mixed PHP/HTML inline rendering
- **Frontend:** Bootstrap 5, jQuery, CDN-loaded assets
- **Dependency Management:** None — all vendor libraries are manually copied into `/classes/`

### Project Layout

```
/
├── index.php           # Primary request router (HTML shell)
├── index.pub.php       # Public section renderer (~1,022 lines)
├── index.legacy.php    # Legacy admin renderer (~398 lines)
├── paths.php           # Path constants + global sterilize() + session bootstrap
├── handle.php          # File upload + PDF download handler
├── site/
│   ├── config.php      # DB credentials (git-ignored)
│   ├── bootstrap.php   # Global init: loads DB, preferences into session
│   └── MysqliDb.php    # Vendored MySQL query builder
├── includes/
│   ├── db/             # ~62 query files, one per "view"
│   ├── process/        # ~36 form processor files
│   ├── process.inc.php # Central POST router / dispatcher
│   └── logincheck.inc.php
├── sections/           # View partials (~34 files)
├── admin/              # Admin view partials (~35 files)
├── lib/                # Function libraries (common.lib.php: 5,477 lines)
├── classes/            # Manually vendored libraries
└── ajax/               # AJAX endpoints (~9 files)
```

### Architectural Pattern
Pure **procedural PHP** acting as a front-controller pattern without a framework.

Request lifecycle:
1. `index.php` → `paths.php` (constants, session, `sterilize()`) → `site/config.php` (DB) → `site/bootstrap.php` → include appropriate `sections/*.sec.php` partial
2. All form submissions POST to `includes/process.inc.php`, which dispatches to `includes/process/*.inc.php` files based on `$section` / `$action` GET parameters
3. File uploads go to `handle.php` directly

There is no MVC separation, no dependency injection, no autoloading, and no namespace discipline.

---

## 2. Positive Observations

- CSRF tokens are generated with `random_bytes(32)` and validated with `hash_equals()` — correct implementation.
- `.htaccess` enforces HTTPS redirect and blocks directory listing.
- `session.cookie_httponly`, `session.use_only_cookies`, and `session.cookie_secure` are configured.
- Login failures trigger `trigger_error()` to integrate with fail2ban.
- Contact form has a honeypot field, timing analysis, and IP-based rate limiting.
- File upload handler validates both MIME type and extension.
- HTMLPurifier is used in some POST-processing paths.
- `config.php` is in `.gitignore` — credentials are not committed.

---

## 3. Critical Security Issues (P1)

### P1-SEC-001 — MD5 Used for Password Pre-Hashing
**Severity: Critical**

Every authentication path runs `md5()` on the plaintext password before passing the digest to phpass's `HashPassword()` / `CheckPassword()`. This fundamentally undermines bcrypt's protection.

**Affected files:**
- `includes/logincheck.inc.php:30`
- `includes/process/process_users_register.inc.php:137`
- `includes/process/process_users.inc.php:51,304,305,350`
- `includes/process/process_users_setup.inc.php:27`
- `includes/process/process_forgot_password.inc.php:33`

**Impact:** bcrypt's strength depends on being computationally expensive against brute force. MD5 reduces the effective password space to 32 hex characters and is precomputed in rainbow tables for billions of common passwords.

**Fix:** Pass raw plaintext directly to `password_hash($password, PASSWORD_BCRYPT)` / `password_verify()`. Migrate existing hashes by prompting password resets on next login.

---

### P1-SEC-002 — `mysqli_real_escape_string()` Return Value Discarded
**Severity: Critical**

`mysqli_real_escape_string()` returns the escaped string — it does NOT modify its argument in place. Across the codebase, the return value is silently discarded, making every call completely inert.

**Login query (most critical instance):**
```php
// includes/logincheck.inc.php:28-29
mysqli_real_escape_string($connection, $loginUsername);   // return value discarded
mysqli_real_escape_string($connection, $entered_password); // return value discarded

// Line 63: loginUsername is used UNESCAPED
$query_login = sprintf("SELECT * FROM %s WHERE user_name = '%s'",
    $prefix."users", $loginUsername);  // vulnerable
```

**Also in:** `logincheck.inc.php:84,89`, `includes/process.inc.php:265,283,293,303,335,340,346,352,358,374,384`

**Impact:** SQL injection on the login form, enabling authentication bypass, data exfiltration, and potentially RCE via `UNION SELECT INTO OUTFILE`.

**Fix:** Use parameterized prepared statements (`mysqli_prepare` / `bind_param`) for all queries.

---

### P1-SEC-003 — Reflected XSS via Unescaped Cookie Output
**Severity: Critical**

Cookie values are echoed directly into HTML attributes with no `htmlspecialchars()` wrapping.

```php
// sections/register.sec.php:397
value="<?php echo $_COOKIE['brewerBreweryName']; ?>"

// sections/contact.sec.php:141 — textarea body
<?php echo $_COOKIE['message']; ?>

// setup/admin_user.setup.php:64 — PASSWORD ECHOED IN PLAINTEXT
value="<?php echo $_COOKIE['password']; ?>"
```

**Fix:** Wrap all `$_COOKIE` / `$_GET` / `$_POST` / `$_SESSION` echoes with `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`. Remove the password cookie echo entirely.

---

### P1-SEC-004 — `display_errors` Hardcoded ON in `process.inc.php`
**Severity: Critical**

```php
// includes/process.inc.php:9-10
error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors', '1');  // unconditional, overrides DEBUG constant
```

**Fix:** `if (DEBUG) ini_set('display_errors', '1'); else ini_set('display_errors', '0');`

---

### P1-SEC-005 — Path Traversal in `handle.php` PDF Download
**Severity: Critical**

```php
// handle.php:9-11
readfile(USER_DOCS."$id.pdf");  // $id from $_GET, sterilize() does NOT strip ../
```

**Fix:** Validate `$id` against `/^[a-zA-Z0-9_-]+$/`, resolve with `realpath()`, and confirm the resolved path begins with `USER_DOCS` before calling `readfile()`.

---

### P1-SEC-006 — No Session Regeneration on Login
**Severity: Critical**

`includes/logincheck.inc.php` never calls `session_regenerate_id(true)` after successful authentication, enabling session fixation attacks.

**Fix:** Call `session_regenerate_id(true)` immediately after `$_SESSION['loginUsername'] = $loginUsername;`

---

## 4. High Severity Issues (P2)

### P2-SEC-007 — `or die(mysqli_error())` Exposes DB Internals
30+ instances across `includes/db/`, `includes/process.inc.php`, and `lib/preflight.lib.php` print MySQL error details (table names, column names, query structure) directly to the browser on any query failure.

### P2-SEC-008 — SVG Upload Allows Stored XSS
SVG is accepted as an upload type (`image/svg+xml`, `.svg`). SVGs can contain embedded `<script>` tags executed by the browser when served as `image/svg+xml`.

### P2-SEC-009 — `sterilize()` + `addslashes()` Insufficient for SQL Injection
`sterilize()` in `paths.php:166` combines HTML encoding and `addslashes()` — a pattern explicitly documented by PHP as insufficient for SQL injection protection.

### P2-SEC-010 — Encryption Key Stored in PHP Session
```php
// includes/constants.inc.php:625
$_SESSION['encryption_key'] = base64_encode(openssl_random_pseudo_bytes(32));
```
A new key per session means encrypted data is unrecoverable after session expiry. Fix: store the key in `config.php` or an environment variable.

### P2-SEC-011 — Referer-Based Process Gate is Bypassable
`HTTP_REFERER` is a client-supplied header and can be forged or omitted. The existing CSRF token check is sufficient; the Referer check adds nothing and breaks privacy-respecting browsers.

---

## 5. Performance Concerns

- **172 `SELECT *` instances** — fetches unused columns and stores entire DB rows (including password hashes) in `$_SESSION`.
- **No indexes on frequently queried columns** — `user_name`, `brewBrewerID`, `brewerEmail`, `uid`, `brewCategorySort` cause full table scans on every query.
- **Multiple redundant queries per page load** — `bootstrap.php` fires 3-5 queries on every request; `common.db.php` runs separate queries for contest info, preferences, judging preferences, and user/brewer info.
- **5,477-line monolithic `common.lib.php`** — loaded in full on every request regardless of what is actually needed.

---

## 6. Code Quality Issues

- **Three inconsistent DB access patterns** co-exist: raw `sprintf()` + `mysqli_query()`, `MysqliDb` API, and direct string concatenation (most dangerous).
- **Large commented-out dead code** in `process_forgot_password.inc.php:71-195` (~125 lines) and active `print_r()` debug calls in `convert_bjcp_2025.inc.php:41,44,47`.
- **Malformed SQL** in `includes/db/admin_judging_tables.db.php:12`: `SELECT tableNumber FROM  WHERE` — missing table name, would fatal in SINGLE mode.
- **Global variable pollution** — `$connection`, `$prefix`, `$base_url`, `$section`, `$action`, `$id` are all global. The `foreach` in `common.db.php` dumps arbitrary DB column names into `$_SESSION`.

---

## 7. Dependencies and Their State

All dependencies are manually vendored into `/classes/` with no version management and no `composer.json`.

| Library | Vendored Version | Status |
|---|---|---|
| phpass | 0.5.1 (c. 2004-2006) | Obsolete — replace with PHP's `password_hash()` |
| HTMLPurifier | 4.9.3 | Outdated — current is 4.17.0; known XSS bypasses exist in older versions |
| PHPMailer | 7.0.1 | Appears current |
| FPDF | 1.84 | Current |
| MysqliDb | Unknown | Unknown state |
| reCAPTCHA lib | Old standalone | Deprecated — use the API directly |

---

## 8. Prioritized Remediation Roadmap

### P1 — Critical (Immediate, before next deployment)

| ID | Item | File(s) | Effort |
|---|---|---|---|
| P1-1 | Replace MD5 password pre-hashing with direct `password_hash()` / `password_verify()`; migrate existing hashes | `logincheck.inc.php:30`, `process_users*.inc.php`, `process_forgot_password.inc.php` | Medium |
| P1-2 | Fix all `mysqli_real_escape_string()` calls to capture return value; convert login query path to prepared statements | `logincheck.inc.php:28-29,84,89`; entire process layer | Low per-instance, High total |
| P1-3 | Wrap all `$_COOKIE` echoes in `htmlspecialchars()`; remove password cookie echo | `sections/register.sec.php`, `sections/contact.sec.php`, `setup/admin_user.setup.php` | Low |
| P1-4 | Remove hardcoded `ini_set('display_errors','1')` from `process.inc.php` | `includes/process.inc.php:10` | Trivial |
| P1-5 | Add path traversal guard to `handle.php` PDF download | `handle.php:11` | Low |
| P1-6 | Call `session_regenerate_id(true)` after successful login | `includes/logincheck.inc.php:93` | Trivial |

### P2 — High (Within current sprint)

| ID | Item | File(s) | Effort |
|---|---|---|---|
| P2-1 | Replace `or die(mysqli_error())` with a central error handler | All `includes/db/*.db.php`, process files | High |
| P2-2 | Remove SVG from allowed upload types | `handle.php:28-29` | Low |
| P2-3 | Migrate all inline-string SQL queries to parameterized prepared statements | All `includes/db/` and `includes/process/` files | High |
| P2-4 | Move AES encryption key from `$_SESSION` to a server-side config variable | `includes/constants.inc.php:625` | Low |
| P2-5 | Remove Referer-based `$process_allowed` gate; rely solely on CSRF token | `includes/process.inc.php:79-81` | Low |
| P2-6 | Add HTTP security response headers (CSP, X-Frame-Options, X-Content-Type-Options) | `.htaccess` or global include | Low |

### P3 — Medium (Next two sprints)

| ID | Item | File(s) | Effort |
|---|---|---|---|
| P3-1 | Replace all `SELECT *` with explicit column lists; stop dumping full rows into `$_SESSION` | All `includes/db/` files | High |
| P3-2 | Enable MySQL strict mode; remove `SET sql_mode = ''` | `site/config.php:73` | Low (may require data fixes) |
| P3-3 | Upgrade HTMLPurifier from 4.9.3 to 4.17.0 | `classes/htmlpurifier/` | Medium |
| P3-4 | Introduce Composer for dependency management; replace phpass with `password_hash()` | Root-level | Medium |
| P3-5 | Add DB indexes on `user_name`, `uid`, `brewBrewerID`, `brewerEmail`, `brewCategorySort` | SQL schema | Low |
| P3-6 | Replace `rand()` / `srand()` in judging number generation with `random_int()` | `lib/process.lib.php:5,10` | Trivial |
| P3-7 | Remove `$_SESSION['password']` by excluding password hash from user session queries | `includes/db/common.db.php:287-291` | Low |
| P3-8 | Fix malformed SQL in `admin_judging_tables.db.php:12` (SINGLE mode fatal) | `includes/db/admin_judging_tables.db.php:12` | Trivial |

### P4 — Low (Backlog / Technical debt)

| ID | Item | Effort |
|---|---|---|
| P4-1 | Remove commented-out dead code and `print_r()` debug calls | Low |
| P4-2 | Break `lib/common.lib.php` (5,477 lines) into focused modules | High |
| P4-3 | Consolidate SQL access patterns to use `MysqliDb` exclusively | High |
| P4-4 | Replace `sterilize()` with context-appropriate `htmlspecialchars()` / prepared statements | High |
| P4-5 | Introduce PSR-4 autoloading and namespaces | Very High |
| P4-6 | Remove IE <= 9 browser detection code (EOL 2016) | Trivial |

---

## 9. Overall Health

**Score: 4 / 10**

The application functions correctly and demonstrates iterative improvement over time (CSRF tokens, session security settings, HTTPS enforcement, HTMLPurifier integration). However, several unresolved vulnerabilities are severe enough to warrant halting deployment until fixed.

The most dangerous combination is **P1-1** (MD5 pre-hashing) + **P1-2** (discarded escape return values) + **P1-3** (unescaped cookie output). Together they create independent, exploitable paths to authentication bypass, SQL injection, and XSS against the most trafficked parts of the application.

---

*Principal Engineer Code Review — BCOEM v3.0.2 · 2026-03-24*
