# Technical Review Follow-up
## Brew Competition Online Entry & Management (BCOEM) — `release-3.0.3`

**Review date:** 2026-07-08 · **Baseline reviewed against:** [Principal Engineer Code Review, v3.0.2](https://github.com/bforrest/brewcompetitiononlineentry/blob/master/Docs/Technical%20Review.md) (2026-03-24, `my-3.0.2`)

This follow-up re-checks every finding from the original review against the current `release-3.0.3` branch, and adds a new analysis of multi-tenancy feasibility (one Apache2 instance hosting multiple independent competitions).

---

## 1. What changed since the original review

One commit — **`7eb0d260`, "fix: patch multiple security vulnerabilities (SQLi, XSS, info disclosure)"** — landed between the two reviews and fixed a real subset of the P1/P2 list. Recent commits `96e34f57` (#1701) and `a59676ff` (#1696) are unrelated UI/bug fixes and did not touch any security-relevant code.

| ID | Finding | Status on `release-3.0.3` |
|---|---|---|
| P1-SEC-001 | MD5 pre-hash before phpass | ❌ **STILL PRESENT** |
| P1-SEC-002 | Discarded `mysqli_real_escape_string()` / no prepared statements | ❌ **STILL PRESENT** |
| P1-SEC-003 | Unescaped `$_COOKIE` echo (XSS) | ✅ **FIXED** |
| P1-SEC-004 | Hardcoded `display_errors=1` | ✅ **FIXED** (hardcoded to `'0'`, still ignores `DEBUG`) |
| P1-SEC-005 | Path traversal in `handle.php` PDF download | ❌ **STILL PRESENT** |
| P1-SEC-006 | No `session_regenerate_id()` on login | ❌ **STILL PRESENT** |
| P2-SEC-007 | `or die(mysqli_error())` info disclosure | 🟡 **PARTIALLY FIXED** (13 instances remain) |
| P2-SEC-008 | SVG upload allowed | ❌ **STILL PRESENT** |
| P2-SEC-009 | `sterilize()` insufficient for SQLi | ❌ **STILL PRESENT** (unchanged) |
| P2-SEC-010 | Encryption key in `$_SESSION` | ❌ **STILL PRESENT** (unchanged) |
| P2-SEC-011 | Referer-based process gate | ❌ **STILL PRESENT** |

**Net: 2 of 6 P1s fixed, 1 of 5 spot-checked P2s partially fixed.** The most dangerous combination flagged in the original review — MD5 pre-hashing + discarded escaping + unescaped output — is now only **one-third mitigated**: the XSS leg (P1-SEC-003) is closed, but the auth-bypass leg (MD5, P1-SEC-001) and the SQL-injection leg (P1-SEC-002) are both untouched.

---

## 2. Detailed security findings — still open

### P1-SEC-001 — MD5 password pre-hashing (Critical, unresolved)
`md5()` is still applied before every `HashPassword()`/`CheckPassword()` call, at essentially the same locations as before:
- `includes/logincheck.inc.php:30`
- `includes/process/process_users_register.inc.php:137`
- `includes/process/process_users.inc.php:51, 304-305, 350`
- `includes/process/process_users_setup.inc.php:27`
- `includes/process/process_forgot_password.inc.php:33`
- **New instance found, not in the original list:** `includes/process/process_comp_info.inc.php:125,233` (contest check-in password)

**Fix (unchanged from original):** pass plaintext directly to `password_hash()`/`password_verify()`; migrate existing hashes via forced reset on next login.

### P1-SEC-002 — SQL injection via discarded escape return values (Critical, unresolved)
`includes/logincheck.inc.php:28-29,84,89` and 11 more sites in `includes/process.inc.php` (lines 265, 283, 293, 303, 335, 340, 346, 352, 358, 374, 384) still call `mysqli_real_escape_string()` and discard the result. The login query at `logincheck.inc.php:41` still builds SQL via `sprintf()` with the **unescaped** `$loginUsername`. Commit `7eb0d260` touched these two files but only replaced the `die(mysqli_error())` error-disclosure calls — it left the actual injection vector untouched.

**This is the single highest-priority item to fix before the next deploy** — it's an unauthenticated SQL injection on the login form.

### P1-SEC-005 — Path traversal in `handle.php` (Critical, unresolved)
`handle.php:11` — `readfile(USER_DOCS."$id.pdf")` — `$id` is only run through `sterilize()` (`includes/url_variables.inc.php:36`), which does HTML-entity encoding + `addslashes()` but never strips `.`/`/`. No `realpath()` or basename validation exists.

### P1-SEC-006 — No session regeneration on login (Critical, unresolved)
No `session_regenerate_id(true)` call exists in the login success path (`logincheck.inc.php:80-105`). Two unrelated `session_regenerate_id()` calls exist elsewhere in the code (`includes/db/common.db.php:43` and `process_judging_preferences.inc.php:291`) but they address session-tampering detection on page load, not post-login fixation — they do not cover this gap.

### Other open items
- **P2-SEC-008 (SVG upload / stored XSS):** `handle.php:28-29` still allows `image/svg+xml` / `.svg`.
- **P2-SEC-009 (`sterilize()`):** unchanged — still HTML-encode + `addslashes()`, explicitly documented by PHP as insufficient against SQL injection.
- **P2-SEC-010 (encryption key in session):** unchanged, `includes/constants.inc.php:626`.
- **P2-SEC-011 (Referer gate):** unchanged, `includes/process.inc.php:79-80,136,189-194`.
- **P2-SEC-007 (DB error disclosure):** 13 instances remain in `includes/db/admin_common.db.php` (11 sites) and `includes/db/scores_bestbrewer.db.php` (2 sites).

### Recommended immediate priority order for `release-3.0.3`
1. **P1-SEC-002** (SQLi on login) — unauthenticated, highest impact.
2. **P1-SEC-001** (MD5 pre-hash) — undermines the one good crypto decision (bcrypt) already in place.
3. **P1-SEC-005** (path traversal) — authenticated but straightforward file disclosure.
4. **P1-SEC-006** (session fixation) — trivial fix, still open.
5. Remaining P2 items per the original roadmap.

---

## 3. Multi-tenancy feasibility: one Apache2 instance, multiple discrete competitions

### What already works today
BCOEM already has a **documented, working mechanism for multiple independent competitions to share one MySQL database**: a table-prefix variable.
- `site/config.php:107` — `$prefix = '';`, with an explicit comment block (`config.php:87-105`) describing this as the supported way for separate installs to share one DB.
- `includes/db_tables.inc.php:19-44` builds every table name as `$prefix.'tablename'`, and 49 of the 62 files in `includes/db/` route through it.
- Session isolation across co-located installs also already works: `config.php:109-121` (`$installation_id`) → `paths.php:222-223,239` (`md5($installation_id)` used as `session_name()`), so multiple installs on one domain don't collide on cookies.

**This is exactly what today's "separate deployment per competition owner" model already is** — each owner's docroot/vhost has its own git-ignored `config.php` (own DB creds, own `$prefix`, own `installation_id`). It is *not* one running instance sharing a DB/login session across competitions — it's N independent instances that merely *could* point at the same physical MySQL server without colliding.

### What does NOT exist today
- **`$prefix`/DB selection is static per deployment, not dynamic per request.** No code path picks a table-prefix or database based on hostname, subdomain, or URL — `config.php` is one file compiled into one deployment.
- **No shared user/identity layer.** Each prefixed table-set has its own complete, isolated `users` table (`sql/bcoem_baseline_3.0.X.sql:1467-1481`) with no competition column and no cross-reference to any other prefix's `users` table. `users.id` is a purely local auto-increment used as the FK target for `brewer.uid` — there is no global identity concept.
- **Competition data is a singleton, not a multi-row concept.** `contest_info`, `preferences`, and `judging_preferences` are read via `WHERE id='1'` (`includes/db/common.db.php:59-102`) and dumped flat into non-namespaced `$_SESSION` keys — there is exactly one "current competition" per table-set, full stop.
- **File storage would collide under one shared instance.** `USER_DOCS`/`USER_IMAGES` (`paths.php:31-33`) are global constants with no competition segmentation, and files are named/served by raw numeric entry ID (`handle.php:11,86-95`). Two competitions sharing one docroot would silently overwrite each other's scoresheets/label images at matching IDs.

### Dead groundwork worth knowing about (don't build on this)
The codebase contains a **`SINGLE` mode** with `comp_id`-scoped queries scattered across `includes/db/*.db.php`, `includes/process.inc.php`, `output/*.output.php`, etc. — this looks like prior multi-competition work, but it is non-functional:
- `paths.php:52` — `define('SINGLE', FALSE)`, hardcoded off, no config toggle.
- It depends on an `SSO/` directory that was deleted from the repo in 2016 (commit `122179c5`).
- The `comp_id` and `brewerCompParticipant` columns those queries reference **do not exist** in the shipped schema (zero matches in `sql/bcoem_baseline_3.0.X.sql`).
- At least one `SINGLE`-mode query is syntactically broken: `includes/db/admin_judging_tables.db.php:12` drops its table name due to an argument-count mismatch in `sprintf()`.

It's useful only as a naming precedent (`$_SESSION['comp_id']`, ~30 call sites already show which queries would need a real filter) — not as working code to resurrect as-is.

Two adjacent, **currently functional** precedents are more useful as design inspiration:
- **`HOSTED` mode** (`paths.php:50`) — already shares one read-only reference table (`bcoem_shared_styles`) across independently-prefixed installs, avoiding duplication of BJCP style-guide data. Good precedent for "shared reference data, isolated competition data."
- **Year-archive suffixing** (`includes/db_tables.inc.php:45-71`) — appends `_2023` etc. to table names for prior-year data of the *same* competition. Precedent for "same schema, runtime-selected table-name suffix," architecturally close to what real concurrent-competition table selection would need.

### Two viable models

**Model B — separate table-set per competition, shared user directory** *(lower effort, lower regression risk — recommended starting point)*
1. Add one small shared identity table/service (`global_users`: email, password hash) outside any competition's prefixed set.
2. On registration: create/find the `global_users` row, then create the competition-local `users`/`brewer` row as today, storing a new `global_user_id` column alongside it.
3. On login: check `global_users` first, then resolve to the local `users` row for whichever competition/prefix the request targets.
4. Add a "my competitions" switcher backed by a small `global_user_id → prefix/db` mapping table; make prefix/DB selection dynamic per request (subdomain, URL segment, or post-login choice) instead of hardcoded in `config.php`.
5. Segment `USER_DOCS`/`USER_IMAGES` by prefix if competitions will share one docroot/process (not required if each competition keeps its own docroot behind a shared identity front-door).
6. Leaves the ~150 existing prefix-scoped SQL call sites untouched — this is what makes it cheaper and lower-risk.

**Model A — multiple competitions in one DB, fully shared users** *(higher effort, higher regression risk)*
1. Add `competition_id` (resurrecting the `comp_id` name/idea, but with real schema support this time) to every per-competition table: `contest_info`, `preferences`, `judging_preferences`, `brewing`, `judging_scores*`, `judging_flights`, `judging_assignments`, `judging_tables`, `judging_locations`, `sponsors`, `staff`, `special_best_*`, `payments`, `drop_off`, etc.
2. Replace singleton `WHERE id='1'` reads with `WHERE id = ?` driven by a real, live `$_SESSION['comp_id']`.
3. Rework the ~150+ raw-SQL call sites that assume a single implicit competition — this is the bulk of the work and, with no automated test suite in the repo, the highest regression risk in the whole codebase.
4. Build a real "current competition" selector (the dead `$_SESSION['comp_id']` skeleton shows roughly where, but the working `SSO`-based mechanism is gone from history and would need to be rebuilt).
5. Fix the file-storage collision problem — now unavoidable since one shared install means one shared `USER_DOCS`/`USER_IMAGES`.
6. Shared brewer identity falls out "for free" once entries are keyed by `competition_id` instead of by table-set — this is the one place Model A is actually simpler than Model B.

### Recommendation
Given no test suite exists to catch regressions across ~150 query sites, **Model B is the pragmatic starting point**: it reuses the already-working `$prefix` isolation, adds a thin shared-identity layer, and confines new code to registration/login/switcher rather than touching every query in the app. Model A is the more "correct" long-term architecture (and is where the dead `SINGLE`-mode scaffolding was clearly headed) but should only be attempted after the P1 security items above are resolved and ideally after an automated test suite exists to de-risk the ~150-site SQL rework.

---

*Follow-up review — BCOEM `release-3.0.3` · 2026-07-08*
