# SQLi Remediation Follow-up: v3.1.0 MysqliDb Conversion & `slim` Rebase Assessment

**Date:** 2026-08-19
**Scope:** (1) How upstream's (geoffhumphrey) v3.1.0 "Conversion to MysqliDb Protocols" commit compares to our prior SQLi findings. (2) Concrete effort estimate to bring `slim` current with it.

Prior context: `Docs/SQLi Remediation - mysqli_real_escape_string Audit.md`, `Docs/Technical Review Follow-up - release-3.0.3.md`, and memory `project-security-findings` (P1-SEC-002's broader scope — ~510 non-login `mysqli_real_escape_string()` no-op call sites — was deliberately deferred to our own Phase 3).

## Branch topology (as of this analysis)

- Local `master` (`8de3895a`) = the point where `slim`'s Phase 0–3.1 work was merged into master on 2026-07-21 (`15786138 Merge branch 'slim'`), plus one commit since.
- `slim` diverged from that exact commit and kept going: **161 commits**, 363 files changed (+42,976/-914) — Phase 3.2 onward, Registration, landing page, dead-code deletion.
- `origin/master` (the fork, `bforrest/brewcompetitiononlineentry`) diverged from that same commit via an automated upstream-sync workflow: **40 commits**, 336 files changed — mostly `Merge remote-tracking branch 'upstream/master'` pulling in geoffhumphrey's real upstream work.
- `upstream/master` has nothing `origin/master` doesn't already have — the fork is fully synced to upstream.
- **Local `master` is 23 commits behind `origin/master`** — needs a plain `git pull` before anything else.

So "the project creator's massive SQL injection fix" is upstream commit **`a8092f18` — "v3.1.0 Conversion to MysqliDb Protocols; Data-integrity and Stability Improvements"** (Aug 13, geoffhumphrey), already synced into `origin/master`, but present in neither local `master` nor `slim`.

## Part 1 — How the fix compares to our findings

**It's real and substantial**, not cosmetic:

- **282 files changed, +6,824/-7,904 lines.** Converts the bulk of the app's hand-built SQL to `joshcam/PHP-MySQLi-Database-Class` ("MysqliDb", vendored at `site/MysqliDb.php`, v2.9.3) — a genuine query builder whose `->where()/->get()/->insert()/->update()` methods build parameterized queries and execute them via real `mysqli_prepare()` + `bind_param()` (verified in the vendored source), not another escaping layer.
- Raw `mysqli_query()` calls with hand-assembled SQL strings: **1,342 → 58** across the codebase (95.7% reduction). Spot-checking the survivors: several are dead code left commented-out for reference (e.g. `includes/db/common.db.php` — the old `mysqli_query` blocks are literally `/* ... */`'d out next to their live `$db_conn->where(...)->getOne(...)` replacement), and the one live example checked (`ajax/practice_session.ajax.php`) either takes no user input or feeds MysqliDb's parameterized `->insert()`.
- `mysqli_real_escape_string()`: **0 call sites, before and after** — confirms that vector was already fully retired earlier (matches our own login-path fix + Phase 3 domain extractions).
- This closes most of what **P1-SEC-002's deferred broader scope** was tracking — upstream did, independently, most of the ~510-call-site migration we'd scoped for our own Phase 3.

**Still open, unaffected by this commit:**
- **P2-SEC-009** (`sterilize()` insufficient for SQLi) — unchanged. 53 call sites before and after; it still only does `FILTER_SANITIZE_FULL_SPECIAL_CHARS` + `strip_tags`/`addslashes`, not parameterization. Lower risk now that most of its output feeds MysqliDb's bound-parameter calls rather than raw concatenated SQL, but the function itself is not a fix and shouldn't be treated as one.
- **P2-SEC-007** (`or die(mysqli_error())` info disclosure) — still present on the handful of remaining raw-query call sites.
- No change to P2-SEC-008 (SVG upload/stored XSS), P2-SEC-010 (encryption key in `$_SESSION`), P2-SEC-011 (Referer-based gate).

**Verdict:** treat this as a legitimate, good-faith SQLi remediation from upstream that substantially overlaps our own deferred Phase 3 scope. It does not touch authentication, session, or path-traversal fixes (those were already ours, done in Phase 1) or the two P2s above.

## Part 2 — Effort to bring `slim` current

**Recommendation: merge, don't literally rebase.** 161 commits on `slim` replayed one-by-one against a legacy tree that upstream rewrote by 6,824/-7,904 lines would mean re-resolving overlapping conflicts commit-by-commit. A single 3-way merge (either direction) collapses that to one resolution pass.

Simulated with `git merge-tree` (no working-tree changes) merging `origin/master` into `slim`:

- 20 files were touched by both sides since the common ancestor; **336 + 363 − 20 ≈ 679 other files apply cleanly** (upstream's other 316 files, `slim`'s other 343 files — mostly all of `src/Domain/`, tests, Slim controllers).
- Of the 20 overlapping files, **only 9 produce actual merge conflicts**:

| File | Hunks | Nature |
|---|---|---|
| `.gitignore` | 1 | Trivial — union both ignore lists |
| `composer.json` | 1 | Trivial to merge, but see the sync-workflow finding below |
| `phpstan.neon` | 1 | Trivial |
| `phpstan-baseline.neon` | 4 | Mechanical — line-number churn from upstream's reformatted files |
| `paths.php` | 1 | Easy — `sterilize()` def; master added array support, slim added a `function_exists()` test-bootstrap guard; combine both |
| `update/1.2.0.0_update.php` | 3 | Low-risk — historical one-time migration script |
| `update/1.2.1.0_update.php` | 1 | Same |
| `update/1.3.0.0_update.php` | 2 | Same |
| **`lib/common.lib.php`** | **7** | **The real work — see below** |

The other 11 overlapping files (`admin/entries.admin.php`, `admin/judging_scores.admin.php`, `eval/scoresheet.eval.php`, `includes/process/process_prefs.inc.php`, `index.pub.php`, `lib/date_time.lib.php`, `lib/update.lib.php`, `pub/eval_scoresheet.pub.php`, `setup/install_db.setup.php`, `update.php`, `update/2.1.0.0_update.php`) auto-merge cleanly.

### `lib/common.lib.php` — the crux

Both sides hit this file hard: master rewrote ~1,437 lines (MysqliDb conversion), `slim` removed ~559 lines (Phase 3 domain extraction). All 7 conflicts are the same shape: master rewrote a function's body to use MysqliDb; `slim` deleted the whole function because it's now `src/Domain/...`.

Traced all 13 functions `slim` deleted that master also modified, checking for live callers elsewhere on `origin/master` (outside `common.lib.php`):

- **11 are genuinely dead** outside `common.lib.php` (`available_at_location`, `check_judging_flights`, `check_judging_numbers`, `convert_to_ba`, `convert_to_pro`, `display_array_content_style`, `get_ba_style_info`, `highlight_required`, `remove_sensitive_data`, `total_entries_brewer`, `user_check`) — safe to keep deleted, consistent with `slim`'s own Phase 3.8 dead-code audit.
- **2 are NOT safe to delete**: `total_paid()` (still called live from `includes/constants.inc.php`, `pub/at-a-glance.pub.php`, `sections/reg_open.sec.php`, `sections/sidebar.sec.php`) and `winner_method()` (still called live from `awards.php`, `output/export.output.php`, `output/print.output.php`, `pub/default.pub.php`, `pub/past_winners.pub.php`, `sections/default.sec.php`, `includes/db/output_entries_export_winner.db.php`, `includes/db/scores.db.php`) — **none of those caller files have been touched by `slim`'s strangler extraction yet.** Blindly taking `slim`'s deletion would 500 those pages. Correct resolution: keep master's MysqliDb-rewritten versions of these two functions in `common.lib.php`, drop the other 11.

### A finding worth flagging separately: the sync automation

While tracing why master's `composer.json` conflicts at all, found the cause: `.github/workflows/sync-upstream.yml` runs `git merge -X theirs upstream/master`. On 2026-08-15, upstream added its own `composer.json` for the first time (`ed185560`, dev-tooling only). Because the fork's `composer.json` already existed (with the Slim4/PHP-DI/Monolog/OpenTelemetry manifest, merged in from `slim` back on 2026-07-21), the add/add conflict resolved silently in upstream's favor — **wiping the fork's dependency manifest with no CI failure and no human review.**

This isn't just today's merge conflict — it's a standing risk. If `slim` merges into `master` and this workflow keeps running unmodified, any future upstream sync that happens to conflict with fork-specific/modernization content will silently discard the fork's side again. Worth deciding, before or alongside the `slim` merge, whether to drop `-X theirs` (let real conflicts fail the workflow for human review) or scope the automation away from modernization-owned paths.

### Bottom line

- Trivial/mechanical conflicts (8 files): well under a day, mostly line-merges.
- `lib/common.lib.php`: the only place needing actual judgment — 7 hunks, and the caller-tracing above already answers 2 of the 3 open questions (keep `total_paid`/`winner_method`, drop the other 11). Call it a well-scoped 1–2 day task for someone who knows both sides, not a rewrite.
- Not included in that estimate: re-running the full 3-tier PHPUnit + PHPStan + e2e suite against the merged tree and fixing whatever it surfaces — per this project's own recurring lesson ("verify, don't trust a COMPLETE claim"), that's the actual completion gate, not clean conflict resolution.
- Decide the `sync-upstream.yml` `-X theirs` question before or alongside the merge, or the fork will keep losing modernization content silently on every future upstream sync.

## How to apply

Before starting: `git pull` local `master` (23 commits behind `origin/master`). Then merge `origin/master` into `slim` (not a literal commit-by-commit rebase) using the resolution guidance above. Re-verify PHPStan/PHPUnit/e2e afterward before declaring done.
