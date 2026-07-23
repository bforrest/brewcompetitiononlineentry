# Modern Registration Public-Page Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the standard entrant `/register` page visually and behaviorally match the legacy public registration flow while keeping rendering, option reads, form mapping, and persistence isolated.

**Architecture:** A read-only `RegistrationOptionsRepository` builds explicit `RegistrationFormOptions`; `RegistrationFormFactory` turns request data and options into renderable `RegistrationFormData`; the controller coordinates those objects and `RegistrationService`. `LayoutRenderer::public()` supplies public Bootstrap chrome and templates render only passed variables.

**Tech Stack:** PHP 8.2, Slim 4, PHP-DI, PHPUnit 10, Playwright, Bootstrap 3, MariaDB.

## Global Constraints

- New code uses `Bcoem\` and prepared `Bcoem\Database\Connection` access only.
- Registration templates must not read `$_SESSION`, globals, or database connections.
- Do not reuse `sections/register.sec.php`; treat it as behavior/markup reference only.
- Scope is standard entrant only: exclude Pro Edition, dedicated judge/steward routes, location-preference variants, and admin/quick registration.
- Preserve the existing success behavior: set `loginUsername`, regenerate CSRF, redirect to `/entries/my`.
- Use base-aware/root-relative paths consistent with the existing modern routes.

---

### Task 1: Public layout rendering path

**Files:** Modify `src/Kernel/View/LayoutRenderer.php`, `templates/layout/nav.php`; create `tests/Unit/Kernel/View/LayoutRendererPublicTest.php`.

**Produces:** `public(string $title, string $templatePath, array $vars = []): string`, rendering Bootstrap assets, anonymous public navigation, page heading, content, and footer without an `Identity`.

- [ ] Write a failing test asserting `public()` contains `/css/common.min.css`, `/css/default.min.css`, public Register/Login links, title, and fixture content.
- [ ] Run `OTEL_PHP_DISABLED_INSTRUMENTATIONS=mysqli ./vendor/bin/phpunit tests/Unit/Kernel/View/LayoutRendererPublicTest.php`; expect undefined method failure.
- [ ] Add `public()` using `wrapPublic()`; make `nav.php` accept explicit `?Identity $identity` and render anonymous links when null.
- [ ] Re-run the test; expect pass.
- [ ] Commit: `feat: add public layout rendering path`.

### Task 2: Registration form read model

**Files:** Create `src/Domain/Registration/Form/RegistrationFormOptions.php`, `RegistrationFormData.php`, `RegistrationFormFactory.php`; create unit tests under `tests/Unit/Domain/Registration/Form/`.

**Produces:** immutable options for title/guidance/select choices/availability and form data with `values`, `fieldErrors`, and `generalErrors`.

- [ ] Write failing tests proving submitted values survive an error render and that missing required names produce keyed errors.
- [ ] Implement readonly constructors and `RegistrationFormFactory::fromRequest(array $input, RegistrationFormOptions $options, array $fieldErrors = [], array $generalErrors = []): RegistrationFormData`.
- [ ] Run the focused tests; expect pass.
- [ ] Commit: `feat: add registration form view models`.

### Task 3: Explicit option reads

**Files:** Create `src/Domain/Registration/Repository/RegistrationOptionsRepository.php`; create `tests/Integration/Registration/RegistrationOptionsRepositoryIntegrationTest.php`.

**Produces:** `options(): RegistrationFormOptions`, populated from `contest_info`, `preferences`, `drop_off`, and applicable standard-entrant availability tables through prepared queries.

- [ ] Write a failing integration test with fixture contest title, registration text, and a drop-off row; assert the returned options expose them.
- [ ] Implement prefix-aware prepared queries through `Connection`; return empty option lists when no eligible rows exist.
- [ ] Run `docker compose exec -T -e BCOEM_DB_HOST=db web vendor/bin/phpunit tests/Integration/Registration/RegistrationOptionsRepositoryIntegrationTest.php`; expect pass.
- [ ] Commit: `feat: add registration form option repository`.

### Task 4: Standard entrant persistence coverage

**Files:** Modify `RegisterEntrantCommand.php`, `RegistrationService.php`, `RegistrationRepository.php`; modify `tests/Unit/Domain/Registration/Command/RegisterEntrantCommandTest.php`, `Service/RegistrationServiceTest.php`, and `tests/Integration/Registration/RegistrationDualPathTest.php`.

**Produces:** explicit support for standard entrant drop-off and rendered volunteer/waiver fields, with only fields that have legacy-equivalent writes accepted.

- [ ] Add failing command/service tests for drop-off and opt-in/waiver values plus a dual-path assertion for their persisted columns.
- [ ] Extend the command with typed fields and defaults; extend the service/repository column arrays using existing legacy column names.
- [ ] Run focused unit and integration tests; expect all pass.
- [ ] Commit: `feat: persist standard entrant registration fields`.

### Task 5: Public registration view composition

**Files:** Replace `templates/Registration/register-form.php`; create `templates/Registration/partials/{errors,account,contact-address,logistics,volunteer,waiver,submit}.php`.

**Produces:** content-only Bootstrap 3 registration partials receiving `$form` and `$options` explicitly.

- [ ] Write a rendering test asserting the complete form includes legacy-equivalent grouped headings, required markers, `/register` POST action, error summary, drop-off, opt-ins, waiver, and no document/head/body tags.
- [ ] Implement the partials with `e()` for values/content and Bootstrap classes used by the legacy form (`form-horizontal`, `form-group`, `control-label`, `form-control`, `help-block`, `alert`).
- [ ] Run the rendering test; expect pass.
- [ ] Commit: `feat: add standard entrant registration form partials`.

### Task 6: Controller and DI integration

**Files:** Modify `src/Kernel/container.php`, `src/Kernel/Controller/RegistrationController.php`, `tests/Unit/Kernel/Controller/RegistrationControllerTest.php`.

**Produces:** GET uses options + `LayoutRenderer::public`; POST re-renders HTML form with 422/409 statuses for invalid/domain errors and preserves the existing success redirect.

- [ ] Write failing controller tests for public-shell GET, invalid form HTML response, duplicate-email HTML response, and existing success redirect.
- [ ] Bind options repository/factory/layout dependencies; replace direct template inclusion and JSON error bodies with one private `renderForm()` method.
- [ ] Run the controller tests; expect pass.
- [ ] Commit: `feat: render registration failures as public HTML forms`.

### Task 7: End-to-end equivalence and visual verification

**Files:** Modify `e2e/tests/registration-dual-path.spec.ts`; add/update screenshot assertions and docs only if test fixtures require them.

- [ ] Add a modern-page assertion for public navigation, Bootstrap form grouping, contest guidance placeholder, and the standard entrant journey.
- [ ] Run `cd e2e && npx playwright test tests/registration-dual-path.spec.ts`; expect all legacy/modern checks pass.
- [ ] Run final gates: `OTEL_PHP_DISABLED_INSTRUMENTATIONS=mysqli ./vendor/bin/phpunit --testsuite Unit`, `docker compose exec -T -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Integration`, and `./vendor/bin/phpstan analyse --memory-limit=1G`.
- [ ] Commit: `test: verify modern registration public-page parity`.

## Post-plan verification

- [ ] Confirm no Registration template contains `$_SESSION`, `$GLOBALS`, `mysqli_`, `<html`, `<head`, or `<body`.
- [ ] Confirm `/register` renders public navigation while `/entries/my` behavior remains unchanged after a successful registration.
