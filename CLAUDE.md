# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

BCOE&M (Brew Competition Online Entry & Management) — a PHP homebrew-competition management system. This is a **~15-year-old legacy PHP codebase (procedural, `mysqli`, file-per-page routing) undergoing an incremental modernization** to a PSR-7/Slim app with a DDD-style `src/` layer, running side-by-side with the legacy code behind one front controller. Both stacks are live in production simultaneously — most requests still hit legacy code paths.

## Commands

### Local stack (Docker)
```bash
docker-compose up -d              # app (localhost:8080) + DB + Tempo/Prometheus/Grafana (localhost:3000)
docker-compose up -d db           # just the DB, for host-run PHPUnit Integration tests
docker-compose down -v            # wipe DB volume
composer db:reset                 # same as down -v && up -d (fresh seeded fixtures)
```
phpMyAdmin is not part of this compose file (see `~/CLAUDE.md` workspace notes for the separate `BCOE-M-Docker` project, which does provide it).

### Static analysis & tests
```bash
composer stan                     # PHPStan, level 0, over lib/ + src/ only (see phpstan.neon)
composer test:unit                # Unit tier, no Docker/DB needed
composer test                     # full 3-tier PHPUnit suite inside the web container (stack must be up)
composer test:db                  # Integration + Approval tiers inside the web container
composer e2e                      # Playwright e2e against the running stack (passes args through, e.g. `composer e2e -- smoke.spec.ts`)
composer e2e:install               # one-time: e2e npm deps + Chromium
composer ci                       # stan + test + e2e, i.e. what CI runs
```
Single test / single suite directly:
```bash
php vendor/bin/phpunit --testsuite Unit
php vendor/bin/phpunit tests/Unit/Domain/Entry --testsuite Unit
php vendor/bin/phpunit tests/Unit/Domain/Entry/ValueObject/EntryIdTest.php
docker-compose exec -T web vendor/bin/phpunit --testsuite Integration
docker-compose exec -T web vendor/bin/phpunit --filter testSomeMethod tests/Integration/Entry/EntryRepositoryIntegrationTest.php
cd e2e && npx playwright test dual-path-verification.spec.ts
```
Integration/Approval tests need a live DB — either run them inside the `web` container (reads `docker-compose.yml`'s `DB_*` env vars via `$GLOBALS['connection']`), or export `BCOEM_DB_HOST`/`BCOEM_DB_USER`/`BCOEM_DB_PASSWORD`/`BCOEM_DB_NAME` and run on the host. Integration tests use per-test transactional rollback, but that isolation does **not** cover rows a concurrent Playwright run commits to the same DB — reseed (`composer db:reset`) after running e2e locally before trusting Integration numbers again. Full tier/health detail (known-broken E2E TLS issue, per-phase coverage gaps, flakiness) lives in `TESTING.md` / `TESTING_HEALTH_DASHBOARD.md` / `TESTING_RUNBOOKS.md` — read those rather than re-deriving this from scratch.

## Architecture

### One front controller, two eras of code

`index.php` is the sole entry point (`.htaccess` rewrites everything else here). It defines the `ROOT` constant only, then delegates to `src/Kernel/app.php`'s `buildApp()`, which assembles a Slim 4 app via `php-di/slim-bridge`. Route registration order is load-bearing, not stylistic — nikic/fast-route fails to compile (and takes down *every* route, not just the offender) if a static route is registered after a variable/catch-all route it could collide with. The real order in `app.php` is: legacy `file:*` side-door routes → explicit legacy front-controller routes (`/index.php`, `/`, `/includes/process.inc.php`) → modern Domain routes (Entry, Export, Registration, Judging) → the SEF catch-all `/{section}[/{go}[/{action}[/{id}]]]}` last. Adding a new static route means adding it *before* the catch-all.

Legacy pages are bridged through `src/Legacy/{LegacyPageHandler,LegacyProcessHandler,LegacyFileHandler,LegacyBootstrap}.php`, each of which `require`s the target legacy file **from within a method**, not top-level — this is deliberate: legacy's own `paths.php` sets `$prefix`/`$database`/`$connection` as plain (non-global) variables in whatever scope first `require_once`s it, and every legacy file after it expects those vars to already be in *its own* scope. `require_once`-ing `paths.php` eagerly at true top level (e.g. from `index.php`) breaks this silently — the second `require_once` from within a handler method becomes a no-op, and the target file runs with those variables undefined. If you touch the legacy bridge, preserve the "require happens inside the handler method" shape.

Modern (Domain-layer) code lives under `src/Domain/{Entry,Judging,Export,AdminPreferences,Registration}/`, each following the same internal shape: `ValueObject/`, `Repository/`, `Service/`, `Command/`, `Exception/`, plus an aggregate root. `src/Kernel/Controller/*Controller.php` are the HTTP-facing adapters wired into `app.php`'s routes; note several are registered as lazy closures/class-strings specifically so DB-free routes (like `/__kernel_hello`) don't force a live `Connection` to be built at `buildApp()` time.

### Authorization is centralized and deny-by-default

`config/access_policy.php` is the single source of truth for every reachable `section`/`go`/`action` combination, `process.inc.php` dispatch value, legacy side-door (`file:*`), `output.inc.php` sub-dispatch (`output:section:*`), and modern route name. `src/Kernel/Middleware/AuthorizationMiddleware.php` denies anything not listed. Each entry is heavily commented with how it was verified (curl empirical checks, source citations) — when adding a new reachable path (legacy or modern), add its policy entry here or it 403s; don't try to gate it purely inside the handler.

Middleware order in `app.php` matters and is documented inline there: Session → Authentication → Slim routing → SEF-path-to-query-param translation → Authorization → route handler, with Tracing as the true outermost wrapper (added last, after even `addErrorMiddleware()`, so spans see the final status code including error-handled responses).

### PHPStan custom rules are placeholders, not yet enforcing

`src/PHPStan/NoLegacyReferenceOutsideLegacyRule.php` and `NoMysqliOutsideConnectionRule.php` are registered in `phpstan.neon` but both currently `return []` unconditionally — they exist to declare *intent* (Domain code shouldn't reach into legacy globals; raw `mysqli_*` calls should be confined to `src/Database/Connection.php`) ahead of a later phase that will actually implement the check. `NoLegacyReferenceOutsideLegacyRule`'s docblock already names the sanctioned exceptions (`AuthenticationMiddleware` reading `$_SESSION`, `SessionMiddleware` reading `$GLOBALS['installation_id']`) to carve out once it's live. PHPStan itself runs at **level 0** (not a typo/aspiration — `TESTING.md` explicitly corrects an earlier doc that claimed level 8).

### Configuration

`site/config.php` is the single source of truth for deploy-varying config (DB credentials, installation ID, Stripe keys): `getenv('X') ?: '<shared-hosting default>'`. Docker supplies these via `docker-compose.yml`'s `environment:` block; shared hosting with no env vars set falls through to the hardcoded defaults in that file. `site/config.php` is git-ignored. Never hardcode secrets into a committed PHP file — add new deploy-specific config following the existing `getenv()` pattern and document the var in `docker-compose.yml`.

### Frontend: legacy/modern parity

Modern templates (`templates/*/partials/*.php`) are being built to match legacy rendering (`sections/*.sec.php`) field-for-field, including easy-to-miss details like which partials get `require`d. Parity is checked against static snapshots in `Docs/Forms/legacy-registration.html` / `modern-registration.html` and enforced by Playwright specs named `*-dual-path*.spec.ts` in `e2e/tests/`, which run the same scenario against legacy and modern routes and assert equivalent behavior/DB state. New markup should be Bootstrap 5 only (`is-invalid`/`invalid-feedback`, `form-check`/`form-check-input`/`form-check-label`) — no BS3 leftovers (`has-error`, bare `.checkbox`, `col-sm-offset-*`); add an explicit `d-block` on `.invalid-feedback` when it isn't a direct sibling of the `.is-invalid` control. Every CDN `<link>`/`<script>` in `templates/layout/head.php`/`footer.php` is version-pinned with a `sha384` `integrity` + `crossorigin="anonymous"` attribute — keep new CDN references pinned and hashed the same way.

### Observability

`src/Kernel/Middleware/TracingMiddleware.php` + `SpanEnrichmentMiddleware.php` emit OpenTelemetry traces (OTLP/HTTP) to the `tempo` service; `prometheus`/`grafana` in `docker-compose.yml` provide local metrics/dashboards at `localhost:3000` (admin/admin). Monolog channels are separated by concern (`logger.app`, `logger.security`, `logger.legacy`) — legacy PHP warnings/notices are captured onto the `legacy` channel via `bcoem_register_error_logging()` in `index.php`, not left as raw output.

### Directory map (non-obvious parts only)

- `lib/` — legacy procedural function libraries (`common.lib.php`, `date_time.lib.php`, etc.), included by both legacy pages and some modern glue code.
- `classes/` — vendored/legacy third-party libraries predating Composer (fpdf, htmlpurifier, phpass, tiny_but_strong, etc.) — not autoloaded via PSR-4.
- `sections/*.sec.php` — legacy page-body includes, dispatched by `section`/`go` query params.
- `admin/*.admin.php`, `output/*.output.php`, `ajax/*.ajax.php` — other legacy dispatch namespaces, each with its own `config/access_policy.php` key prefix (`file:admin/...`, `output:section:...`, `file:ajax/...`).
- `templates/` — modern PHP view templates for the `src/Kernel/Controller` layer, one subdirectory per Domain plus `layout/` for shared chrome.
- `.superpowers/` — brainstorm/plan/task artifacts from prior modernization phases; `.superpowers/sdd/task-3a-report.md` etc. hold the citation trails referenced in `config/access_policy.php`'s comments.
