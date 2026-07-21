# BCOE&M Modernization — Approach Recommendation

**Date:** 2026-07-18
**Status:** Draft for discussion — approach not yet selected
**Goal (agreed):** All privileged actions centrally authorized · all domain workflows in services · all writes validated and auditable · legacy pages only a temporary compatibility layer

---

## Constraints established

| Question | Answer |
|---|---|
| Upstream posture | Hybrid: keep syncing from `geoffhumphrey/brewcompetitiononlineentry` during a prep phase, then diverge. Prep-phase work must therefore be additive / low-diff-footprint. |
| Framework | Undecided — recommendation requested (Symfony was suggested by another model, not committed to). |
| Deployment | Docker primary, shared-hosting still possible. No hard dependency on reverse proxy or server-side build step; `vendor/` is already committed, which preserves the FTP-deploy escape hatch. |
| First milestone | E2E safety net (Playwright) before security fixes or architecture work. |

## Current state (evidence gathered)

- **Hotspot analysis** (`Docs/hotspot-analysis.html`, churn × cyclomatic complexity):
  1. `lib/common.lib.php` — score 0.99, 5,477 LOC, complexity 1594, 229 commits
  2. `admin/default.admin.php` — 0.89, 3,106 LOC
  3. `admin/site_preferences.admin.php` — 0.68
  4. `sections/brew.sec.php` — 0.58
  5. `admin/entries.admin.php` — 0.57
  6. `sections/register.sec.php` — 0.50
  7. `admin/judging_tables.admin.php` — 0.49
  8. `output/export.output.php` — 0.48

  The monolithic function library and the admin/entry pages are where risk concentrates; `update/run_update.php` (complexity 1024) and `pub/*.pub.php` are complex but low-churn.
- **Existing modernization assets already on branches:**
  - `origin/characterization-tests`: ~336 passing tests in 3 tiers (235 unit / 54 DB-integration / 47 approval-snapshot), PHPUnit + `phpunit.xml`, InnoDB table migration (MyISAM ignores transactions — already root-caused), 9 production bugs found and fixed, findings docs.
  - Current branch `docker-baseline-db`: Docker stack (Apache + MariaDB 11), baseline DB seeding, PHPStan + Composer, write-load-test harness.
- **Open security debt** (per prior reviews, re-verified 2026-07-08/14):
  - P1-SEC-002 — unauthenticated login SQLi; 519 `mysqli_real_escape_string` call sites, 511 discard the return value (no-ops); zero parameterized queries in codebase. Highest priority.
  - P1-SEC-001 — MD5 pre-hashing before phpass (7+ files).
  - P1-SEC-005 — path traversal in `handle.php` PDF download.
  - P1-SEC-006 — no `session_regenerate_id()` on login.
  - P2: SVG stored XSS, `sterilize()` misuse, encryption key in session, Referer-based gating, residual `mysqli_error()` disclosure.
- **Upstream** is actively maintained (3.0.3 era); issue tracker is dominated by functional bugs in entry/judging/payment flows plus enhancements (e.g. #1620 shared logins across instances, #1617 Docker support).

---

## The three realistic approaches

### A. Strangler fig with Symfony *components* à la carte — **RECOMMENDED**

Keep the app as the app. Introduce a thin kernel you own — front controller, router, DI container, request/response — built from individual Symfony components (`http-foundation`, `routing`, `dependency-injection`, `validator`), not the full framework. Legacy page-scripts get wrapped as "legacy controllers" (output-buffered includes) dispatched through that kernel, which is where the **central authorization gate** lives — every request, legacy or modern, passes one choke point. Domain workflows then get extracted out of `includes/process/*.inc.php` into service classes one workflow at a time, hotspot-first, each landing with parameterized queries, validation, and audit logging.

**Why it fits the constraints:**

- **Hybrid upstream posture:** Phases 0–1 (E2E net, security fixes) are purely additive files — upstream merges stay trivial. The kernel phase touches only `index.php` / `paths.php` bootstrapping, which is the planned divergence point anyway.
- **Docker-primary, shared-hosting possible:** still plain PHP + Apache rewrite rules. No reverse proxy, no build step. Committed `vendor/` covers FTP deploys.
- **Meets the full success criteria:** central authz ✓ · workflows in services ✓ · validated auditable writes ✓ · legacy pages as temporary compatibility layer ✓ — without betting the project on learning full Symfony first.
- **A door that stays open:** Symfony components *are* Symfony. Services, validators, and routes port into the full framework later if wanted; unwinding a too-heavy full-stack adoption is far worse than upgrading a component-based one.

**Trade-off:** you own the glue (kernel, auth gate, legacy bridge — roughly 300–500 lines). No framework docs for "how do we do X here."

### B. Full Symfony skeleton wrapping the legacy app

New Symfony app; a catch-all legacy-route controller boots the old app for unmigrated pages. Gains Security voters, Doctrine, Forms, Twig, Messenger — the whole convention set — plus ecosystem/documentation support.

**Trade-offs:** steepest learning curve while still researching Symfony; Doctrine collides hard with BCOEM's table-prefix scheme and three coexisting mysqli patterns; large diff footprint from day one, which fights the "keep syncing upstream for now" posture; the strangler bridge (sessions, globals, `sterilize()`, headers) is significantly more work because full Symfony wants to own the request lifecycle.

### C. In-place modernization, no framework layer

Central auth include, namespaced service classes via Composer autoload, a parameterized-query helper; keep the page-script architecture.

**Trade-offs:** lightest touch and permanently merge-friendly, but it cannot reach the agreed success criteria — pages remain the architecture rather than a temporary compatibility layer, and authorization stays per-page opt-in (the exact failure mode the app has today). Only appropriate if staying merged with upstream forever.

---

## Recommended sequencing (Approach A, shaped by the answers above)

1. **Phase 0 — E2E safety net.** Playwright against the Docker stack with a seeded baseline DB (builds directly on the `docker-baseline-db` branch). Journeys: register → login → create entry → pay; admin: competition setup → judging tables → scores → results. Adopt the ~336 characterization tests from `origin/characterization-tests` into CI alongside.
2. **Phase 1 — close the P1s under the net.** Login SQLi first (unauthenticated), then MD5 pre-hash, path traversal, session fixation. Additive/small-diff → still upstream-syncable, and candidates for upstream contribution.
3. **Phase 2 — kernel + central authorization gate.** The divergence point, taken on your schedule.
4. **Phase 3 — hotspot-ordered service extraction.** `lib/common.lib.php` and the entry/judging workflows first, per the hotspot map.

---

## Alternative frameworks considered (added 2026-07-18)

The success criteria hinge on one architectural feature: a **middleware pipeline** giving a central choke point for authorization. Any PSR-15 framework provides that natively; the rest (services, validation, audit) is discipline plus a DI container.

| Framework | Fit | Verdict |
|---|---|---|
| **Slim 4** (+ PHP-DI, symfony/validator) | PSR-7/PSR-15 micro-framework — essentially the Approach A kernel, but maintained and documented. Auth gate = standard middleware; legacy pages = catch-all route with output-buffered include. Plain PHP + Apache rewrites, so Docker/shared-hosting constraint holds. | **Strongest alternative — arguably beats hand-rolled Approach A glue.** Closes A's "no docs for how we do X" weakness. |
| **Laravel** | Biggest community; Gates/Policies map naturally to central authorization; Dusk for browser tests. But it wants the whole lifecycle: Eloquent collides with table-prefix mysqli like Doctrine would, legacy bridge is real work, large diff footprint. | Viable, but = Approach B with a friendlier curve; forces the upstream-divergence point earlier. |
| **Mezzio (Laminas)** | Same PSR-15 architecture as Slim; more config-driven, smaller community. | Only over Slim with prior Laminas experience. |
| **CodeIgniter 4** | Shared-hosting friendly, gentle curve, but weak DI and authorization stories — the central-authz discipline would be rebuilt by hand. | Not recommended — defeats the purpose. |
| **CakePHP / Yii** | Declining mindshare; Yii 3 in long-term limbo. | Not recommended for a decade-scale bet. |

**Updated framing — three viable doors:**

1. **Slim 4 + symfony/validator** — "Approach A with a maintained skeleton." Lightest, fits every constraint, smallest learning curve. *Updated recommendation.*
2. **Symfony components à la carte** — original Approach A; slightly more control, slightly more owned glue.
3. **Laravel or full Symfony** — full batteries; accept earlier divergence and a heavier legacy bridge.

Note: the e2e safety net (Playwright) and Phase 0–1 sequencing are framework-agnostic — nothing in this decision blocks starting Phase 0.

---

## Next step

Pick A / B / C (or request a deeper dive into B). The design then gets presented section by section — architecture, components, data flow, error handling, testing — and written up as a spec before any implementation planning.
