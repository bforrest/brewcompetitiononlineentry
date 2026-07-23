# Shared Layout Renderer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Slim controllers a reusable `LayoutRenderer` that wraps their HTML in a clean, parameterized reimplementation of the legacy app's chrome (head/nav/sidebar/footer), then prove it end-to-end by migrating `JudgingController` and its four templates onto it.

**Architecture:** `src/Kernel/View/LayoutRenderer.php` has two entry points — `admin()` (nav + sidebar) and `authenticated()` (nav only) — each rendering four new template partials under `templates/layout/` with explicit parameters only (no `$_SESSION`/global reads inside the partials themselves). `JudgingController`'s four HTML-rendering actions and their templates are migrated to call it, using real Bootstrap 3 classes instead of the current invented vocabulary.

**Tech Stack:** Plain PHP includes (no templating engine), PHPUnit (Unit tier for `LayoutRenderer`, Integration tier for `JudgingController` — see Task 2's rationale), Slim PSR-7 (`Slim\Psr7\Factory\ServerRequestFactory`/`ResponseFactory`).

## Global Constraints

- No new Composer dependencies (design explicitly rejected introducing Twig — see spec Background).
- Layout partials (`head.php`, `nav.php`, `sidebar.php`, `footer.php`) must not read `$_SESSION` or any global directly — every value comes from an explicit parameter, resolved once by `LayoutRenderer`.
- Asset paths are root-relative (`/css/...`), matching the convention Judging's own existing templates already use — no `$base_url` reconstruction.
- `LayoutRenderer` always links `.min.css` files — legacy's `DEBUG`/`TESTING`-mode unminified-CSS swap and cache-busting `?t=` param are explicitly not replicated (see spec Data Flow).
- `sidebar.php` is a small, new, hardcoded list of links to real admin section hrefs (`index.php?section=admin&go=entries`, `&go=participants`, `&go=judging_tables`→`/judging/tables`, `&go=preferences`) — not a port of `admin/sidebar.admin.php`, which is a live-data status dashboard, not a nav menu (see spec's corrected Components section).
- Theme URL resolution must match `site/bootstrap.php:432-448`'s formula: `$css_common_url = '/css/common.min.css'`, `$theme = '/css/' . ($_SESSION['prefsTheme'] ?? 'default') . '.min.css'`.

---

### Task 1: `LayoutRenderer` and layout partials

**Files:**
- Create: `templates/layout/head.php`
- Create: `templates/layout/nav.php`
- Create: `templates/layout/sidebar.php`
- Create: `templates/layout/footer.php`
- Create: `src/Kernel/View/LayoutRenderer.php`
- Create: `tests/Unit/Kernel/View/fixtures/fixture-template.php`
- Test: `tests/Unit/Kernel/View/LayoutRendererTest.php`

**Interfaces:**
- Consumes: `Bcoem\Security\Identity` (`->loggedIn: bool`, `->username: ?string`, `->role: Role`), constructed in tests via `Identity::fromSession(['loginUsername' => ..., 'userLevel' => ...])` (private constructor — this is the only way to build one).
- Produces: `Bcoem\Kernel\View\LayoutRenderer` with
  `public function admin(Identity $identity, string $title, string $activeNav, string $templatePath, array $vars = []): string`
  and
  `public function authenticated(Identity $identity, string $title, string $templatePath, array $vars = []): string`.
  Both throw `\RuntimeException` if `$templatePath` doesn't exist. Consumed by Task 2.

- [ ] **Step 1: Create the fixture template and write the failing test**

Create `tests/Unit/Kernel/View/fixtures/fixture-template.php`:

```php
<?php
/**
 * Throwaway fixture template for LayoutRendererTest - not a real view.
 * Available variables: $message (string).
 */
?>
<p class="fixture-content"><?= e($message) ?></p>
```

Create `tests/Unit/Kernel/View/LayoutRendererTest.php`:

```php
<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Kernel\View;

use Bcoem\Kernel\View\LayoutRenderer;
use Bcoem\Security\Identity;
use PHPUnit\Framework\TestCase;

class LayoutRendererTest extends TestCase
{
    private LayoutRenderer $renderer;
    private string $fixtureTemplate;

    protected function setUp(): void
    {
        unset($_SESSION['prefsTheme']);
        $this->renderer = new LayoutRenderer();
        $this->fixtureTemplate = __DIR__ . '/fixtures/fixture-template.php';
    }

    public function test_admin_renders_inner_content_verbatim(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);
        $html = $this->renderer->admin($identity, 'Judging Tables', 'judging', $this->fixtureTemplate, ['message' => 'hello from fixture']);

        $this->assertStringContainsString('<p class="fixture-content">hello from fixture</p>', $html);
    }

    public function test_admin_includes_title_in_page_header(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);
        $html = $this->renderer->admin($identity, 'Judging Tables', 'judging', $this->fixtureTemplate, ['message' => 'x']);

        $this->assertStringContainsString('<h1>Judging Tables</h1>', $html);
    }

    public function test_admin_shows_identity_username_and_logout_link(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);
        $html = $this->renderer->admin($identity, 'Judging Tables', 'judging', $this->fixtureTemplate, ['message' => 'x']);

        $this->assertStringContainsString('admin@test.local', $html);
        $this->assertStringContainsString('logout', $html);
    }

    public function test_admin_includes_sidebar(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);
        $html = $this->renderer->admin($identity, 'Judging Tables', 'judging', $this->fixtureTemplate, ['message' => 'x']);

        $this->assertStringContainsString('class="sidebar', $html);
    }

    public function test_authenticated_omits_sidebar(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'judge@test.local', 'userLevel' => '2']);
        $html = $this->renderer->authenticated($identity, 'Judging Scoresheet', $this->fixtureTemplate, ['message' => 'x']);

        $this->assertStringNotContainsString('class="sidebar', $html);
    }

    public function test_authenticated_still_includes_nav_and_footer(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'judge@test.local', 'userLevel' => '2']);
        $html = $this->renderer->authenticated($identity, 'Judging Scoresheet', $this->fixtureTemplate, ['message' => 'x']);

        $this->assertStringContainsString('<nav', $html);
        $this->assertStringContainsString('<footer', $html);
    }

    public function test_default_theme_links_default_css(): void
    {
        unset($_SESSION['prefsTheme']);
        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);
        $html = $this->renderer->admin($identity, 'T', 'judging', $this->fixtureTemplate, ['message' => 'x']);

        $this->assertStringContainsString('/css/common.min.css', $html);
        $this->assertStringContainsString('/css/default.min.css', $html);
    }

    public function test_session_theme_preference_overrides_default_css(): void
    {
        $_SESSION['prefsTheme'] = 'bruxellensis';
        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);
        $html = $this->renderer->admin($identity, 'T', 'judging', $this->fixtureTemplate, ['message' => 'x']);
        unset($_SESSION['prefsTheme']);

        $this->assertStringContainsString('/css/bruxellensis.min.css', $html);
    }

    public function test_missing_template_throws(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/does-not-exist\.php/');

        $this->renderer->admin($identity, 'T', 'judging', __DIR__ . '/fixtures/does-not-exist.php', []);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Kernel/View/LayoutRendererTest.php`
Expected: FAIL — `Class "Bcoem\Kernel\View\LayoutRenderer" not found` (the class doesn't exist yet).

- [ ] **Step 3: Create the four layout partials**

Create `templates/layout/head.php`:

```php
<?php
/**
 * Layout chrome: <head>. Available variables (set by LayoutRenderer::wrap()):
 * - $title: string
 * - $cssCommonUrl: string
 * - $themeUrl: string
 */
?>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - Brew Competition Online Entry &amp; Management</title>
    <link rel="stylesheet" type="text/css" href="<?= e($cssCommonUrl) ?>" />
    <link rel="stylesheet" type="text/css" href="<?= e($themeUrl) ?>" />
</head>
```

Create `templates/layout/nav.php`:

```php
<?php
/**
 * Layout chrome: top nav. Available variables (set by LayoutRenderer::wrap()):
 * - $identity: Identity
 */
?>
<nav class="navbar navbar-default">
    <div class="container-fluid">
        <div class="navbar-header">
            <a class="navbar-brand" href="/">Brew Competition Online Entry &amp; Management</a>
        </div>
        <ul class="nav navbar-nav navbar-right">
            <li><span class="navbar-text"><?= e($identity->username ?? '') ?></span></li>
            <li><a href="/includes/process.inc.php?section=logout">Log out</a></li>
        </ul>
    </div>
</nav>
```

Create `templates/layout/sidebar.php`:

```php
<?php
/**
 * Layout chrome: admin sidebar navigation. Available variables (set by
 * LayoutRenderer::wrap()):
 * - $activeNav: string
 *
 * A small, new, static list of real admin section links (hrefs confirmed
 * against admin/sidebar.admin.php's own dashboard links) - NOT a port of
 * that file, which is a 513-line live-data status dashboard, not a nav
 * menu. See Docs/superpowers/specs/2026-07-22-shared-layout-renderer-design.md.
 */
$links = [
    'entries' => ['label' => 'Entries', 'href' => '/index.php?section=admin&go=entries'],
    'participants' => ['label' => 'Participants', 'href' => '/index.php?section=admin&go=participants'],
    'judging' => ['label' => 'Judging Tables', 'href' => '/judging/tables'],
    'preferences' => ['label' => 'Preferences', 'href' => '/index.php?section=admin&go=preferences'],
];
?>
<div class="sidebar col-lg-3 col-md-4 col-sm-12 col-xs-12">
    <ul class="nav nav-pills nav-stacked">
        <?php foreach ($links as $key => $link): ?>
            <li<?= $activeNav === $key ? ' class="active"' : '' ?>>
                <a href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
```

Create `templates/layout/footer.php`:

```php
<footer class="navbar navbar-default navbar-fixed-bottom">
    <p class="navbar-text col-md-12 col-sm-12 col-xs-12 text-muted small">
        &copy; <?= date('Y') ?> Brew Competition Online Entry &amp; Management
    </p>
</footer>
```

- [ ] **Step 4: Create `LayoutRenderer`**

Create `src/Kernel/View/LayoutRenderer.php`:

```php
<?php
declare(strict_types=1);

namespace Bcoem\Kernel\View;

use Bcoem\Security\Identity;

/**
 * Renders modernized Slim views inside a clean, parameterized
 * reimplementation of the legacy app's chrome (head/nav/sidebar/footer) -
 * explicit inputs only, no ambient globals. See
 * Docs/superpowers/specs/2026-07-22-shared-layout-renderer-design.md for
 * the full design rationale (why not reuse legacy/index.legacy.php's real
 * nav.sec.php/sidebar.admin.php includes verbatim - they read a large set
 * of ambient variables computed earlier in legacy's global bootstrap chain).
 *
 * Also the single place that guarantees templates/helpers.php's e() helper
 * is loaded before any template (chrome or inner) runs.
 */
final class LayoutRenderer
{
    private const LAYOUT_DIR = __DIR__ . '/../../../templates/layout';
    private const HELPERS_PATH = __DIR__ . '/../../../templates/helpers.php';

    public function admin(Identity $identity, string $title, string $activeNav, string $templatePath, array $vars = []): string
    {
        return $this->wrap($identity, $title, $activeNav, true, $this->renderTemplate($templatePath, $vars));
    }

    public function authenticated(Identity $identity, string $title, string $templatePath, array $vars = []): string
    {
        return $this->wrap($identity, $title, '', false, $this->renderTemplate($templatePath, $vars));
    }

    private function wrap(Identity $identity, string $title, string $activeNav, bool $withSidebar, string $content): string
    {
        $cssCommonUrl = '/css/common.min.css';
        $themePref = $_SESSION['prefsTheme'] ?? 'default';
        $themeUrl = '/css/' . $themePref . '.min.css';

        ob_start();
        include self::LAYOUT_DIR . '/head.php';
        $head = ob_get_clean();

        ob_start();
        include self::LAYOUT_DIR . '/nav.php';
        $nav = ob_get_clean();

        $sidebar = '';
        $contentColumnClass = 'col-lg-12 col-md-12 col-sm-12 col-xs-12';
        if ($withSidebar) {
            ob_start();
            include self::LAYOUT_DIR . '/sidebar.php';
            $sidebar = ob_get_clean();
            $contentColumnClass = 'col-lg-9 col-md-8 col-sm-12 col-xs-12';
        }

        ob_start();
        include self::LAYOUT_DIR . '/footer.php';
        $footer = ob_get_clean();

        $titleHtml = e($title);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
{$head}
<body>
{$nav}
<div class="container-fluid">
    <div class="row">
        {$sidebar}
        <div class="{$contentColumnClass}">
            <div class="page-header">
                <h1>{$titleHtml}</h1>
            </div>
            {$content}
        </div>
    </div>
</div>
{$footer}
</body>
</html>
HTML;
    }

    private function renderTemplate(string $templatePath, array $vars): string
    {
        require_once self::HELPERS_PATH;

        if (!is_file($templatePath)) {
            throw new \RuntimeException("LayoutRenderer: template not found: {$templatePath}");
        }

        extract($vars);
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Kernel/View/LayoutRendererTest.php`
Expected: `OK (9 tests, ...)` — all pass, no warnings.

- [ ] **Step 6: Run PHPStan on the new files**

Run: `./vendor/bin/phpstan analyse src/Kernel/View/LayoutRenderer.php`
Expected: `[OK] No errors`

- [ ] **Step 7: Commit**

```bash
git add templates/layout/ src/Kernel/View/LayoutRenderer.php tests/Unit/Kernel/View/
git commit -m "feat: add LayoutRenderer for visually-consistent modernized views"
```

---

### Task 2: Migrate `JudgingController` to `LayoutRenderer`

Four real, pre-existing bugs get fixed as a side effect of this migration (found during this plan's own investigation, all confirmed empirically):

1. **Judging's HTML views currently cannot render at all.** `JudgingController`'s four `include __DIR__ . '/../../templates/Judging/...'` calls (lines 256, 280, 311, 340) are missing one `../` — `src/Kernel/Controller` is 3 directories below the repo root, not 2. Confirmed via `php -r 'var_dump(file_exists(...));'`: the current path resolves to `src/templates/Judging/...`, which doesn't exist. `include` on a missing file emits an `E_WARNING` and continues, so today these actions silently return a 200 response containing a PHP warning instead of real content — not a hard crash, but never real output either.
2. **`e()` is never loaded before Judging's templates use it.** `templates/helpers.php` (which defines it) is `require_once`'d only by the three *dead, unwired* Entry templates — nothing in `JudgingController`'s request path loads it. Confirmed via `grep -rln "helpers.php"` across the repo. `LayoutRenderer::renderTemplate()` (Task 1) fixes this for every future controller, Judging included.
3. **`templates/Judging/judge-scoresheet.php:22` calls `$currentIdentity->user()->email()`.** The real `Bcoem\Security\Identity` class (`src/Security/Identity.php`) has no `user()` method at all — only `loggedIn`, `username`, `role`. This would fatal with `Call to undefined method`. Fixed in this task by using `$currentIdentity->username` directly.
4. **`getTablesView()` never defines `$location`, but `admin-table-list.php`'s "Create New Table" link calls `$location->value()`.** The controller only builds `$locationId` (an int); the template needs a `LocationId` value object under the name `$location`. This would have been an undefined-variable error the moment bug #1 was fixed without also fixing this — masked because bug #1 meant this code path never actually ran. Fixed in this task's Step 3 by constructing `$location = new LocationId($locationId);` before passing it to the template.

**Files:**
- Modify: `src/Kernel/Controller/JudgingController.php:229-348` (the four HTML-rendering actions: `getTablesView`, `getTableDetailView`, `getJudgeScoresheet`, `getTableForm`)
- Modify: `templates/Judging/admin-table-list.php`
- Modify: `templates/Judging/admin-table-detail.php`
- Modify: `templates/Judging/judge-scoresheet.php`
- Modify: `templates/Judging/table-form.php`
- Test: `tests/Integration/Kernel/Controller/JudgingControllerTest.php`

**Interfaces:**
- Consumes: `Bcoem\Kernel\View\LayoutRenderer::admin()`/`::authenticated()` (Task 1). `JudgingController`'s constructor gains a third parameter, `private readonly LayoutRenderer $layout` — PHP-DI autowires it automatically (confirmed: `JudgingController` has no explicit `container.php` entry today, relying entirely on autowiring of its two existing services; `LayoutRenderer` has a zero-argument constructor, so no `container.php` changes are needed at all).
- Produces: nothing new consumed by later tasks — this is the plan's proof-of-concept, terminal task.

**Note on test tier:** this test is **Integration**, not Unit. `JudgingTableService`, `JudgingScoreService`, and their repository/validation collaborators (`JudgingTableRepository`, `JudgingScoreRepository`, `JudgingValidationService`) are all `final class` with no interface seam, so `JudgingController` cannot be constructed with mocked collaborators the way `RegistrationControllerTest` mocks `RegistrationRepository`/`CaptchaVerifier` (those are interfaces; only `RegistrationService` itself is final). A real DB-backed `IntegrationTestCase` is the only way to exercise `JudgingController` at all.

**Separately noted, not fixed here:** `tests/Integration/Domain/Judging/JudgingTableServiceIntegrationTest.php` (and its three sibling Judging integration tests) reference `Bcoem\Kernel\Identity`, `Bcoem\Kernel\Security\User`, and `Bcoem\Kernel\Security\Role` — none of which exist in the current codebase (the real classes are `Bcoem\Security\Identity`/`Role`, no `User` class). These tests are also gated behind a bare `getenv('DB_TEST_HOST')` check (not the `BCOEM_DB_*` convention `IntegrationTestCase` actually uses), so they likely `markTestSkipped()` in every real run today, silently hiding that they reference non-existent classes. This is exactly the kind of Phase 3 trust gap flagged as still-open in `[[project-modernization]]`'s memory — report it to the user after this plan lands; do not fix it as part of this task.

- [ ] **Step 1: Write the failing test**

Create `tests/Integration/Kernel/Controller/JudgingControllerTest.php`:

```php
<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration\Kernel\Controller;

use Bcoem\Database\Connection;
use Bcoem\Domain\Judging\Repository\JudgingScoreRepository;
use Bcoem\Domain\Judging\Repository\JudgingTableRepository;
use Bcoem\Domain\Judging\Service\JudgingScoreService;
use Bcoem\Domain\Judging\Service\JudgingTableService;
use Bcoem\Domain\Judging\Service\JudgingValidationService;
use Bcoem\Domain\Judging\ValueObject\LocationId;
use Bcoem\Kernel\Controller\JudgingController;
use Bcoem\Kernel\View\LayoutRenderer;
use Bcoem\Security\Identity;
use BCOEM\Tests\Integration\IntegrationTestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class JudgingControllerTest extends IntegrationTestCase
{
    private JudgingController $controller;
    private JudgingTableService $tableService;
    private Identity $admin;
    private Identity $judge;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = new Connection(self::$conn);
        $tableRepository = new JudgingTableRepository($connection, self::$pfx);
        $scoreRepository = new JudgingScoreRepository($connection, self::$pfx);
        $validation = new JudgingValidationService();

        $this->tableService = new JudgingTableService($tableRepository, $validation);
        $scoreService = new JudgingScoreService($scoreRepository, $tableRepository, $validation);

        $this->controller = new JudgingController($this->tableService, $scoreService, new LayoutRenderer());

        $this->admin = Identity::fromSession(['loginUsername' => 'admin@test.local', 'userLevel' => '1']);
        $this->judge = Identity::fromSession(['loginUsername' => 'judge@test.local', 'userLevel' => '2']);
    }

    public function test_get_tables_view_returns_styled_html_with_real_bootstrap_classes(): void
    {
        $this->tableService->createTable('Test Table A', new LocationId(1), 10, $this->admin);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/judging/tables?location=1')
            ->withAttribute('identity', $this->admin);
        $response = $this->controller->getTablesView($request, (new ResponseFactory())->createResponse());

        $this->assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        $this->assertStringContainsString('Test Table A', $html);
        $this->assertStringContainsString('btn btn-primary', $html);
        $this->assertStringNotContainsString('button-primary', $html);
        $this->assertStringContainsString('class="sidebar', $html);
        $this->assertStringContainsString('<nav', $html);
    }

    public function test_get_table_form_returns_styled_html(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/judging/tables/create?location=1')
            ->withAttribute('identity', $this->admin);
        $response = $this->controller->getTableForm($request, (new ResponseFactory())->createResponse());

        $this->assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        $this->assertStringContainsString('Create New Table', $html);
        $this->assertStringContainsString('btn btn-primary', $html);
        $this->assertStringContainsString('class="sidebar', $html);
    }

    public function test_get_table_detail_view_returns_styled_html(): void
    {
        $tableId = $this->tableService->createTable('Detail Table', new LocationId(1), 10, $this->admin);

        $request = (new ServerRequestFactory())->createServerRequest('GET', "/judging/tables/{$tableId->value()}")
            ->withAttribute('identity', $this->admin);
        $response = $this->controller->getTableDetailView($request, (new ResponseFactory())->createResponse(), (string) $tableId->value());

        $this->assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        $this->assertStringContainsString('Detail Table', $html);
        $this->assertStringContainsString('label label-', $html);
        $this->assertStringNotContainsString('badge-primary', $html);
    }

    public function test_get_judge_scoresheet_uses_authenticated_layout_without_sidebar(): void
    {
        $tableId = $this->tableService->createTable('Scoresheet Table', new LocationId(1), 10, $this->admin);

        $request = (new ServerRequestFactory())->createServerRequest('GET', "/judging/tables/{$tableId->value()}/scoresheet")
            ->withAttribute('identity', $this->judge);
        $response = $this->controller->getJudgeScoresheet($request, (new ResponseFactory())->createResponse(), (string) $tableId->value());

        $this->assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        $this->assertStringContainsString('judge@test.local', $html);
        $this->assertStringNotContainsString('class="sidebar', $html);
        $this->assertStringContainsString('<nav', $html);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit tests/Integration/Kernel/Controller/JudgingControllerTest.php`
Expected: FAIL — `Too few arguments to function Bcoem\Kernel\Controller\JudgingController::__construct(), 2 passed ... and exactly 3 expected` (the controller doesn't accept `LayoutRenderer` yet).

- [ ] **Step 3: Migrate `JudgingController`'s constructor and four HTML actions**

In `src/Kernel/Controller/JudgingController.php`, change the constructor (lines 36-40):

```php
    public function __construct(
        private readonly JudgingTableService $tableService,
        private readonly JudgingScoreService $scoreService,
        private readonly \Bcoem\Kernel\View\LayoutRenderer $layout
    ) {
    }
```

Replace `getTablesView()` (lines 229-263) with:

```php
    public function getTablesView(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            return ResponseHelper::json($response, ['error' => 'Unauthorized'], 401);
        }

        try {
            $queryParams = $request->getQueryParams();
            $locationId = (int) ($queryParams['location'] ?? 0);
            $state = $queryParams['state'] ?? null;
            $selectedState = null;

            if ($state) {
                $selectedState = TableState::from($state);
                $tables = $this->tableService->listTablesByLocationAndState(
                    new LocationId($locationId),
                    $selectedState
                );
            } else {
                $tables = $this->tableService->listTablesByLocation(new LocationId($locationId));
            }

            $locationName = "Location #$locationId";
            $states = TableState::cases();
            // Real, previously-latent bug: admin-table-list.php's "Create New
            // Table" link calls $location->value() (a LocationId), but the
            // original controller never defined $location - only $locationId
            // (an int). Masked entirely by the broken include path (this
            // task's Fix #1), which meant this line never actually executed.
            $location = new LocationId($locationId);

            $html = $this->layout->admin(
                $identity,
                'Judging Tables',
                'judging',
                __DIR__ . '/../../../templates/Judging/admin-table-list.php',
                compact('tables', 'location', 'locationName', 'states', 'selectedState')
            );

            return ResponseHelper::html($response, $html);
        } catch (\Throwable $e) {
            return ResponseHelper::text($response, 'Error: ' . $e->getMessage(), 400);
        }
    }
```

Replace `getTableDetailView()` (lines 265-288) with:

```php
    public function getTableDetailView(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            return ResponseHelper::json($response, ['error' => 'Unauthorized'], 401);
        }

        try {
            $id = (int) $id;
            $table = $this->tableService->getTable(new TableId($id));
            $scores = $this->scoreService->listScoresForTable(new TableId($id));
            $flights = $table->flights()->all();
            $allowedTransitions = $table->state()->getAllowedTransitions();

            $html = $this->layout->admin(
                $identity,
                $table->name(),
                'judging',
                __DIR__ . '/../../../templates/Judging/admin-table-detail.php',
                compact('table', 'flights', 'scores', 'allowedTransitions')
            );

            return ResponseHelper::html($response, $html);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : 400;
            return ResponseHelper::text($response, 'Error: ' . $e->getMessage(), $status);
        }
    }
```

Replace `getJudgeScoresheet()` (lines 290-319) with:

```php
    public function getJudgeScoresheet(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            return ResponseHelper::json($response, ['error' => 'Unauthorized'], 401);
        }

        try {
            $id = (int) $id;
            $table = $this->tableService->getTable(new TableId($id));
            $flights = $table->flights()->all();
            $scores = $this->scoreService->listScoresForTable(new TableId($id));

            $scoresIndex = [];
            foreach ($scores as $score) {
                $scoresIndex[$score->entryId()->value()] = $score;
            }
            $scores = $scoresIndex;

            $currentIdentity = $identity;

            $html = $this->layout->authenticated(
                $identity,
                'Judging Scoresheet - ' . $table->name(),
                __DIR__ . '/../../../templates/Judging/judge-scoresheet.php',
                compact('table', 'flights', 'scores', 'currentIdentity')
            );

            return ResponseHelper::html($response, $html);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : 400;
            return ResponseHelper::text($response, 'Error: ' . $e->getMessage(), $status);
        }
    }
```

Replace `getTableForm()` (lines 321-348) with:

```php
    public function getTableForm(ServerRequestInterface $request, ResponseInterface $response, ?string $id = null): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            return ResponseHelper::json($response, ['error' => 'Unauthorized'], 401);
        }

        if (!$identity->role->satisfies(Role::Admin)) {
            return ResponseHelper::json($response, ['error' => 'Forbidden: Admin role required'], 403);
        }

        try {
            $queryParams = $request->getQueryParams();
            $locationId = (int) ($queryParams['location'] ?? 0);
            $id = $id !== null ? (int) $id : null;
            $isEditMode = $id !== null;
            $table = $isEditMode ? $this->tableService->getTable(new TableId($id)) : null;
            $location = new LocationId($locationId);

            $html = $this->layout->admin(
                $identity,
                $isEditMode ? 'Edit Table' : 'Create New Table',
                'judging',
                __DIR__ . '/../../../templates/Judging/table-form.php',
                compact('table', 'location', 'isEditMode')
            );

            return ResponseHelper::html($response, $html);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : 400;
            return ResponseHelper::text($response, 'Error: ' . $e->getMessage(), $status);
        }
    }
```

- [ ] **Step 4: Rewrite `templates/Judging/admin-table-list.php`'s Bootstrap classes**

Change (this file's structure and PHP logic stay the same — only class names change):
- `class="button button-primary"` → `class="btn btn-primary"`
- `class="badge badge-<?= e($table->state()->cssClass()) ?>"` → replace with a real Bootstrap 3 label mapping. Change:
  ```php
  <span class="badge badge-<?= e($table->state()->cssClass()) ?>">
      <?= e($table->state()->label()) ?>
  </span>
  ```
  to:
  ```php
  <span class="label label-<?= e(str_replace('badge-', '', $table->state()->cssClass())) ?>">
      <?= e($table->state()->label()) ?>
  </span>
  ```
  (`TableState::cssClass()` returns e.g. `'badge-success'`, `'badge-danger'` — stripping the `badge-` prefix and using Bootstrap 3's real `.label label-*` convention instead of the nonexistent `.badge-*` variants.)
- `class="button button-small"` → `class="btn btn-xs"`
- Remove the outer `<div class="container">`/`</div>` wrapper (lines 13 and 73) — `LayoutRenderer` now provides the page container; leave everything between them as-is.

- [ ] **Step 5: Rewrite `templates/Judging/admin-table-detail.php`'s Bootstrap classes**

Same substitutions as Step 4, applied to this file:
- `class="button button-primary"` → `class="btn btn-primary"` (2 occurrences)
- `class="button button-small button-danger"` → `class="btn btn-xs btn-danger"`
- `class="button"` (the "Back to Tables" link) → `class="btn btn-default"`
- `class="badge badge-<?= e($table->state()->cssClass()) ?>"` → same `label label-<?= ... ?>` substitution as Step 4
- Remove the outer `<div class="container">`/`</div>` wrapper (lines 13 and 166).

- [ ] **Step 6: Rewrite `templates/Judging/judge-scoresheet.php`'s Bootstrap classes and fix the `Identity` bug**

- Line 22: change `<?= e($currentIdentity->user()->email()) ?>` to `<?= e($currentIdentity->username ?? '') ?>` — `Identity` has no `user()`/`email()` method (`src/Security/Identity.php` only has `loggedIn`, `username`, `role`).
- `class="badge badge-<?= e($table->state()->cssClass()) ?>"` → same `label label-<?= ... ?>` substitution as Step 4.
- `class="button button-primary"` → `class="btn btn-primary"`
- `class="button button-secondary"` → `class="btn btn-default"`
- Remove the entire inline `<style>` block (lines 126-190) — its rules (`.scoresheet-table`, `.score-input`, etc.) duplicate what Bootstrap 3's `.table`/`.form-control` already provide via the now-linked shared stylesheet. Replace `class="scoresheet-table"` with `class="table table-bordered"`, and add `class="form-control"` to the `.score-input`/`.place-input`/`.score-type-select` inputs/select (lines 87, 92, 97).
- Remove the outer `<div class="container judge-scoresheet">`/`</div>` wrapper (lines 12 and 192).

- [ ] **Step 7: Rewrite `templates/Judging/table-form.php`'s Bootstrap classes**

- `class="button button-primary"` → `class="btn btn-primary"`
- `class="button button-secondary"` → `class="btn btn-default"`
- `class="button button-danger"` → `class="btn btn-danger"`
- Remove the entire inline `<style>` block (lines 78-128) — replace `class="form-group"` divs' bare `<input>`/`<select>` elements by adding `class="form-control"` to each (lines 26, 34, 43), matching Bootstrap 3's real form styling instead of the hand-rolled CSS.
- Remove the outer `<div class="container">`/`</div>` wrapper (lines 12 and 129).

- [ ] **Step 8: Run the test to verify it passes**

Run: `./vendor/bin/phpunit tests/Integration/Kernel/Controller/JudgingControllerTest.php`
Expected: `OK (4 tests, ...)` — all pass. If the DB isn't reachable, PHPUnit will report `Skipped: 4` instead with the message from `IntegrationTestCase::setUpBeforeClass()` ("Integration DB unavailable ... run: docker-compose up -d db") — in that case run `docker compose up -d db` first, wait for it to report healthy (`docker compose ps`), then re-run.

- [ ] **Step 9: Run PHPStan on the modified files**

Run: `./vendor/bin/phpstan analyse src/Kernel/Controller/JudgingController.php`
Expected: `[OK] No errors`

- [ ] **Step 10: Run the full Unit suite to confirm no regressions**

Run: `./vendor/bin/phpunit --testsuite=Unit`
Expected: same pass/fail counts as before this task (no new failures attributable to this change — `JudgingController` has no existing Unit tests to regress, per this task's own "Note on test tier").

- [ ] **Step 11: Commit**

```bash
git add src/Kernel/Controller/JudgingController.php templates/Judging/ tests/Integration/Kernel/Controller/
git commit -m "fix: migrate JudgingController to LayoutRenderer, real Bootstrap classes

Fixes three pre-existing bugs found during migration: a broken template
include path (missing one ../, confirmed via file_exists()) that made
every Judging HTML view silently return a blank/warning-polluted 200,
a missing require of templates/helpers.php that left e() undefined,
and judge-scoresheet.php calling Identity::user()->email() which
doesn't exist on the real Identity class."
```

---

## Self-Review Notes

- **Spec coverage:** `LayoutRenderer` with `admin()`/`authenticated()` (Task 1) matches the spec's Architecture section exactly, including method signatures. All four layout partials match the spec's Components table. The theme-resolution formula (Task 1, `wrap()`) matches the spec's Data Flow section verbatim (`/css/common.min.css`, `prefsTheme ?? 'default'`). The `require_once` for `templates/helpers.php` inside `renderTemplate()` fulfills the spec's stated responsibility ("the single place that guarantees `e()` is loaded"). The corrected `sidebar.php` (new static links, not a port) matches the spec's corrected Components section. Error handling (missing-template `\RuntimeException`, non-nullable `Identity` param) matches the spec's Error Handling section. Testing matches the spec's Testing section for Task 1's Unit tests; Task 2's move to the Integration tier is a necessary, documented deviation from the spec's original "`JudgingControllerTest` updated (existing file)" language — corrected here because no such file existed and the domain's `final` classes rule out Unit-level mocking entirely (see Task 2's "Note on test tier").
- **Placeholder scan:** no TBD/TODO; every step has complete code or an exact command with expected output.
- **Type consistency:** `LayoutRenderer::admin()`/`::authenticated()` signatures are identical between Task 1's implementation and Task 2's call sites. `JudgingController`'s constructor promotion (`private readonly LayoutRenderer $layout`) matches how Task 2's test constructs it (`new JudgingController($this->tableService, $scoreService, new LayoutRenderer())`). `Identity`'s real shape (`loggedIn`/`username`/`role`, `fromSession()`) is used consistently across both tasks' tests — no test references the nonexistent `user()`/`email()` methods the pre-existing bug in `judge-scoresheet.php` was calling.
