# BCOE&M Modernization — Slim 4 Strangler-Fig Design

**Date:** 2026-07-18
**Status:** Approved section-by-section in design review; pending final spec review
**Companion doc:** `.claude/Modernization Approach Recommendation.md` (approach selection rationale, alternatives considered)

## Goal (agreed success criteria)

1. All privileged actions are **centrally authorized**
2. All domain workflows live in **services**
3. All writes are **validated and auditable**
4. Legacy pages are only a **temporary compatibility layer**

## Decisions & constraints

| Decision | Choice |
| --- | --- |
| Framework | **Slim 4** (PSR-7/PSR-15) + **PHP-DI** + **symfony/validator**. Not full Symfony/Laravel: lighter bridge, smaller learning curve, and the domain layer stays portable to full Symfony later if wanted. |
| Migration pattern | Strangler fig: one front controller, legacy pages wrapped as last-resort route handlers, workflows extracted hotspot-first. |
| Upstream posture | Hybrid: keep syncing `geoffhumphrey/brewcompetitiononlineentry` during prep (Phases 0–1, additive work only); diverge when the Slim shell lands (Phase 2). |
| Deployment | Docker primary; shared-hosting (FTP, no shell) remains supported via a built artifact and no-op observability bindings. |
| First milestone | Playwright e2e safety net on the Docker stack. |
| Observability | OpenTelemetry designed-in via DI choke points; Docker-only collector/extension, no-ops elsewhere. |

## Evidence base

- **Hotspots** (`Docs/hotspot-analysis.html`, churn × complexity): `lib/common.lib.php` (0.99; 5,477 LOC, cx 1594, 229 commits), `admin/default.admin.php` (0.89), `admin/site_preferences.admin.php` (0.68), `sections/brew.sec.php` (0.58), `admin/entries.admin.php` (0.57), `sections/register.sec.php` (0.50), `admin/judging_tables.admin.php` (0.49), `output/export.output.php` (0.48). Migration order follows this list.
- **Existing assets:** `origin/characterization-tests` — ~336 passing tests (235 unit / 54 DB-integration / 47 approval-snapshot), PHPUnit config, InnoDB migration (MyISAM ignores transactions — already root-caused), 9 production bugs found/fixed. Current branch `docker-baseline-db` — Docker stack, baseline DB seeding, Composer + PHPStan.
- **Open security debt** (prior reviews, re-verified 2026-07): P1 login SQLi (511 of 519 `mysqli_real_escape_string` calls are no-ops; zero prepared statements anywhere), MD5 pre-hashing before phpass, `handle.php` path traversal, no session regeneration on login; P2 items including `or die(mysqli_error())` leakage and `sterilize()` misuse.
- **Request flow today (verified):** GETs route through `index.php?section=…` with inline `$_SESSION['userLevel']` checks; POSTs go to `includes/process.inc.php` (separate full bootstrap, no entry-point auth), dispatching to `includes/process/*.inc.php`; ~10 self-bootstrapping side doors (`handle.php`, `qr.php`, `ajax/*.php`, `setup.php`, `update.php`, `awards.php`, `ppv.php`, …). Authorization is per-page opt-in.

---

## Section 1 — Architecture: the strangler shell

A single Slim 4 front controller becomes the only PHP entry point. Apache rewrite rules (an established pattern for this app's hosts — `.htaccess` already does HTTPS/SEF rewriting) send every request to it. Legacy pages keep working unmodified inside it via legacy-bridge handlers.

```
Request → .htaccess (rewrite all → front controller)
        → Slim App
           ├─ Middleware pipeline (every request, no exceptions):
           │    0. TracingMiddleware       (OTel root span — outermost)
           │    1. ErrorMiddleware         (uniform error pages; no mysqli_error leaks)
           │    2. SessionMiddleware       (session start; regeneration on privilege change)
           │    3. AuthenticationMiddleware (reads legacy $_SESSION into Identity object)
           │    4. AuthorizationMiddleware  (THE central gate — policy map, deny-by-default)
           │    5. AuditMiddleware         (request-level audit context: who, what, when)
           ├─ Modern routes (grow over time) → controllers → services
           └─ Catch-all legacy routes:
                GET  ?section=X    → LegacyPageHandler    (output-buffered index.php flow)
                POST process forms → LegacyProcessHandler (output-buffered process.inc.php)
                handle.php, qr.php, ajax/* → thin wrappers, same pipeline
```

**Central authorization.** Authorization stops living inside page-scripts. One policy map (`config/access_policy.php`) declares required roles for every route *including legacy ones*, keyed by route or by legacy `section`/`go`/`action` params:

```php
return [
  'section:admin'                                 => Role::Admin,
  'section:list|pay|brew|brewer|user|evaluation'  => Role::Entrant,
  'process:users_register|forgot_password|contact' => Role::Anonymous, // public writes exist
  'process:*'                                     => Role::Entrant,  // refined per action
  'ajax:save'                                     => Role::Admin,
  'route:POST /entries'                           => Role::Entrant,
  // anything not listed: DENY — inversion of today's opt-in checks
];
```

Deny-by-default is the critical property: today a page is protected only if someone remembered a check; after this, a page is reachable only if its policy is declared. Legacy inline checks remain during transition (harmless redundancy) and are deleted with their pages.

**Untouched initially:** `sections/`, `admin/`, `includes/process/`, `lib/` run exactly as today inside the bridge. `paths.php` bootstrap runs once in the front controller instead of once per side door.

**Timing:** this section lands after the upstream divergence point — it rewrites entry-point wiring upstream also touches. Phases 0–1 come first and stay merge-friendly.

## Section 2 — Components & directory layout

New code in `src/` (PSR-4 `Bcoem\` via Composer autoload); legacy trees stay put until retired.

```
/                       (repo root = docroot, as today — see docroot note)
├── index.php           → ~10 lines: autoload, container, run Slim app
├── src/
│   ├── Kernel/         app.php (middleware order, routes), container.php (PHP-DI), Middleware/
│   ├── Legacy/         LegacyPageHandler, LegacyProcessHandler, LegacyBootstrap (throwaway layer)
│   ├── Security/       Identity (from $_SESSION), Role enum (userLevel map), AccessPolicy
│   ├── Database/       Connection (sole mysqli wrapper; prepared statements only), Tables (prefix resolution)
│   ├── Domain/         one folder per workflow, hotspot-first, e.g.:
│   │   └── Entry/      EntryService, EntryRepository, CreateEntryCommand (validator-annotated DTO)
│   └── Audit/          AuditLogger → audit_log table
├── config/             access_policy.php, routes.php
├── templates/          plain-PHP templates for migrated pages, e(…) escaping helper
├── tests/              characterization suite (adopted) + new unit/integration
└── e2e/                Playwright suite
```

**Contracts:**

- `Connection` is the only class that touches mysqli: `select(string $sql, array $params)`, `execute(...)` — no string-interpolation API exists. Legacy keeps its `$connection` global during transition. PHPStan rule: no `mysqli_*` outside `Connection`.
- Domain services are constructor-injected and framework-free (no Slim types in `src/Domain/`). Controllers translate Request → Command DTO → service → Response. This keeps the swap-to-full-Symfony door open and makes unit testing trivial.
- **Write-path contract** (criteria 3): every mutating service method takes a Command DTO, runs `Validator::validate($command)` (symfony/validator attributes), throws `ValidationFailed` with field errors, and on success calls `AuditLogger::record($identity, $action, $before, $after)` in the same transaction (InnoDB). No service writes without both. New additive table: `audit_log(id, user_id, action, entity, entity_id, before_json, after_json, ip, created_at)`.
- `Legacy/` is explicitly throwaway; nothing in `src/` outside `Legacy/` may reference legacy globals or `common.lib.php` (second PHPStan rule).

**Docroot note:** deliberately not moving to `/public` initially — legacy assumes repo-root docroot for assets, and moving breaks shared-hosting FTP deploys. `.htaccess` denies direct access to `src/`, `config/`, `vendor/`, `tests/`. The `/public` move is a late-phase option once asset paths are centralized.

## Section 3 — Data flow: entry submission, before/after

**Today:** form POSTs directly to `includes/process.inc.php?action=brewing` → full re-bootstrap, no entry-point auth → `process_brewing.inc.php` (1,197 LOC) inline-mixes scrubbing (`sterilize()`), business rules, `sprintf`-built SQL with no-op escaping, session mutation, email, `header("Location: …?msg=N")`. No audit; validation failures lose user input.

**After migration of this workflow:**

1. Form action → `POST /entries` (one-line change in the rendering page — legacy pages may post to modern routes).
2. Pipeline: session → `Identity` → **AuthorizationMiddleware checks `POST /entries` ↔ `Role::Entrant` before any handler runs**.
3. `EntryController` maps body onto `CreateEntryCommand`; no `$_POST` beyond this point.
4. `Validator::validate($command)`; failures re-render the form with field-level errors and input preserved (retires `msg=N` per migrated page).
5. `EntryService::create($command, $identity)`: business rules (window open, entry limit, paid state) → `EntryRepository::insert(...)` (prepared statement, prefix-aware) + `AuditLogger::record(...)`, same transaction. Email moves behind an `EntryCreated` hook (initially delegates to existing mail code).
6. Controller returns redirect Response.

**During transition (unmigrated workflow):** same request matches the legacy catch-all → pipeline runs (central authz + request audit context apply identically) → `LegacyProcessHandler` output-buffers `process.inc.php` as today. Unmigrated writes gain central authorization and request-level audit on day one of the shell; field validation and row-level audit arrive when the workflow migrates.

**Reads:** controller → repository/query service → DTO/array → plain-PHP template with `e()` escaping. No Twig for now; revisit after several pages have migrated.

## Section 4 — Error handling & observability

**Retired problems:** `or die(mysqli_error())` (13 open instances), per-entry-point `display_errors` hardcoding, numeric `?msg=N` feedback, DEBUG-constant white screens, zero server-side logging.

**Exception model:** `ValidationFailed` (field map → 422 re-render) · `AccessDenied` (403, audit-logged with identity + route) · `NotFound` (404) · `DomainException` subclasses (`EntryWindowClosed`, `EntryLimitReached`, … — expected outcomes, friendly messages, INFO log, never stack-traced) · everything else including `mysqli_sql_exception` → generic 500, detail to logs only.

One kernel line — `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)` — converts every mysqli failure (legacy or modern) into pipeline-caught exceptions, retiring `or die()` globally without touching call sites.

**ErrorMiddleware:** driven by `APP_DEBUG` env var (replaces editing `paths.php`). Production: branded error page with a short error-reference ID; full trace + request context logged under that ID. Debug: full trace in-browser. Content negotiation: `ajax/*` and JSON routes get `{error, reference_id}` JSON (fixes HTML-error-into-AJAX breakage).

**Logging:** Monolog behind `LoggerInterface`, constructor-injected everywhere (no global logger access in `src/` — enforced convention). Channels: `app`, `security` (logins, denials, policy violations), `legacy`. Kernel `set_error_handler` routes legacy warnings/notices to the `legacy` channel — a live inventory of latent defects. Docker: JSON to stdout. Shared hosting: rotating file in a web-denied directory.

**OpenTelemetry:** `TracingMiddleware` outermost (root span: route/section, identity, status, exceptions); `Connection` emits DB child spans; Monolog OTel handler correlates logs to traces. Docker: compose gains `otel-collector` + Jaeger/Grafana; Dockerfile adds the auto-instrumentation extension (also covers legacy direct `mysqli_query` calls and Slim route spans). Shared hosting: no-op bindings via the same env config; zero overhead. Estimated incremental effort: ~3 days.

**Audit vs. observability:** logging/tracing is operational and best-effort (sampling allowed); `audit_log` is domain truth and transactional. Separate mechanisms on purpose.

## Section 5 — Testing

1. **Characterization suite (adopt, don't rebuild):** merge `origin/characterization-tests` (~336 tests, 3 tiers, InnoDB migration) early in Phase 0. Role: pinning legacy behavior during refactors. Per-workflow disposition at migration time: convert to real service unit tests, or retire. Scaffolding, not a permanent suite.
2. **Playwright e2e (Phase 0 headline, `e2e/`):** runs against the Docker stack with a seeded deterministic baseline DB (known admin, known entrant, open entry window, styles loaded). Journeys in build order:
   - register → activate → login → create entry → edit → pay (PayPal stubbed) → entry visible in account (territory of open bugs #1698/#1700/#1701)
   - admin: login → competition setup → judging tables → assign entries/judges → enter scores → publish results
   - security invariants: anonymous → any `section:admin` URL ⇒ redirect/403; entrant → admin ⇒ 403; side doors (`ajax/save.ajax.php`, `handle.php?id=../…`) denied. These are the acceptance tests for the P1 fixes and the authorization gate — written first, red, then green forever.

   Selectors via `data-test` attributes added to legacy markup (additive, merge-safe). The suite must pass identically before and after the Slim shell lands — the definition of "the shell broke nothing."
3. **New-code tests (per Section 2 contracts):** service unit tests (in-memory fakes, no DB) · repository integration tests (real MariaDB, prepared statements, prefixes) · validator constraint tests per DTO. **Definition of done for a migrated workflow:** service unit tests + repository integration tests + its e2e journey green + PHPStan clean.
4. **Static gates:** PHPStan with the two custom rules (Section 2); ratchet level over time.

**CI order:** PHPStan → Tier 1 unit (fast-fail) → Tier 2/3 vs MariaDB service container → Playwright vs composed stack (traces/screenshots on failure). Target < ~10 min; split e2e into per-PR smoke + per-merge full set if it grows.

**Deliberately excluded:** mocking mysqli inside legacy code (characterization + e2e cover the seams); retroactive unit tests for unmigrated pages (migration is when a page earns real tests).

## Section 6 — Deployment pipeline

**Principle:** the deployable is a built artifact produced and tested by CI — never a hand-FTP'd working tree. Happy path: merge to release branch → tested → shipped, no manual steps.

1. **Build:** `composer install --no-dev --optimize-autoloader` → deploy tree (minus `tests/`, `e2e/`, dev config). `vendor/` stops being committed once CI exists (removes vendor-merge noise; shrinks upstream-sync diffs).
2. **Test:** full Section 5 ladder gates every release, run against the built image — test what ships.
3. **Package:** two artifacts per build:
   - Docker image (primary): app + vendor + OTel extension, tagged SHA + version → GHCR.
   - Zip/tarball (shared-hosting fallback) attached to the GitHub Release; kept honest by a CI smoke job under plain Apache without the extension (proves no-op observability path).
4. **Deploy:** Docker host — CI SSH/webhook → `docker compose pull && up -d`; rollback = redeploy previous tag. Shared hosting — `FTP-Deploy-Action` diff-sync on release publish; zero shell.

**Migrations:** Phinx replaces `update/run_update.php` for the fork's own schema changes (first: `audit_log`, indexes). Docker: entrypoint runs `phinx migrate` before Apache starts. Shared hosting: auth-gated browser-triggered runner (same pattern as today's `update.php`). Rule: **forward-only and additive during the strangler period** — legacy and modern code share the schema; nothing drops/renames what legacy still reads. Destructive cleanup waits until dependent workflows are fully migrated.

**Configuration:** deploy-varying settings (DB creds, `APP_DEBUG`, OTel endpoint, base URL) → environment variables, with a `site/config.php` shim reading `getenv()`. One image for dev/staging/prod; shared hosting keeps editing the shim.

**Environments/cadence:** PRs → CI only. Merge to develop-equivalent → auto-deploy staging compose stack. Tagged release → production + GitHub Release with fallback artifact. Upstream syncs flow through the same PR path during prep — upstream changes get the full test gate too.

---

## Phase plan

| Phase | Content | Upstream posture |
| --- | --- | --- |
| **0 — Safety net** | Playwright e2e suite + seeded baseline DB; adopt characterization suite; CI running all tiers; `data-test` attributes | Syncing (additive only) |
| **1 — P1 security fixes under the net** | Login SQLi (first — unauthenticated), MD5 pre-hash, `handle.php` traversal, session regeneration; security-invariant e2e tests as acceptance | Syncing (small diffs; upstream-contributable) |
| **2 — Slim shell + central authorization** | Front controller, middleware pipeline, policy map (deny-by-default), legacy bridges, error handling, Monolog, OTel, env config, Phinx, deployment pipeline | **Divergence point** |
| **3 — Hotspot-ordered extraction** | Workflow-by-workflow into `src/Domain/` per hotspot ranking: entries/brewing first, then admin judging flows, registration; `common.lib.php` dismantled opportunistically as its callers migrate | Diverged; cherry-pick upstream fixes if wanted |

Success criteria mapping: criterion 1 lands wholesale in Phase 2 (policy map covers legacy routes too); criteria 2–3 land incrementally per workflow in Phase 3; criterion 4 is the definition of the `src/Legacy/` layer, which shrinks to zero.

## Risks & mitigations

- **Legacy bridge subtleties** (headers already sent, `exit()` in page-scripts, output buffering interactions): contained in `Legacy/` handlers; e2e suite must pass identically pre/post shell — that equivalence is the Phase 2 gate.
- **Policy-map completeness** (missing a legacy route = lockout, or worse, a forgotten side door): deny-by-default makes omissions fail closed (a 403, visible immediately in e2e), never open.
- **Upstream drift after divergence:** accepted by decision; upstream fixes remain cherry-pickable while their target files are unmigrated, and the e2e suite gates each cherry-pick.
- **Solo-maintainer bus factor for owned glue:** minimized by choosing Slim (maintained, documented) over hand-rolled kernel; the only truly custom pieces are the legacy bridges and policy map.
- **Session coupling** (legacy stores rows/passwords in `$_SESSION`; `Identity` reads the same session): Phase 2 reads but does not restructure the session; session cleanup is a Phase 3 workflow like any other.

## Out of scope (this design)

- Multi-tenancy (see `project-multitenancy` memory / follow-up review) — the service layer is a prerequisite and makes it easier later; nothing here blocks Model B.
- UI/frontend modernization (Bootstrap/templates stay as-is; template extraction is per-page during Phase 3).
- Full Symfony adoption — an explicitly kept-open option, not a plan.
