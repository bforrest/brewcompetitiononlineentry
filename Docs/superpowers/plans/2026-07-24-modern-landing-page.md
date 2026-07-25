# Modern Landing Page Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Serve `GET /` through a modern Slim landing-page controller that reproduces the visual states and user interactions of the no-query legacy homepage while preserving `GET /index.php` as the legacy reference.

**Architecture:** Extract legacy date-window semantics into a shared immutable value object, build a prepared-statement read repository and typed landing-page state model, render that model through focused Bootstrap 5 templates and the existing public layout, and cut over only the static `/` route. Keep all legacy homepage files unchanged and prove parity against `/index.php` with unit, integration, and Playwright tests.

**Tech Stack:** PHP 8.2, Slim 4, PHP-DI, mysqli through `Bcoem\Database\Connection`, PHPUnit 10, PHPStan, Bootstrap 5, Playwright.

## Global Constraints

- Follow `CLAUDE.md` and `AGENTS.md`.
- The parity source is `GET /index.php` with no query string, which renders through `legacy/index.php` → `index.pub.php` → `pub/*.pub.php`.
- Do not modify or delete `index.pub.php`, `pub/default.pub.php`, any other legacy homepage fragment, `lib/common.lib.php`, or legacy callers.
- Keep `/index.php` mapped to `LegacyPageHandler`.
- Register the static `/` route before the SEF catch-all.
- Register `landing.page` as `Role::Anonymous` in `config/access_policy.php`.
- New code must not call `open_or_closed()`, require `lib/common.lib.php`, call `mysqli_*`, interpolate values into SQL, expose raw database rows to templates, or read ambient globals from templates.
- All value-bearing SQL uses `Connection::select()` / `selectOne()` placeholders. Prefixed table identifiers come only from the validated constructor prefix established by the container/bootstrap boundary.
- Use Bootstrap 5 only: no `navbar-default`, `hidden-print`, `data-toggle`, `data-target`, `pull-right`, or other Bootstrap 3 vocabulary.
- Preserve the existing login/logout/registration/output endpoints and their authorization/CSRF behavior.
- Escape dynamic HTML with `e()`. Validate external URLs before constructing links. Apply `encodeURIComponent` to dynamic values constructed in Playwright.
- Use fixed timestamps in tests; do not assert behavior against the wall clock.
- Commit after every task. Do not include the existing unrelated `AGENTS.md` working-tree modification in any commit.

---

## File Map

### Shared window behavior

- Create `src/Domain/Shared/ValueObject/WindowStatus.php`
- Create `src/Domain/Shared/ValueObject/DateWindow.php`
- Create `tests/Unit/Domain/Shared/ValueObject/DateWindowTest.php`
- Modify `src/Domain/Registration/Service/RegistrationService.php`
- Modify `tests/Unit/Domain/Registration/Service/RegistrationServiceTest.php`

### Landing-page domain

- Create `src/Domain/LandingPage/Model/ContestOverview.php`
- Create `src/Domain/LandingPage/Model/CompetitionWindows.php`
- Create `src/Domain/LandingPage/Model/CompetitionLimits.php`
- Create `src/Domain/LandingPage/Model/JudgingProgress.php`
- Create `src/Domain/LandingPage/Model/CompetitionLocations.php`
- Create `src/Domain/LandingPage/Model/Contact.php`
- Create `src/Domain/LandingPage/Model/Sponsor.php`
- Create `src/Domain/LandingPage/Model/Archive.php`
- Create `src/Domain/LandingPage/Model/WinnerSummary.php`
- Create `src/Domain/LandingPage/Presentation/Alert.php`
- Create `src/Domain/LandingPage/Presentation/HeroPresentation.php`
- Create `src/Domain/LandingPage/Presentation/LandingPageCopy.php`
- Create `src/Domain/LandingPage/Presentation/LandingPageContext.php`
- Create `src/Domain/LandingPage/Presentation/LandingPageLinks.php`
- Create `src/Domain/LandingPage/Presentation/LandingPageViewModel.php`
- Create `src/Domain/LandingPage/Repository/LandingPageRepository.php`
- Create `src/Domain/LandingPage/Service/LandingPageCopyAdapter.php`
- Create `src/Domain/LandingPage/Service/LandingPageService.php`
- Create `src/Domain/LandingPage/Resources/en-US.php`
- Create `src/Domain/LandingPage/Resources/en-GB.php`
- Create `src/Domain/LandingPage/Resources/es-419.php`
- Create matching Unit and Integration tests under `tests/Unit/Domain/LandingPage/` and `tests/Integration/LandingPage/`

### HTTP and rendering

- Create `src/Kernel/Controller/LandingPageController.php`
- Modify `src/Kernel/View/LayoutRenderer.php`
- Modify `templates/layout/nav.php`
- Modify `templates/layout/head.php`
- Modify `templates/layout/footer.php`
- Create `templates/LandingPage/home.php`
- Create `templates/LandingPage/partials/alerts.php`
- Create `templates/LandingPage/partials/registration.php`
- Create `templates/LandingPage/partials/at-a-glance.php`
- Create `templates/LandingPage/partials/contacts.php`
- Create `templates/LandingPage/partials/sponsors.php`
- Create `templates/LandingPage/partials/winners.php`
- Create `templates/LandingPage/partials/login.php`
- Create `templates/LandingPage/partials/archives.php`
- Create `tests/Unit/Kernel/Controller/LandingPageControllerTest.php`
- Create `tests/Unit/Kernel/LandingPageTemplateTest.php`
- Modify `tests/Unit/Kernel/View/LayoutRendererPublicTest.php`

### Wiring and parity

- Modify `src/Kernel/container.php`
- Modify `src/Kernel/app.php`
- Modify `config/access_policy.php`
- Create `tests/Unit/Kernel/LandingPageRouteTest.php`
- Create `e2e/tests/landing-page-dual-path.spec.ts`
- Create `e2e/tests/landing-page-accessibility.spec.ts`
- Create `e2e/helpers/landing-fixtures.ts`

---

### Task 1: Characterize the existing homepage contract

**Files:**
- Create: `e2e/tests/landing-page-dual-path.spec.ts`

**Interfaces:**
- Consumes: legacy `GET /index.php`.
- Produces: reusable `captureLandingContract(page)` returning visible regions, link destinations, and form metadata. Later tasks add modern `/` assertions to the same file.

- [ ] **Step 1: Write the legacy characterization helper and baseline test**

```ts
import { expect, Page, test } from '@playwright/test';

type LandingContract = {
  title: string;
  regions: string[];
  loginAction: string;
  loginFields: string[];
  links: Record<string, string>;
};

async function captureLandingContract(page: Page): Promise<LandingContract> {
  const regions = await page.locator('main section[id], #main-content section[id]')
    .evaluateAll(nodes => nodes.map(node => node.id).filter(Boolean));
  const loginForm = page.locator('form:has(input[name="loginUsername"])');

  return {
    title: await page.title(),
    regions,
    loginAction: await loginForm.getAttribute('action') ?? '',
    loginFields: await loginForm.locator('input[name]')
      .evaluateAll(nodes => nodes.map(node => (node as HTMLInputElement).name)),
    links: {
      register: await page.getByRole('link', { name: /register/i }).first().getAttribute('href') ?? '',
      contact: await page.getByRole('link', { name: /contact/i }).first().getAttribute('href') ?? '',
      login: await page.getByRole('link', { name: 'Log In', exact: true }).getAttribute('href') ?? '',
    },
  };
}

test.describe.serial('landing page dual-path parity', () => {
  test('legacy baseline exposes the public interactive contract', async ({ page }) => {
    await page.goto('/index.php');
    const legacy = await captureLandingContract(page);

    expect(legacy.title).toContain('Brew Competition Online Entry & Management');
    expect(legacy.loginAction).toContain('includes/process.inc.php');
    expect(legacy.loginFields).toEqual(expect.arrayContaining(['loginUsername', 'loginPassword']));
    expect(legacy.links.login).toContain('section=login');
  });
});
```

- [ ] **Step 2: Run the characterization test**

Run:

```bash
composer e2e -- landing-page-dual-path.spec.ts
```

Expected: PASS against the existing legacy `/index.php` route. If selectors fail, adjust them to observable legacy semantics before writing modern markup; do not weaken assertions to empty values.

- [ ] **Step 3: Record the seeded baseline screenshots**

Add:

```ts
await expect(page).toHaveScreenshot('landing-legacy-desktop.png', {
  fullPage: true,
  animations: 'disabled',
});
```

Then add a mobile test using:

```ts
test.use({ viewport: { width: 390, height: 844 } });
```

Expected: Playwright creates reviewable baseline images. Commit them only if this repository's existing Playwright snapshot policy tracks snapshots; otherwise retain them as test-report artifacts.

- [ ] **Step 4: Commit**

```bash
git add e2e/tests/landing-page-dual-path.spec.ts
git commit -m "test: characterize legacy landing page"
```

---

### Task 2: Extract the shared date-window value object

**Files:**
- Create: `src/Domain/Shared/ValueObject/WindowStatus.php`
- Create: `src/Domain/Shared/ValueObject/DateWindow.php`
- Create: `tests/Unit/Domain/Shared/ValueObject/DateWindowTest.php`
- Modify: `src/Domain/Registration/Service/RegistrationService.php`
- Modify: `tests/Unit/Domain/Registration/Service/RegistrationServiceTest.php`

**Interfaces:**
- Produces:
  - `WindowStatus::{Upcoming, Open, Closed}`
  - `DateWindow::__construct(int $opensAt, int $closesAt)`
  - `DateWindow::statusAt(int $timestamp): WindowStatus`
  - `DateWindow::isOpenAt(int $timestamp): bool`
- Consumers: `RegistrationService` in this task and `LandingPageService` in Task 5.

- [ ] **Step 1: Write the failing boundary tests**

```php
<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\Shared\ValueObject;

use Bcoem\Domain\Shared\ValueObject\DateWindow;
use Bcoem\Domain\Shared\ValueObject\WindowStatus;
use PHPUnit\Framework\TestCase;

final class DateWindowTest extends TestCase
{
    public static function states(): iterable
    {
        yield 'before opening' => [999, WindowStatus::Upcoming];
        yield 'at opening' => [1000, WindowStatus::Open];
        yield 'inside window' => [1500, WindowStatus::Open];
        yield 'at closing' => [2000, WindowStatus::Closed];
        yield 'after closing' => [2001, WindowStatus::Closed];
    }

    /** @dataProvider states */
    public function test_status_matches_legacy_boundaries(int $now, WindowStatus $expected): void
    {
        $window = new DateWindow(1000, 2000);
        self::assertSame($expected, $window->statusAt($now));
        self::assertSame($expected === WindowStatus::Open, $window->isOpenAt($now));
    }

    public function test_rejects_an_inverted_window(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DateWindow(2000, 1000);
    }
}
```

- [ ] **Step 2: Run the test to verify RED**

Run:

```bash
php vendor/bin/phpunit tests/Unit/Domain/Shared/ValueObject/DateWindowTest.php
```

Expected: FAIL because `DateWindow` and `WindowStatus` do not exist.

- [ ] **Step 3: Implement the enum and value object**

```php
<?php
declare(strict_types=1);

namespace Bcoem\Domain\Shared\ValueObject;

enum WindowStatus: string
{
    case Upcoming = 'upcoming';
    case Open = 'open';
    case Closed = 'closed';
}
```

```php
<?php
declare(strict_types=1);

namespace Bcoem\Domain\Shared\ValueObject;

final readonly class DateWindow
{
    public function __construct(
        private int $opensAt,
        private int $closesAt,
    ) {
        if ($closesAt < $opensAt) {
            throw new \InvalidArgumentException('Window closing time must not precede opening time.');
        }
    }

    public function statusAt(int $timestamp): WindowStatus
    {
        if ($timestamp < $this->opensAt) {
            return WindowStatus::Upcoming;
        }
        if ($timestamp < $this->closesAt) {
            return WindowStatus::Open;
        }
        return WindowStatus::Closed;
    }

    public function isOpenAt(int $timestamp): bool
    {
        return $this->statusAt($timestamp) === WindowStatus::Open;
    }
}
```

- [ ] **Step 4: Run the value-object test to verify GREEN**

Run:

```bash
php vendor/bin/phpunit tests/Unit/Domain/Shared/ValueObject/DateWindowTest.php
```

Expected: PASS, 6 tests.

- [ ] **Step 5: Add RegistrationService equivalence tests**

Add tests fixing contest dates at `1000..2000` and assert:

```php
$this->repository->method('contestDates')->willReturn([
    'contestRegistrationOpen' => 1000,
    'contestRegistrationDeadline' => 2000,
    'contestJudgeOpen' => 1000,
    'contestJudgeDeadline' => 2000,
]);
$this->repository->method('anyJudgingSessionStarted')->willReturn(false);

self::assertTrue($this->service->isRegistrationOpenAt(1000));
self::assertFalse($this->service->isRegistrationOpenAt(2000));
self::assertFalse($this->service->isRegistrationOpenAt(2001));
```

Also assert `anyJudgingSessionStarted() === true` forces both registration and judge windows closed.

The exact-close assertion is a deliberate correction, not accidental parity
drift: legacy `open_or_closed()` falls through to numeric state `0` at
`now === closesAt` because it tests `< closesAt` for open and `> closesAt` for
closed. `DateWindow` uses the conventional half-open interval and returns
`Closed` at that instant.

- [ ] **Step 6: Run the service tests to verify RED**

Run:

```bash
php vendor/bin/phpunit tests/Unit/Domain/Registration/Service/RegistrationServiceTest.php
```

Expected: FAIL because the explicit `*At()` methods do not exist.

- [ ] **Step 7: Replace the legacy function load in RegistrationService**

Implement:

```php
public function isRegistrationOpen(): bool
{
    return $this->isRegistrationOpenAt(time());
}

public function isJudgeWindowOpen(): bool
{
    return $this->isJudgeWindowOpenAt(time());
}

public function isRegistrationOpenAt(int $now): bool
{
    return $this->windowStateAt($now)['registrationOpen'];
}

public function isJudgeWindowOpenAt(int $now): bool
{
    return $this->windowStateAt($now)['judgeWindowOpen'];
}

/** @return array{registrationOpen: bool, judgeWindowOpen: bool} */
private function windowStateAt(int $now): array
{
    $dates = $this->repository->contestDates();
    if ($dates === null) {
        return ['registrationOpen' => true, 'judgeWindowOpen' => true];
    }

    $registrationOpen = (new DateWindow(
        (int) $dates['contestRegistrationOpen'],
        (int) $dates['contestRegistrationDeadline'],
    ))->isOpenAt($now);
    $judgeWindowOpen = (new DateWindow(
        (int) $dates['contestJudgeOpen'],
        (int) $dates['contestJudgeDeadline'],
    ))->isOpenAt($now);

    if ($this->repository->anyJudgingSessionStarted()) {
        return ['registrationOpen' => false, 'judgeWindowOpen' => false];
    }

    return ['registrationOpen' => $registrationOpen, 'judgeWindowOpen' => $judgeWindowOpen];
}
```

Add the `DateWindow` import and delete the `function_exists('open_or_closed')`,
`paths.php`, and `common.lib.php` loading block.

- [ ] **Step 8: Verify no modern registration dependency remains**

Run:

```bash
php vendor/bin/phpunit tests/Unit/Domain/Shared/ValueObject/DateWindowTest.php tests/Unit/Domain/Registration/Service/RegistrationServiceTest.php
rg -n "open_or_closed|common\\.lib\\.php" src/Domain/Registration
```

Expected: tests PASS; `rg` returns no production matches.

- [ ] **Step 9: Commit**

```bash
git add src/Domain/Shared tests/Unit/Domain/Shared \
  src/Domain/Registration/Service/RegistrationService.php \
  tests/Unit/Domain/Registration/Service/RegistrationServiceTest.php
git commit -m "refactor: extract date window state"
```

---

### Task 3: Add typed landing-page read models

**Files:**
- Create: `src/Domain/LandingPage/Model/ContestOverview.php`
- Create: `src/Domain/LandingPage/Model/CompetitionWindows.php`
- Create: `src/Domain/LandingPage/Model/CompetitionLimits.php`
- Create: `src/Domain/LandingPage/Model/JudgingProgress.php`
- Create: `src/Domain/LandingPage/Model/CompetitionLocations.php`
- Create: `src/Domain/LandingPage/Model/Contact.php`
- Create: `src/Domain/LandingPage/Model/Sponsor.php`
- Create: `src/Domain/LandingPage/Model/Archive.php`
- Create: `src/Domain/LandingPage/Model/WinnerSummary.php`
- Create: `src/Domain/LandingPage/Presentation/Alert.php`
- Create: `src/Domain/LandingPage/Presentation/AlertLevel.php`
- Create: `src/Domain/LandingPage/Presentation/HeroPresentation.php`
- Create: `src/Domain/LandingPage/Presentation/LandingPageCopy.php`
- Create: `src/Domain/LandingPage/Presentation/LandingPageContext.php`
- Create: `src/Domain/LandingPage/Presentation/LandingPageLinks.php`
- Create: `src/Domain/LandingPage/Presentation/LandingPageViewModel.php`
- Create: `tests/Unit/Domain/LandingPage/Model/LandingPageModelTest.php`

**Interfaces:**
- Produces the exact immutable types consumed by repository, service, controller, and templates.
- No type in this task reads the database, session, clock, or filesystem.

- [ ] **Step 1: Write constructor-validation tests**

Test representative required validation:

```php
public function test_contest_overview_rejects_blank_title(): void
{
    $this->expectException(\InvalidArgumentException::class);
    new ContestOverview('', 'Host Club', null, null, null);
}

public function test_landing_links_reject_unsafe_external_urls(): void
{
    $this->expectException(\InvalidArgumentException::class);
    new LandingPageLinks(
        register: '/register',
        login: '/index.php?section=login',
        logout: '/includes/process.inc.php?section=logout',
        contact: '/#contact',
        sponsors: '/#sponsors',
        hostWebsite: 'javascript:alert(1)',
        resultsPdf: '/includes/output.inc.php?section=export-results&view=pdf',
        resultsHtml: '/includes/output.inc.php?section=export-results&view=html',
    );
}
```

- [ ] **Step 2: Run the model test to verify RED**

Run:

```bash
php vendor/bin/phpunit tests/Unit/Domain/LandingPage/Model/LandingPageModelTest.php
```

Expected: FAIL because the model classes do not exist.

- [ ] **Step 3: Implement the focused repository models**

Use one class per file. Required signatures:

```php
final readonly class ContestOverview
{
    public function __construct(
        public string $name,
        public string $hostName,
        public ?string $hostWebsite,
        public ?string $hostLocation,
        public ?string $logoPath,
    );
}

final readonly class CompetitionWindows
{
    public function __construct(
        public int $registrationOpensAt,
        public int $registrationClosesAt,
        public int $entryOpensAt,
        public int $entryClosesAt,
        public int $judgeOpensAt,
        public int $judgeClosesAt,
        public ?int $dropoffOpensAt,
        public ?int $dropoffClosesAt,
        public ?int $shippingOpensAt,
        public ?int $shippingClosesAt,
    );
}

final readonly class CompetitionLimits
{
    public function __construct(
        public int $entryCount,
        public int $paidEntryCount,
        public ?int $entryLimit,
        public ?int $paidEntryLimit,
        public int $nearLimitThreshold,
    );
}

final readonly class JudgingProgress
{
    public function __construct(
        public bool $started,
        public bool $ended,
        public bool $displayWinners,
        public int $winnerReleaseAt,
    );
}
```

Add typed `CompetitionLocations`, `Contact`, `Sponsor`, `Archive`, and
`WinnerSummary` classes with scalar properties matching their rendered fields.
Every URL-bearing constructor calls:

```php
private static function assertSafeUrl(?string $url): void
{
    if ($url === null || str_starts_with($url, '/')) {
        return;
    }
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new \InvalidArgumentException('Only relative, HTTP, and HTTPS URLs are allowed.');
    }
}
```

- [ ] **Step 4: Implement the presentation models**

Required signatures:

```php
enum AlertLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Danger = 'danger';
    case Success = 'success';
}

final readonly class Alert
{
    public function __construct(
        public AlertLevel $level,
        public string $message,
        public ?string $linkLabel = null,
        public ?string $linkUrl = null,
    );
}

final readonly class HeroPresentation
{
    public function __construct(
        public string $imageUrl,
        public string $heading,
        public string $subheading,
    );
}

final readonly class LandingPageContext
{
    /** @param list<int> $beverageStyleTypes */
    public function __construct(
        public string $locale,
        public ?string $viewerName,
        public array $beverageStyleTypes,
    ) {
        if (!in_array($locale, ['en-US', 'en-GB', 'es-419'], true)) {
            throw new \InvalidArgumentException('Unsupported landing-page locale.');
        }
        foreach ($beverageStyleTypes as $type) {
            if ($type < 0 || $type > 3) {
                throw new \InvalidArgumentException('Invalid beverage style type.');
            }
        }
    }
}

final readonly class LandingPageViewModel
{
    /** @param list<Alert> $alerts
     *  @param list<Contact> $contacts
     *  @param list<Sponsor> $sponsors
     *  @param list<Archive> $archives
     */
    public function __construct(
        public ContestOverview $contest,
        public bool $loggedIn,
        public ?string $viewerName,
        public WindowStatus $registrationStatus,
        public WindowStatus $entryStatus,
        public WindowStatus $judgeStatus,
        public WindowStatus $dropoffStatus,
        public WindowStatus $shippingStatus,
        public CompetitionLimits $capacity,
        public JudgingProgress $judging,
        public CompetitionLocations $locations,
        public array $alerts,
        public array $contacts,
        public array $sponsors,
        public array $archives,
        public WinnerSummary $winners,
        public HeroPresentation $hero,
        public LandingPageLinks $links,
        public LandingPageCopy $copy,
    );
}
```

`LandingPageCopy` contains explicit string properties used by templates:
`register`, `login`, `logout`, `rules`, `volunteers`, `entryInfo`, `contact`,
`sponsors`, `officials`, `results`, `upcomingMessage`, `openMessage`,
`closedMessage`, `judgeOpenMessage`, `entryLimitMessage`, and
`winnerDelayMessage`.

- [ ] **Step 5: Run model tests and PHPStan**

Run:

```bash
php vendor/bin/phpunit tests/Unit/Domain/LandingPage/Model/LandingPageModelTest.php
composer stan
```

Expected: model tests PASS; PHPStan reports no new errors.

- [ ] **Step 6: Commit**

```bash
git add src/Domain/LandingPage/Model src/Domain/LandingPage/Presentation \
  tests/Unit/Domain/LandingPage/Model
git commit -m "feat: add typed landing page models"
```

---

### Task 4: Implement prepared-statement landing-page reads

**Files:**
- Create: `src/Domain/LandingPage/Repository/LandingPageRepository.php`
- Create: `tests/Integration/LandingPage/LandingPageRepositoryIntegrationTest.php`

**Interfaces:**
- Consumes: `Connection`, trusted table prefix.
- Produces:
  - `contestOverview(): ?ContestOverview`
  - `competitionWindows(): ?CompetitionWindows`
  - `competitionLimits(): CompetitionLimits`
  - `judgingProgress(): JudgingProgress`
  - `locations(): CompetitionLocations`
  - `contacts(): array`
  - `sponsors(): array`
  - `visibleArchives(): array`
  - `winnerSummary(): WinnerSummary`

- [ ] **Step 1: Write integration tests for required and empty data**

Use the existing transactional Integration base pattern. Assert:

```php
$overview = $this->repository->contestOverview();
self::assertNotNull($overview);
self::assertNotSame('', $overview->name);

$windows = $this->repository->competitionWindows();
self::assertNotNull($windows);
self::assertGreaterThan(0, $windows->registrationClosesAt);

self::assertContainsOnlyInstancesOf(Contact::class, $this->repository->contacts());
self::assertContainsOnlyInstancesOf(Sponsor::class, $this->repository->sponsors());
self::assertContainsOnlyInstancesOf(Archive::class, $this->repository->visibleArchives());
```

Delete optional rows inside the test transaction and assert the corresponding
methods return empty lists rather than throwing.

- [ ] **Step 2: Run the integration test to verify RED**

Run:

```bash
docker compose exec -T -e BCOEM_DB_HOST=db web vendor/bin/phpunit tests/Integration/LandingPage/LandingPageRepositoryIntegrationTest.php
```

Expected: FAIL because the repository does not exist.

- [ ] **Step 3: Implement contest, window, limit, and judging reads**

Constructor:

```php
private string $tablePrefix;

public function __construct(
    private Connection $connection,
    ?string $tablePrefix = null,
) {
    $this->tablePrefix = $tablePrefix ?? (string) ($GLOBALS['prefix'] ?? 'baseline_');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $this->tablePrefix)) {
        throw new \InvalidArgumentException('Unsafe table prefix.');
    }
}
```

Use fixed identifier composition and bound values:

```php
public function contestOverview(): ?ContestOverview
{
    $row = $this->connection->selectOne(
        'SELECT contestName, contestHost, contestHostWebsite, contestHostLocation, contestLogo '
        . 'FROM ' . $this->tablePrefix . 'contest_info WHERE id = ?',
        [1],
    );
    if ($row === null) {
        return null;
    }
    return new ContestOverview(
        trim((string) $row['contestName']),
        trim((string) $row['contestHost']),
        $this->nullableString($row['contestHostWebsite']),
        $this->nullableString($row['contestHostLocation']),
        $this->nullableString($row['contestLogo']),
    );
}
```

`competitionWindows()` selects the ten exact date columns from
`contest_info WHERE id = ?`, casts required values to `int`, and maps blank
optional drop-off/shipping pairs to `null`.

`competitionLimits()` selects configured limits from the preferences/limits
table and counts current entries with `COUNT(*)`; use separate prepared
`selectOne()` calls so each method has one clear query purpose.

`judgingProgress()` selects real judging locations only
(`judgingLocType < ?`) and computes start/end facts from returned timestamps.
Winner display preference and delay timestamp come from the existing preference
columns currently consumed by `pub/default.pub.php`.

- [ ] **Step 4: Implement collection reads**

Each collection method returns model objects:

```php
/** @return list<Contact> */
public function contacts(): array
{
    $rows = $this->connection->select(
        'SELECT contactFirstName, contactLastName, contactPosition, contactEmail '
        . 'FROM ' . $this->tablePrefix . 'contacts ORDER BY id',
    );
    return array_map(
        static fn (array $row): Contact => new Contact(
            trim((string) $row['contactFirstName']),
            trim((string) $row['contactLastName']),
            trim((string) $row['contactPosition']),
            trim((string) $row['contactEmail']),
        ),
        $rows,
    );
}
```

`sponsors()` selects enabled rows from `{prefix}sponsors`, ordered by
`sponsorLevel, sponsorName`, and maps `sponsorName`, `sponsorURL`,
`sponsorImage`, `sponsorText`, `sponsorLocation`, and `sponsorLevel`.
`visibleArchives()` selects rows with `archiveDisplayWinners = ?`, binding
`'Y'`, ordered by `archiveSuffix DESC`, and maps `archiveSuffix`,
`archiveWinnerMethod`, and `archiveStyleSet`. Neither method accepts a table
identifier or archive suffix from the request.

For winners, port the read-side SQL currently selected by
`pub/default.pub.php` and its winner includes into `winnerSummary()`. Bind all
style/category/filter values and map rows into typed winner items. Preserve the
configured winner method (overall/category/subcategory) as a backed enum in
`WinnerSummary`; do not return pre-rendered HTML.

- [ ] **Step 5: Run integration tests and SQL safety checks**

Run:

```bash
docker compose exec -T -e BCOEM_DB_HOST=db web vendor/bin/phpunit tests/Integration/LandingPage/LandingPageRepositoryIntegrationTest.php
rg -n "mysqli_|sprintf\\(|SELECT.*\\$|WHERE.*\\$" src/Domain/LandingPage/Repository
```

Expected: tests PASS; safety grep returns no raw mysqli, `sprintf()`, or
value-interpolated SQL.

- [ ] **Step 6: Commit**

```bash
git add src/Domain/LandingPage/Repository \
  tests/Integration/LandingPage/LandingPageRepositoryIntegrationTest.php
git commit -m "feat: add landing page read repository"
```

---

### Task 5: Build the landing-page state machine and copy boundary

**Files:**
- Create: `src/Domain/LandingPage/Service/LandingPageCopyAdapter.php`
- Create: `src/Domain/LandingPage/Service/LandingPageService.php`
- Create: `src/Domain/LandingPage/Resources/en-US.php`
- Create: `src/Domain/LandingPage/Resources/en-GB.php`
- Create: `src/Domain/LandingPage/Resources/es-419.php`
- Create: `tests/Unit/Domain/LandingPage/Service/LandingPageCopyAdapterTest.php`
- Create: `tests/Unit/Domain/LandingPage/Service/LandingPageServiceTest.php`

**Interfaces:**
- Consumes: `LandingPageRepository`, `Identity`, `LandingPageContext`, and a fixed timestamp.
- Produces: `LandingPageService::viewFor(Identity $identity, LandingPageContext $context, int $now): LandingPageViewModel`.

- [ ] **Step 1: Write failing service tests for the parity matrix**

Use a mocked repository. Include separate tests for:

```php
public function test_open_registration_builds_open_cta_and_no_closed_alert(): void;
public function test_upcoming_registration_builds_upcoming_alert(): void;
public function test_closed_registration_with_open_judge_window_builds_judge_cta(): void;
public function test_started_judging_forces_registration_and_entry_closed(): void;
public function test_entry_limit_builds_capacity_alert(): void;
public function test_missing_optional_windows_are_effectively_open(): void;
public function test_hidden_sponsors_and_empty_contacts_produce_empty_regions(): void;
public function test_winner_delay_hides_winner_rows_until_release(): void;
public function test_authenticated_viewer_gets_account_links_and_safe_greeting(): void;
public function test_malformed_hero_preferences_fall_back_to_bundled_image(): void;
```

Representative assertion:

```php
$context = new LandingPageContext('en-US', null, [0, 1]);
$view = $this->service->viewFor(Identity::fromSession([]), $context, 1500);
self::assertSame(WindowStatus::Open, $view->registrationStatus);
self::assertSame('/register', $view->links->register);
self::assertFalse($view->loggedIn);
self::assertNotEmpty($view->hero->imageUrl);
```

- [ ] **Step 2: Run service tests to verify RED**

Run:

```bash
php vendor/bin/phpunit tests/Unit/Domain/LandingPage/Service
```

Expected: FAIL because service and copy adapter do not exist.

- [ ] **Step 3: Extract the supported landing-page copy catalogs**

Create catalog files that return arrays with these exact keys:
`register`, `login`, `logout`, `rules`, `volunteers`, `entry_info`, `contact`,
`sponsors`, `officials`, `results`, `upcoming_message`, `open_message`,
`closed_message`, `judge_open_message`, `entry_limit_message`, and
`winner_delay_message`.

Copy the corresponding values from `lang/en/en-US.lang.php`,
`lang/en/en-GB.lang.php`, and `lang/es/es-419.lang.php`. Convert dynamic legacy
strings into `sprintf()` templates owned by the catalog, such as
`The limit of %d entries has been reached. No further entries will be accepted.`
The catalogs return data only; they do not execute legacy language files.

- [ ] **Step 4: Implement LandingPageCopyAdapter**

Select only a fixed catalog filename and return the typed object:

```php
public function forLocale(string $locale): LandingPageCopy
{
    $catalogFile = match ($locale) {
        'en-GB' => 'en-GB.php',
        'es-419' => 'es-419.php',
        default => 'en-US.php',
    };
    /** @var array<string, string> $catalog */
    $catalog = require dirname(__DIR__) . '/Resources/' . $catalogFile;

    return new LandingPageCopy(
        register: $catalog['register'],
        login: $catalog['login'],
        logout: $catalog['logout'],
        rules: $catalog['rules'],
        volunteers: $catalog['volunteers'],
        entryInfo: $catalog['entry_info'],
        contact: $catalog['contact'],
        sponsors: $catalog['sponsors'],
        officials: $catalog['officials'],
        results: $catalog['results'],
        upcomingMessage: $catalog['upcoming_message'],
        openMessage: $catalog['open_message'],
        closedMessage: $catalog['closed_message'],
        judgeOpenMessage: $catalog['judge_open_message'],
        entryLimitMessage: $catalog['entry_limit_message'],
        winnerDelayMessage: $catalog['winner_delay_message'],
    );
}
```

- [ ] **Step 5: Implement LandingPageService**

Core flow:

```php
public function viewFor(Identity $identity, LandingPageContext $context, int $now): LandingPageViewModel
{
    $contest = $this->repository->contestOverview()
        ?? throw new \RuntimeException('Contest overview is not configured.');
    $windows = $this->repository->competitionWindows()
        ?? throw new \RuntimeException('Competition windows are not configured.');
    $judging = $this->repository->judgingProgress();

    $registration = $this->status($windows->registrationOpensAt, $windows->registrationClosesAt, $now);
    $entry = $this->status($windows->entryOpensAt, $windows->entryClosesAt, $now);
    $judge = $this->status($windows->judgeOpensAt, $windows->judgeClosesAt, $now);
    $dropoff = $this->optionalStatus($windows->dropoffOpensAt, $windows->dropoffClosesAt, $now);
    $shipping = $this->optionalStatus($windows->shippingOpensAt, $windows->shippingClosesAt, $now);

    if ($judging->started) {
        $registration = WindowStatus::Closed;
        $entry = WindowStatus::Closed;
    }

    $copy = $this->copy->forLocale($context->locale);

    return new LandingPageViewModel(
        contest: $contest,
        loggedIn: $identity->loggedIn,
        viewerName: $identity->loggedIn ? ($context->viewerName ?? $identity->username) : null,
        registrationStatus: $registration,
        entryStatus: $entry,
        judgeStatus: $judge,
        dropoffStatus: $dropoff,
        shippingStatus: $shipping,
        capacity: $this->repository->competitionLimits(),
        judging: $judging,
        locations: $this->repository->locations(),
        alerts: $this->buildAlerts($registration, $entry, $judge, $judging),
        contacts: $this->repository->contacts(),
        sponsors: $this->repository->sponsors(),
        archives: $this->repository->visibleArchives(),
        winners: $this->visibleWinners($judging, $now),
        hero: $this->heroFor($contest, $context->beverageStyleTypes),
        links: $this->linksFor($contest),
        copy: $copy,
    );
}
```

`optionalStatus(null, null, $now)` returns `WindowStatus::Open`, matching
legacy's effective default. Mixed null/non-null pairs throw a configuration
exception. `viewerName()` reads and trims the optional first name once at the
application boundary and falls back to the identity username.

- [ ] **Step 6: Run service tests**

Run:

```bash
php vendor/bin/phpunit tests/Unit/Domain/LandingPage/Service
```

Expected: all parity-state and copy-adapter tests PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Domain/LandingPage/Service src/Domain/LandingPage/Resources \
  tests/Unit/Domain/LandingPage/Service
git commit -m "feat: build landing page state model"
```

---

### Task 6: Render the typed model with Bootstrap 5 templates

**Files:**
- Modify: `src/Kernel/View/LayoutRenderer.php`
- Modify: `templates/layout/nav.php`
- Modify: `templates/layout/head.php`
- Modify: `templates/layout/footer.php`
- Create: `templates/LandingPage/home.php`
- Create: `templates/LandingPage/partials/alerts.php`
- Create: `templates/LandingPage/partials/registration.php`
- Create: `templates/LandingPage/partials/at-a-glance.php`
- Create: `templates/LandingPage/partials/contacts.php`
- Create: `templates/LandingPage/partials/sponsors.php`
- Create: `templates/LandingPage/partials/winners.php`
- Create: `templates/LandingPage/partials/login.php`
- Create: `templates/LandingPage/partials/archives.php`
- Modify: `tests/Unit/Kernel/View/LayoutRendererPublicTest.php`
- Create: `tests/Unit/Kernel/LandingPageTemplateTest.php`

**Interfaces:**
- Produces:
  - `LayoutRenderer::landing(LandingPageViewModel $view, string $templatePath): string`
- Consumes only `LandingPageViewModel`; templates do not read session/global state.

- [ ] **Step 1: Write failing layout and template tests**

Build a fixture view model and assert:

```php
$html = $renderer->landing($view, $template);
self::assertStringContainsString('<main id="main-content"', $html);
self::assertStringContainsString('data-bs-toggle="collapse"', $html);
self::assertStringContainsString('data-bs-toggle="offcanvas"', $html);
self::assertStringContainsString('name="loginUsername"', $html);
self::assertStringContainsString('name="loginPassword"', $html);
self::assertStringContainsString('action="/includes/process.inc.php?section=login&amp;action=login"', $html);
self::assertStringNotContainsString('data-toggle=', $html);
self::assertStringNotContainsString('navbar-default', $html);
self::assertStringNotContainsString('<script>alert(', $html);
```

Add a filesystem template audit asserting no LandingPage template contains
`$_SESSION`, `$GLOBALS`, `mysqli_`, `require`, or `include` other than
`home.php` including its fixed partial paths.

- [ ] **Step 2: Run tests to verify RED**

Run:

```bash
php vendor/bin/phpunit tests/Unit/Kernel/View/LayoutRendererPublicTest.php tests/Unit/Kernel/LandingPageTemplateTest.php
```

Expected: FAIL because `landing()` and templates do not exist.

- [ ] **Step 3: Add the landing layout entry point**

Implement:

```php
public function landing(LandingPageViewModel $view, string $templatePath): string
{
    return $this->wrapLanding(
        $view,
        $this->renderTemplate($templatePath, ['view' => $view]),
    );
}
```

`wrapLanding()` passes explicit `$view`, `$title`, `$contestTitle`, `$identity`
presentation, `$isPublic = true`, and `$isLanding = true` to the existing layout
partials. Do not change `public()` behavior used by `/register`.

- [ ] **Step 4: Implement the full-page template**

`home.php` contains semantic region ordering only:

```php
<main id="main-content">
    <?php require __DIR__ . '/partials/alerts.php'; ?>
    <?php require __DIR__ . '/partials/registration.php'; ?>
    <?php require __DIR__ . '/partials/at-a-glance.php'; ?>
    <?php require __DIR__ . '/partials/winners.php'; ?>
    <?php require __DIR__ . '/partials/contacts.php'; ?>
    <?php require __DIR__ . '/partials/sponsors.php'; ?>
    <?php require __DIR__ . '/partials/login.php'; ?>
    <?php require __DIR__ . '/partials/archives.php'; ?>
</main>
```

Each partial checks only typed state. Example alert rendering:

```php
<?php foreach ($view->alerts as $alert): ?>
<div class="alert alert-<?= e($alert->level->value) ?>" role="alert">
    <?= e($alert->message) ?>
    <?php if ($alert->linkUrl !== null && $alert->linkLabel !== null): ?>
    <a class="alert-link" href="<?= e($alert->linkUrl) ?>"><?= e($alert->linkLabel) ?></a>
    <?php endif; ?>
</div>
<?php endforeach; ?>
```

Example login form:

```php
<?php if (!$view->loggedIn): ?>
<section id="login" aria-labelledby="login-heading">
    <h2 id="login-heading"><?= e($view->copy->login) ?></h2>
    <form method="post" action="/includes/process.inc.php?section=login&amp;action=login" class="needs-validation" novalidate>
        <div class="form-floating mb-3">
            <input class="form-control" id="login-user-name" name="loginUsername" type="email" required>
            <label for="login-user-name">Email</label>
            <div class="invalid-feedback d-block">A valid email is required.</div>
        </div>
        <div class="form-floating mb-3">
            <input class="form-control" id="login-password" name="loginPassword" type="password" required>
            <label for="login-password">Password</label>
            <div class="invalid-feedback d-block">Password is required.</div>
        </div>
        <button class="btn btn-primary" type="submit"><?= e($view->copy->login) ?></button>
    </form>
</section>
<?php endif; ?>
```

Use the real legacy field names/action verified in Task 1.

- [ ] **Step 5: Update public chrome without regressing `/register`**

For landing mode, nav links come from `$view->links` and copy comes from
`$view->copy`. Show login/register for anonymous users and account/logout for
authenticated users. Add Bootstrap 5 archive offcanvas and keyboard-operable
controls. External links use:

```php
target="_blank" rel="noopener noreferrer"
```

Keep current pinned CDN integrity/crossorigin attributes. Do not add an
unpinned asset.

- [ ] **Step 6: Run template tests**

Run:

```bash
php vendor/bin/phpunit tests/Unit/Kernel/View/LayoutRendererPublicTest.php tests/Unit/Kernel/LandingPageTemplateTest.php
```

Expected: PASS, including existing `/register` public-layout assertions.

- [ ] **Step 7: Commit**

```bash
git add src/Kernel/View/LayoutRenderer.php templates/layout \
  templates/LandingPage tests/Unit/Kernel/View/LayoutRendererPublicTest.php \
  tests/Unit/Kernel/LandingPageTemplateTest.php
git commit -m "feat: render modern landing page"
```

---

### Task 7: Add the controller and dependency wiring

**Files:**
- Create: `src/Kernel/Controller/LandingPageController.php`
- Create: `tests/Unit/Kernel/Controller/LandingPageControllerTest.php`
- Modify: `src/Kernel/container.php`

**Interfaces:**
- Produces `LandingPageController::show(ServerRequestInterface, ResponseInterface): ResponseInterface`.
- Uses `LandingPageService`, `LayoutRenderer`, `ResponseHelper`, and request `identity`.

- [ ] **Step 1: Write the failing controller test**

Because `LandingPageService` is final, construct a real service with a mocked
repository, following `RegistrationControllerTest`'s established pattern.

```php
$request = (new ServerRequestFactory())
    ->createServerRequest('GET', '/')
    ->withAttribute('identity', Identity::fromSession([]));
$response = $controller->show(
    $request,
    (new ResponseFactory())->createResponse(),
);

self::assertSame(200, $response->getStatusCode());
self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
self::assertStringContainsString('<main id="main-content"', (string) $response->getBody());
```

Add a second test with authenticated `Identity` and assert the greeting/account
links appear.

- [ ] **Step 2: Run to verify RED**

Run:

```bash
php vendor/bin/phpunit tests/Unit/Kernel/Controller/LandingPageControllerTest.php
```

Expected: FAIL because the controller does not exist.

- [ ] **Step 3: Implement the controller**

```php
<?php
declare(strict_types=1);

namespace Bcoem\Kernel\Controller;

use Bcoem\Domain\LandingPage\Service\LandingPageService;
use Bcoem\Domain\LandingPage\Presentation\LandingPageContext;
use Bcoem\Kernel\ResponseHelper;
use Bcoem\Kernel\View\LayoutRenderer;
use Bcoem\Security\Identity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LandingPageController
{
    public function __construct(
        private LandingPageService $service,
        private LayoutRenderer $layout,
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof Identity) {
            $identity = Identity::fromSession($_SESSION);
        }
        $styleTypes = json_decode((string) ($_SESSION['prefsSelectedStyles'] ?? '[]'), true);
        $context = new LandingPageContext(
            locale: (string) ($_SESSION['prefsLanguage'] ?? 'en-US'),
            viewerName: isset($_SESSION['brewerFirstName'])
                ? trim((string) $_SESSION['brewerFirstName'])
                : null,
            beverageStyleTypes: $this->styleTypesFromPreference($styleTypes),
        );
        $view = $this->service->viewFor($identity, $context, time());
        $html = $this->layout->landing(
            $view,
            dirname(__DIR__, 3) . '/templates/LandingPage/home.php',
        );
        return ResponseHelper::html($response, $html);
    }

    /** @param mixed $preference
     *  @return list<int>
     */
    private function styleTypesFromPreference(mixed $preference): array
    {
        if (!is_array($preference)) {
            return [0];
        }
        $types = [0];
        foreach ($preference as $style) {
            if (is_array($style) && isset($style['brewStyleType'])) {
                $type = (int) $style['brewStyleType'];
                if ($type >= 1 && $type <= 3) {
                    $types[] = $type;
                }
            }
        }
        return array_values(array_unique($types));
    }
}
```

- [ ] **Step 4: Register repository, adapter, service, and controller**

Add imports and definitions to `container.php`:

```php
LandingPageRepository::class => static fn (ContainerInterface $container): LandingPageRepository =>
    new LandingPageRepository($container->get(Connection::class)),

LandingPageCopyAdapter::class => static fn (): LandingPageCopyAdapter =>
    new LandingPageCopyAdapter(),

LandingPageService::class => static fn (ContainerInterface $container): LandingPageService =>
    new LandingPageService(
        $container->get(LandingPageRepository::class),
        $container->get(LandingPageCopyAdapter::class),
    ),

LandingPageController::class => static fn (ContainerInterface $container): LandingPageController =>
    new LandingPageController(
        $container->get(LandingPageService::class),
        $container->get(LayoutRenderer::class),
    ),
```

Match `RegistrationOptionsRepository`'s established constructor convention:
`LandingPageRepository` resolves `$GLOBALS['prefix'] ?? 'baseline_'` once in
its constructor, validates it, and stores it privately. No request value may
influence the prefix.

- [ ] **Step 5: Run controller and container tests**

Run:

```bash
php vendor/bin/phpunit tests/Unit/Kernel/Controller/LandingPageControllerTest.php
composer test:unit
```

Expected: controller tests PASS; Unit suite has no new failures.

- [ ] **Step 6: Commit**

```bash
git add src/Kernel/Controller/LandingPageController.php src/Kernel/container.php \
  tests/Unit/Kernel/Controller/LandingPageControllerTest.php
git commit -m "feat: add landing page controller"
```

---

### Task 8: Cut over only the static root route

**Files:**
- Modify: `src/Kernel/app.php`
- Modify: `config/access_policy.php`
- Create: `tests/Unit/Kernel/LandingPageRouteTest.php`

**Interfaces:**
- Produces route name `landing.page` at `GET /`.
- Preserves route name `section` and `LegacyPageHandler` at `GET /index.php`.

- [ ] **Step 1: Write the failing route/policy tests**

```php
public function test_root_uses_the_modern_landing_route(): void
{
    $app = buildApp($this->containerWithLandingController());
    $response = $app->handle(
        (new ServerRequestFactory())->createServerRequest('GET', '/'),
    );
    self::assertSame(200, $response->getStatusCode());
    self::assertStringContainsString('data-modern-landing-page="true"', (string) $response->getBody());
}

public function test_index_php_remains_the_legacy_reference(): void
{
    $routes = $this->routeSignatures(buildApp($this->containerWithLandingController()));
    self::assertSame('landing.page', $routes['GET /']);
    self::assertSame('section', $routes['GET /index.php']);
}
```

Add a policy assertion:

```php
$policy = require ROOT . 'config/access_policy.php';
self::assertSame(Role::Anonymous, $policy['landing.page']);
```

- [ ] **Step 2: Run to verify RED**

Run:

```bash
php vendor/bin/phpunit tests/Unit/Kernel/LandingPageRouteTest.php
```

Expected: FAIL because `/` is still named `section` and mapped to the legacy handler.

- [ ] **Step 3: Add the lazy controller closure and replace only `/`**

In `app.php`, before registering `/`:

```php
$getLandingPageController = function () use ($container): \Bcoem\Kernel\Controller\LandingPageController {
    static $controller;
    return $controller ??= $container->get(\Bcoem\Kernel\Controller\LandingPageController::class);
};
```

Keep:

```php
$app->get('/index.php', new \Bcoem\Legacy\LegacyPageHandler())->setName('section');
```

Replace the root mapping with:

```php
$app->get(
    '/',
    fn ($request, $response) => $getLandingPageController()->show($request, $response),
)->setName('landing.page');
```

Add to the modern route section of `config/access_policy.php`:

```php
'landing.page' => Role::Anonymous,
```

- [ ] **Step 4: Run route tests and FastRoute compilation smoke test**

Run:

```bash
php vendor/bin/phpunit tests/Unit/Kernel/LandingPageRouteTest.php tests/Unit/Kernel/HelloWorldRouteTest.php
```

Expected: PASS. The hello route proves the new static route did not create a
FastRoute compilation conflict.

- [ ] **Step 5: Commit**

```bash
git add src/Kernel/app.php config/access_policy.php \
  tests/Unit/Kernel/LandingPageRouteTest.php
git commit -m "feat: route root to modern landing page"
```

---

### Task 9: Complete dual-path interaction and accessibility coverage

**Files:**
- Modify: `e2e/tests/landing-page-dual-path.spec.ts`
- Create: `e2e/tests/landing-page-accessibility.spec.ts`
- Create: `e2e/helpers/landing-fixtures.ts`
- Modify: `e2e/package.json`
- Modify: `e2e/package-lock.json`

**Interfaces:**
- Compares legacy `/index.php` and modern `/`.
- Exercises interactive parity and WCAG checks.

- [ ] **Step 1: Extend the dual-path contract comparison**

Add:

```ts
test('modern root matches the legacy public contract', async ({ page }) => {
  await page.goto('/index.php');
  const legacy = await captureLandingContract(page);

  await page.goto('/');
  const modern = await captureLandingContract(page);

  expect(modern.title).toBe(legacy.title);
  expect(modern.loginAction).toBe(legacy.loginAction);
  expect(modern.loginFields.sort()).toEqual(legacy.loginFields.sort());
  expect(modern.links).toEqual(legacy.links);
});
```

Normalize only known structural differences. Do not strip user-visible copy,
form actions, field names, link destinations, enabled state, or accessible names.

- [ ] **Step 2: Add interactive tests**

Test:

```ts
await page.goto('/');
await page.getByRole('link', { name: 'Log In', exact: true }).click();
await expect(page.locator('input[name="loginUsername"]')).toBeFocused();

await page.getByRole('button', { name: /past winners/i }).click();
await expect(page.locator('#archive-list')).toHaveClass(/show/);
await page.keyboard.press('Escape');
await expect(page.locator('#archive-list')).not.toHaveClass(/show/);

await page.getByRole('button', { name: /toggle navigation/i }).click();
await expect(page.locator('#public-nav-toggler')).toHaveClass(/show/);

await page.evaluate(() => window.scrollTo(0, 600));
await expect(page.locator('#sticky-home')).toBeVisible();
await page.locator('#sticky-home a').click();
await expect(page).toHaveURL(/#home$/);
```

Add logged-in coverage through `loginAsAdmin(page)` and assert greeting,
account, and logout behavior.

- [ ] **Step 3: Add deterministic state fixtures**

Install `mysql2` as a dev dependency and create a test-only database helper.
The database is exposed on host port 3306 by `docker-compose.yml`; no production
HTTP scenario switch is added.

```ts
import mysql from 'mysql2/promise';

const db = () => mysql.createConnection({
  host: process.env.E2E_DB_HOST ?? '127.0.0.1',
  port: Number(process.env.E2E_DB_PORT ?? 3306),
  user: process.env.E2E_DB_USER ?? 'bcoem',
  password: process.env.E2E_DB_PASSWORD ?? 'bcoem_password',
  database: process.env.E2E_DB_NAME ?? 'bcoem',
});

export async function setLandingWindows(
  registration: [number, number],
  entries: [number, number],
  judges: [number, number],
): Promise<void> {
  const connection = await db();
  try {
    await connection.execute(
      `UPDATE baseline_contest_info
       SET contestRegistrationOpen = ?, contestRegistrationDeadline = ?,
           contestEntryOpen = ?, contestEntryDeadline = ?,
           contestJudgeOpen = ?, contestJudgeDeadline = ?
       WHERE id = 1`,
      [...registration, ...entries, ...judges],
    );
  } finally {
    await connection.end();
  }
}
```

Add equally explicit helpers for capacity preferences, winner display/delay,
optional dates, sponsors, contacts, and archives. Each helper updates only
`baseline_*` test tables with bound values. `resetLandingFixtures()` restores
the known values from `docker/03-e2e-fixtures.sql` and runs in `test.afterEach`;
the suite remains serial so scenarios cannot race.

Cover upcoming, open, judge-only, closed/pre-results, winner-delay, winners
visible, capacity reached, optional dates absent, sponsors off/on, contacts
empty/present, archives empty/present, and authenticated viewer.

- [ ] **Step 4: Add axe-core and accessibility assertions**

Install:

```bash
cd e2e && npm install --save-dev @axe-core/playwright
```

Test:

```ts
import AxeBuilder from '@axe-core/playwright';

test('landing page has no serious or critical accessibility violations', async ({ page }) => {
  await page.goto('/');
  const results = await new AxeBuilder({ page }).analyze();
  expect(
    results.violations.filter(v => ['serious', 'critical'].includes(v.impact ?? '')),
  ).toEqual([]);
});
```

Also assert no duplicate IDs and verify heading levels, form labels, keyboard
offcanvas behavior, and visible focus.

- [ ] **Step 5: Run desktop, mobile, interaction, and accessibility tests**

Run:

```bash
composer e2e -- landing-page-dual-path.spec.ts landing-page-accessibility.spec.ts
```

Expected: all tests PASS for legacy comparison and modern interaction.

- [ ] **Step 6: Commit**

```bash
git add e2e/tests/landing-page-dual-path.spec.ts \
  e2e/tests/landing-page-accessibility.spec.ts \
  e2e/helpers/landing-fixtures.ts e2e/package.json e2e/package-lock.json \
  docker/03-e2e-fixtures.sql
git commit -m "test: verify landing page parity and accessibility"
```

---

### Task 10: Full verification and handoff

**Files:**
- Modify only if verification reveals a landing-page defect.

**Interfaces:**
- Produces verified implementation evidence; no new product behavior.

- [ ] **Step 1: Verify legacy files were not changed**

Run:

```bash
git diff --name-only HEAD~9..HEAD | rg '^(index\\.pub\\.php|pub/|lib/common\\.lib\\.php)'
```

Expected: no output.

- [ ] **Step 2: Verify modern code has no forbidden legacy dependency**

Run:

```bash
rg -n "open_or_closed|common\\.lib\\.php|mysqli_|\\$_SESSION|\\$GLOBALS" \
  src/Domain/LandingPage templates/LandingPage
```

Expected: no output. The copy/service application boundary may read selected
session preferences only if the final implementation keeps that explicit
exception outside templates; document any such service match and ensure no
template match exists.

- [ ] **Step 3: Run focused PHP tests**

Run:

```bash
php vendor/bin/phpunit \
  tests/Unit/Domain/Shared/ValueObject/DateWindowTest.php \
  tests/Unit/Domain/LandingPage \
  tests/Unit/Kernel/Controller/LandingPageControllerTest.php \
  tests/Unit/Kernel/LandingPageTemplateTest.php \
  tests/Unit/Kernel/LandingPageRouteTest.php
```

Expected: PASS with zero failures/errors.

- [ ] **Step 4: Run static analysis and full Unit suite**

Run:

```bash
composer stan
composer test:unit
```

Expected: PHPStan exits 0; Unit suite has no new failures. If the two documented
OpenTelemetry-dependent `SessionMiddlewareTest` failures recur, verify them
against the parent commit before classifying them as pre-existing.

- [ ] **Step 5: Run database tiers**

Run:

```bash
composer test:db
```

Expected: Integration and Approval suites PASS. Reseed after E2E before trusting
Integration counts, per `CLAUDE.md`.

- [ ] **Step 6: Run landing E2E and full CI-equivalent suite**

Run:

```bash
composer e2e -- landing-page-dual-path.spec.ts landing-page-accessibility.spec.ts
composer ci
```

Expected: landing tests and CI suite PASS.

- [ ] **Step 7: Verify rollback shape**

Inspect `src/Kernel/app.php` and confirm rollback requires changing only the
`GET /` handler back to:

```php
$app->get('/', new \Bcoem\Legacy\LegacyPageHandler())->setName('section');
```

No schema or data rollback may be required.

- [ ] **Step 8: Commit verification-only fixes, if any**

If verification required changes, stage only this feature's owned paths:

```bash
git add src/Domain/Shared src/Domain/LandingPage src/Kernel \
  templates/LandingPage templates/layout config/access_policy.php \
  tests/Unit/Domain/Shared tests/Unit/Domain/LandingPage \
  tests/Integration/LandingPage tests/Unit/Kernel \
  e2e/tests/landing-page-dual-path.spec.ts \
  e2e/tests/landing-page-accessibility.spec.ts \
  e2e/helpers/landing-fixtures.ts e2e/package.json e2e/package-lock.json \
  docker/03-e2e-fixtures.sql
git commit -m "fix: close landing page verification gaps"
```

If no changes were required, do not create an empty commit.

---

## Review Checkpoints

1. After Task 2: confirm `DateWindow` semantics and `RegistrationService`
   equivalence before adding landing-page code.
2. After Task 4: review SQL, trusted identifiers, returned types, and legacy
   winner-query parity before presentation work.
3. After Task 6: compare desktop/mobile screenshots before routing `/`.
4. After Task 8: confirm `/index.php` remains unchanged and the root rollback is
   one route edit.
5. After Task 9: review the complete state matrix and accessibility report before
   calling the migration complete.
