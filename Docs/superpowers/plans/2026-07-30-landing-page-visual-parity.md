# Landing Page Visual Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the modern `/` landing page (`templates/LandingPage/`) to visually match legacy `/index.php`'s card-based, badge-driven look, using the theme CSS classes both pages already load, while keeping every section modern shows that legacy's homepage currently doesn't (Rules, Entry Info, Competition Officials, etc.).

**Architecture:** Template-only + one small escaping fix. No changes to routing, authorization, or the Domain/Repository/Service layers except adding new static copy strings to `LandingPageCopy` (mechanical, following the exact pattern already used for every other copy field). Existing view-model data (`WindowStatus`, `LandingPageActions`, `CompetitionLimits`, `CompetitionLocations`, `LandingPageDates`) already carries everything the new markup needs.

**Tech Stack:** PHP 8.2, plain-PHP templates (no template engine), Bootstrap 5.3.3 (CDN, pinned), the app's own `css/common-3.css`/`css/default-3.css` theme (already loaded by both legacy and modern), PHPUnit 10, Playwright.

## Global Constraints

- Follow `CLAUDE.md` and `AGENTS.md`.
- Design doc: `Docs/superpowers/specs/2026-07-30-landing-page-visual-parity-design.md` — read it before starting; every task below implements a section of it.
- No new CSS. Every class used below (`glance-card-bg`, `glance-header`, `glance-status-pill`, `bg-{success,secondary,danger,primary}-glance-pill`, `text-{success,secondary,danger,primary}-glance-header`, `landing-page-section-header`, `btn-success`) already exists in `css/default-3.css`/`css/common-3.css`, confirmed loaded by both `/index.php` and `/`.
- No change to `src/Domain/LandingPage/Repository/`, `src/Domain/LandingPage/Service/LandingPageService.php`'s business logic, or any route/controller/authorization code. The only Domain-layer touch is adding new `LandingPageCopy` fields (Task 1), mirroring the existing pattern for every other copy field there.
- Do not port legacy's scroll-reveal (`.reveal-element`/`.active-element`) animation — explicitly out of scope per the design doc (documented in its Appendix for later, not built here).
- Escape all dynamic HTML with `e()` (from `templates/helpers.php`) except the one field in Task 2, which has a documented reason not to.
- New/changed markup must stay Bootstrap 5 only — no BS3 vocabulary (see project CLAUDE.md's frontend-parity section).
- Commit after every task.

---

### Task 1: Add new `LandingPageCopy` fields for card titles and badge labels

**Files:**
- Modify: `src/Domain/LandingPage/Presentation/LandingPageCopy.php`
- Modify: `src/Domain/LandingPage/Resources/en-US.php`
- Modify: `src/Domain/LandingPage/Resources/en-GB.php`
- Modify: `src/Domain/LandingPage/Resources/es-419.php`
- Modify: `src/Domain/LandingPage/Service/LandingPageCopyAdapter.php`
- Modify: `src/Domain/LandingPage/Service/LandingPageService.php:406-482` (the `copyForView()` method)
- Test: `tests/Unit/Domain/LandingPage/Service/LandingPageCopyAdapterTest.php`

**Interfaces:**
- Produces: `LandingPageCopy::$judgeRegistrationCardTitle`, `$stewardRegistrationCardTitle`, `$cardStatusLabel`, `$cardInfoLabel` (all `string`) — consumed by Task 5's card-grid partial.

Legacy has separate "Judge Registration" / "Steward Registration" card titles; modern's existing `judgeDates` copy key already combines both ("Judge and steward registration") for the old dates table, so it can't be reused for two distinct card headings. Legacy's Entries card shows a static blue "Status" badge (not tied to any open/closed state — there's nothing to compute a state from); its Awards card shows a static blue "Info" badge. Neither label exists yet.

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/Domain/LandingPage/Service/LandingPageCopyAdapterTest.php`, inside `test_en_us_catalog_preserves_landing_page_copy()` (after the existing `assertSame('%s logo', $copy->hostLogoAlt);` line):

```php
        self::assertSame('Judge Registration', $copy->judgeRegistrationCardTitle);
        self::assertSame('Steward Registration', $copy->stewardRegistrationCardTitle);
        self::assertSame('Status', $copy->cardStatusLabel);
        self::assertSame('Info', $copy->cardInfoLabel);
```

Inside `test_en_gb_catalog_preserves_british_landing_page_copy()` (after the existing `assertSame('%s logo', $copy->hostLogoAlt);` line):

```php
        self::assertSame('Judge Registration', $copy->judgeRegistrationCardTitle);
        self::assertSame('Steward Registration', $copy->stewardRegistrationCardTitle);
        self::assertSame('Status', $copy->cardStatusLabel);
        self::assertSame('Info', $copy->cardInfoLabel);
```

Inside `test_es_419_catalog_preserves_landing_page_copy()` (after the existing `assertSame('Logotipo de %s', $copy->hostLogoAlt);` line):

```php
        self::assertSame('Registro de jueces', $copy->judgeRegistrationCardTitle);
        self::assertSame('Registro de auxiliares', $copy->stewardRegistrationCardTitle);
        self::assertSame('Estado', $copy->cardStatusLabel);
        self::assertSame('Información', $copy->cardInfoLabel);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit --filter LandingPageCopyAdapterTest tests/Unit/Domain/LandingPage/Service/LandingPageCopyAdapterTest.php`
Expected: FAIL — `Error: Undefined property: Bcoem\Domain\LandingPage\Presentation\LandingPageCopy::$judgeRegistrationCardTitle` (or similar for the other three).

- [ ] **Step 3: Add the four new constructor parameters**

In `src/Domain/LandingPage/Presentation/LandingPageCopy.php`, add after the existing `public string $visitSponsor = 'Visit %s',` line (the last parameter):

```php
        public string $judgeRegistrationCardTitle = 'Judge Registration',
        public string $stewardRegistrationCardTitle = 'Steward Registration',
        public string $cardStatusLabel = 'Status',
        public string $cardInfoLabel = 'Info',
```

- [ ] **Step 4: Add the resource catalog keys**

In `src/Domain/LandingPage/Resources/en-US.php`, add after the `'visit_sponsor' => 'Visit %s',` line:

```php
    'judge_registration_card_title' => 'Judge Registration',
    'steward_registration_card_title' => 'Steward Registration',
    'card_status_label' => 'Status',
    'card_info_label' => 'Info',
```

In `src/Domain/LandingPage/Resources/en-GB.php`, add after the same `'visit_sponsor' => 'Visit %s',` line:

```php
    'judge_registration_card_title' => 'Judge Registration',
    'steward_registration_card_title' => 'Steward Registration',
    'card_status_label' => 'Status',
    'card_info_label' => 'Info',
```

In `src/Domain/LandingPage/Resources/es-419.php`, add after the `'visit_sponsor' => 'Visitar %s',` line:

```php
    'judge_registration_card_title' => 'Registro de jueces',
    'steward_registration_card_title' => 'Registro de auxiliares',
    'card_status_label' => 'Estado',
    'card_info_label' => 'Información',
```

- [ ] **Step 5: Wire the adapter**

In `src/Domain/LandingPage/Service/LandingPageCopyAdapter.php`, add after the `visitSponsor: $catalog['visit_sponsor'],` line (inside the `return new LandingPageCopy(...)` call):

```php
            judgeRegistrationCardTitle: $catalog['judge_registration_card_title'],
            stewardRegistrationCardTitle: $catalog['steward_registration_card_title'],
            cardStatusLabel: $catalog['card_status_label'],
            cardInfoLabel: $catalog['card_info_label'],
```

- [ ] **Step 6: Wire the pass-through in `LandingPageService::copyForView()`**

In `src/Domain/LandingPage/Service/LandingPageService.php`, add after the `visitSponsor: $copy->visitSponsor,` line (inside the `return new LandingPageCopy(...)` call at the end of `copyForView()`):

```php
            judgeRegistrationCardTitle: $copy->judgeRegistrationCardTitle,
            stewardRegistrationCardTitle: $copy->stewardRegistrationCardTitle,
            cardStatusLabel: $copy->cardStatusLabel,
            cardInfoLabel: $copy->cardInfoLabel,
```

This step is easy to skip and easy to miss in review: `copyForView()` builds a **new** `LandingPageCopy` explicitly listing every field — any field not listed there silently resets to the constructor default instead of carrying the adapter's locale-specific value through to the rendered page. (This exact mistake is why Task 1 in the previous landing-page work had to be caught in review.)

- [ ] **Step 7: Run test to verify it passes**

Run: `php vendor/bin/phpunit --filter LandingPageCopyAdapterTest tests/Unit/Domain/LandingPage/Service/LandingPageCopyAdapterTest.php`
Expected: PASS, all 4 tests green.

- [ ] **Step 8: Run the full Unit suite to confirm no other test broke**

Run: `php vendor/bin/phpunit --testsuite Unit`
Expected: same pass count as before this task, plus the new assertions. (`composer stan` should also stay clean — run it too.)

- [ ] **Step 9: Commit**

```bash
git add src/Domain/LandingPage/Presentation/LandingPageCopy.php \
  src/Domain/LandingPage/Resources/en-US.php \
  src/Domain/LandingPage/Resources/en-GB.php \
  src/Domain/LandingPage/Resources/es-419.php \
  src/Domain/LandingPage/Service/LandingPageCopyAdapter.php \
  src/Domain/LandingPage/Service/LandingPageService.php \
  tests/Unit/Domain/LandingPage/Service/LandingPageCopyAdapterTest.php
git commit -m "feat: add card-title and badge-label copy for the landing page card grid"
```

---

### Task 2: Fix the Awards HTML-escaping bug

**Files:**
- Modify: `templates/LandingPage/partials/at-a-glance.php:25`
- Test: `tests/Unit/Kernel/Controller/LandingPageControllerTest.php`

**Interfaces:**
- Consumes: `$view->locations->awardsDetails` (`?string`, from `CompetitionLocations`, already available).

`awardsDetails` maps to the legacy `contestAwards` DB column (confirmed in `src/Domain/LandingPage/Repository/LandingPageRepository.php:188`). Legacy's own precedent for this exact column (`pub/entry_info.pub.php:635-645`) renders it as **raw, unescaped HTML** with no purification — an admin-authored rich-text field, trusted as-is. The current modern template wraps it in `e()`, which HTML-escapes it, so a value containing `<p>...</p>` shows the literal tag text on the page instead of an actual paragraph break. Fix: stop escaping this one field, matching legacy's own established (if permissive) trust boundary for it — not introducing a new gap, just not narrowing an existing one differently on the modern side.

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/Kernel/Controller/LandingPageControllerTest.php`, as a new test method (anywhere after an existing test method, e.g. after `test_locale_timezone_and_date_preferences_flow_into_the_typed_view`):

```php
    public function test_awards_details_renders_as_html_not_escaped_tags(): void
    {
        $repository = $this->createMock(LandingPageReadRepository::class);
        $repository->method('contestOverview')->willReturn(
            new ContestOverview('Fixture Competition', 'Fixture Host', null, null, null),
        );
        $now = time();
        $repository->method('competitionWindows')->willReturn(
            new CompetitionWindows($now - 3600, $now + 3600, $now - 3600, $now + 3600, $now - 3600, $now + 3600, null, null, null, null),
        );
        $repository->method('competitionLimits')->willReturn(new CompetitionLimits(5, 3, 100, 80, 90));
        $repository->method('judgingProgress')->willReturn(new JudgingProgress(false, false, false, 0));
        $repository->method('locations')->willReturn(
            new CompetitionLocations(null, null, '<p>Awards details paragraph.</p>', null, null, null),
        );
        $repository->method('contacts')->willReturn([]);
        $repository->method('competitionRules')->willReturn(new CompetitionRules('', null));
        $repository->method('contactMode')->willReturn(ContactMode::Directory);
        $repository->method('sponsors')->willReturn([]);
        $repository->method('visibleArchives')->willReturn([]);
        $repository->method('winnerSummary')->willReturn(new WinnerSummary(WinnerMethod::Overall, '', []));
        $repository->method('bestOfShow')->willReturn(new BestOfShowSummary([]));

        $selector = new class implements HeroImageSelector {
            public function select(array $candidates): string
            {
                return $candidates[0];
            }
        };

        $controller = new LandingPageController(
            new LandingPageService($repository, new LandingPageCopyAdapter(), $selector),
            new LayoutRenderer(),
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/')
            ->withAttribute('identity', Identity::fromSession([]));

        $response = $controller->show($request, (new ResponseFactory())->createResponse());
        $html = (string) $response->getBody();

        self::assertStringContainsString('<p>Awards details paragraph.</p>', $html);
        self::assertStringNotContainsString('&lt;p&gt;', $html);
    }
```

Check the top of the test file's `use` block for `CompetitionLocations` — the existing fixture calls it with 6 positional args (`shippingName, shippingAddress, awardsDetails, awardsLocationName, awardsLocation, awardsAt`); the test above passes `awardsDetails` as the 3rd positional argument, matching the constructor order already confirmed in `src/Domain/LandingPage/Model/CompetitionLocations.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit --filter test_awards_details_renders_as_html_not_escaped_tags tests/Unit/Kernel/Controller/LandingPageControllerTest.php`
Expected: FAIL — the response body contains `&lt;p&gt;Awards details paragraph.&lt;/p&gt;` instead of `<p>Awards details paragraph.</p>`.

- [ ] **Step 3: Fix the template**

In `templates/LandingPage/partials/at-a-glance.php`, line 25, change:

```php
        <dd class="col-sm-8"><?php if ($view->locations->awardsLocationName !== null): ?><strong><?= e($view->locations->awardsLocationName) ?></strong><br><?php endif; ?><?= e($view->locations->awardsLocation) ?><?php if ($view->locations->awardsDetails !== null): ?><br><?= e($view->locations->awardsDetails) ?><?php endif; ?></dd>
```

to:

```php
        <dd class="col-sm-8"><?php if ($view->locations->awardsLocationName !== null): ?><strong><?= e($view->locations->awardsLocationName) ?></strong><br><?php endif; ?><?= e($view->locations->awardsLocation) ?><?php if ($view->locations->awardsDetails !== null): ?><br><?php /* awardsDetails is trusted admin-authored HTML, matching legacy's own unescaped rendering of the same contestAwards column (pub/entry_info.pub.php:645) - not a new trust decision */ ?><?= $view->locations->awardsDetails ?><?php endif; ?></dd>
```

(This line moves into Task 5's card-grid rewrite too — the fix must survive that move. Task 5's steps carry this exact unescaped-echo forward; don't re-add `e()` around it there.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit --filter test_awards_details_renders_as_html_not_escaped_tags tests/Unit/Kernel/Controller/LandingPageControllerTest.php`
Expected: PASS.

- [ ] **Step 5: Run the full Unit suite**

Run: `php vendor/bin/phpunit --testsuite Unit`
Expected: same pass count as before plus the new test, 0 failures.

- [ ] **Step 6: Commit**

```bash
git add templates/LandingPage/partials/at-a-glance.php tests/Unit/Kernel/Controller/LandingPageControllerTest.php
git commit -m "fix: render awards details as HTML instead of escaped tag text"
```

---

### Task 3: Hero — grow height, center title, add welcome bar

**Files:**
- Modify: `src/Kernel/View/LayoutRenderer.php:74-109` (inside `wrapLanding()`)
- Test: `tests/Unit/Kernel/View/LayoutRendererPublicTest.php`
- Test: `tests/Unit/Kernel/Controller/LandingPageControllerTest.php`

**Interfaces:**
- Consumes: `$view->contest->name`, `$view->contest->hostName`, `$view->contest->hostWebsite`, `$view->contest->hostLocation` (all already available on `ContestOverview`, already used elsewhere in this same method).
- No new copy field needed — the welcome sentence is built from the same three pieces of contest data already in scope, following the exact pattern `Docs/superpowers/specs/2026-07-30-landing-page-visual-parity-design.md`'s Change 1 describes. Reuse the existing `LandingPageCopy::$hostedBy` string ("Hosted by") is not appropriate here (that's used lower in the hero, unrelated) — the welcome sentence is a fixed English template string with contest/host name interpolated, matching how legacy phrases it ("Thank you for your interest in `<contest>` organized by `<host>`, `<location>`."). Since this exact sentence doesn't need to vary per-locale for this task (no other landing copy currently localizes plain English boilerplate sentences without a `LandingPageCopy` field — but this one does need one, since the page IS localized). Add it as a new copy field, following Task 1's exact pattern.

- [ ] **Step 1: Add the welcome-message copy field (same mechanical pattern as Task 1)**

In `src/Domain/LandingPage/Presentation/LandingPageCopy.php`, add after the `cardInfoLabel` parameter added in Task 1:

```php
        public string $heroWelcomeMessage = 'Thank you for your interest in %s organized by %s%s.',
```

In `src/Domain/LandingPage/Resources/en-US.php` and `en-GB.php`, add after `'card_info_label' => 'Info',`:

```php
    'hero_welcome_message' => 'Thank you for your interest in %s organized by %s%s.',
```

In `src/Domain/LandingPage/Resources/es-419.php`, add after `'card_info_label' => 'Información',`:

```php
    'hero_welcome_message' => 'Gracias por su interés en %s, organizado por %s%s.',
```

In `src/Domain/LandingPage/Service/LandingPageCopyAdapter.php`, add after `cardInfoLabel: $catalog['card_info_label'],`:

```php
            heroWelcomeMessage: $catalog['hero_welcome_message'],
```

In `src/Domain/LandingPage/Service/LandingPageService.php`'s `copyForView()`, add after `cardInfoLabel: $copy->cardInfoLabel,`:

```php
            heroWelcomeMessage: $copy->heroWelcomeMessage,
```

- [ ] **Step 2: Write the failing test for the welcome bar and hero sizing**

Add to `tests/Unit/Kernel/View/LayoutRendererPublicTest.php` as a new test method (check the existing tests in that file for the exact `LandingPageViewModel`/fixture construction pattern already used there and follow it — the file already builds a full `LandingPageViewModel` fixture for its other landing tests; reuse that same fixture-building approach with `contest->name = 'Fixture Competition'`, `hostName = 'Fixture Host'`, `hostLocation = 'Austin, Texas'`):

```php
    public function test_landing_hero_includes_a_welcome_bar_with_contest_and_host_names(): void
    {
        $view = $this->landingViewModel(); // reuse this file's existing fixture-building helper
        $html = (new LayoutRenderer())->landing($view, self::landingTemplatePath());

        self::assertStringContainsString(
            'Thank you for your interest in Fixture Competition organized by Fixture Host',
            $html,
        );
        self::assertMatchesRegularExpression('/landing-hero[^"]*"[^>]*style="[^"]*pt-[6-9]/', $html);
    }
```

If `LayoutRendererPublicTest.php` doesn't already have a `landingViewModel()`/`landingTemplatePath()` helper (check first — `LandingPageControllerTest.php`'s `controller()` fixture builder is the closest existing template if this file doesn't have its own), adapt the test to build a minimal `LandingPageViewModel` inline instead, matching the constructor this file's other landing tests already use — do not invent a new fixture-construction style not already present in the file.

The second assertion is intentionally loose (checks the hero section's inline style grew from `pt-5` toward a taller padding value) rather than asserting an exact pixel height, since the height comes from the same Bootstrap spacing-utility approach already used (`pt-5 pb-4` today) — Step 3 below defines the exact replacement classes to assert against once written; tighten this regex to match those exact classes after Step 3.

- [ ] **Step 3: Run test to verify it fails**

Run: `php vendor/bin/phpunit --filter test_landing_hero_includes_a_welcome_bar tests/Unit/Kernel/View/LayoutRendererPublicTest.php`
Expected: FAIL — no welcome bar text present.

- [ ] **Step 4: Rewrite the hero + add the welcome bar**

In `src/Kernel/View/LayoutRenderer.php`, in `wrapLanding()`, change the variable-preparation block (currently lines 74-92) to add the welcome message:

```php
        $contestTitleHtml = e($contestTitle);
        $heroImageUrl = e($view->hero->imageUrl);
        $heroHeading = e($view->hero->heading);
        $heroSubheading = e($view->hero->subheading);
        $hostNameText = $view->contest->hostName;
        $hostName = e($hostNameText);
        $hostPresentation = $view->contest->hostWebsite === null
            ? $hostName
            : '<a class="link-light" href="' . e($view->contest->hostWebsite) . '" target="_blank" rel="noopener noreferrer">' . $hostName . '</a>';
        $hostLocation = $view->contest->hostLocation === null
            ? ''
            : ' <span class="text-light">&mdash; ' . e($view->contest->hostLocation) . '</span>';
        $hostLogo = $view->contest->logoPath === null
            ? ''
            : '<img class="img-fluid mb-3" src="' . e($view->contest->logoPath) . '" alt="'
                . e(sprintf($view->copy->hostLogoAlt, $hostNameText)) . '">';
        $locale = e($view->locale);
        $hostedBy = e($view->copy->hostedBy);
        $returnToTop = e($view->copy->returnToTop);
        $welcomeLocationSuffix = $view->contest->hostLocation === null
            ? ''
            : ', ' . e($view->contest->hostLocation);
        $welcomeMessage = sprintf(
            e($view->copy->heroWelcomeMessage),
            $contestTitleHtml,
            $hostName,
            $welcomeLocationSuffix,
        );
```

Then change the returned heredoc's hero `<section>` markup (currently):

```php
<section id="hero" class="landing-hero text-light bg-dark pt-5 pb-4" aria-labelledby="landing-title" style="background-image: linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.75)), url('{$heroImageUrl}')">
    <div class="container-xxl pt-4">
        {$hostLogo}
        <img class="visually-hidden" src="{$heroImageUrl}" alt="" role="presentation">
        <h1 id="landing-title" class="fw-bold animate__animated animate__fadeInDown">{$heroHeading}</h1>
        <p class="lead mb-0">{$heroSubheading}</p>
        <p class="mb-0">{$hostedBy} {$hostPresentation}{$hostLocation}</p>
    </div>
</section>
```

to:

```php
<section id="hero" class="landing-hero text-light bg-dark d-flex align-items-center text-center" style="min-height: 22rem; background-image: linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.75)), url('{$heroImageUrl}')" aria-labelledby="landing-title">
    <div class="container-xxl">
        {$hostLogo}
        <img class="visually-hidden" src="{$heroImageUrl}" alt="" role="presentation">
        <h1 id="landing-title" class="display-4 fw-bold animate__animated animate__fadeInDown" style="text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.85);">{$heroHeading}</h1>
        <p class="lead mb-0">{$heroSubheading}</p>
        <p class="mb-0">{$hostedBy} {$hostPresentation}{$hostLocation}</p>
    </div>
</section>
<div class="bg-black text-light py-4 d-print-none">
    <div class="container-xxl">
        <p class="fs-5 mb-0">{$welcomeMessage}</p>
    </div>
</div>
```

Notes on this change:
- `min-height: 22rem` (~352px) approximates legacy's measured ~370px hero height; `d-flex align-items-center` vertically centers the title block within it, matching legacy's visually-centered look.
- `text-center` on the section + `display-4` + the inline `text-shadow` on the `<h1>` reproduces legacy's large, centered, drop-shadowed title (legacy achieves the drop shadow via its own theme CSS on a class this app's `common-3.css` doesn't define the same way for `.landing-hero h1` — the inline `text-shadow` here is the minimal, self-contained way to match the visual without inventing a new shared CSS rule; if a future pass wants this in the stylesheet instead, that's a mechanical follow-up, not required for this task).
- The new `<div class="bg-black text-light py-4 d-print-none">` block is the welcome bar, styled to match legacy's full-width black intro bar (`d-print-none` matches the same print-hiding convention already used elsewhere in this codebase's landing partials, e.g. `templates/LandingPage/partials/registration.php`'s date-range block does not use it but `pub/at-a-glance.pub.php`'s row wrapper does — apply it here since this is decorative welcome copy, not something print output needs).

- [ ] **Step 5: Run test to verify it passes**

Run: `php vendor/bin/phpunit --filter test_landing_hero_includes_a_welcome_bar tests/Unit/Kernel/View/LayoutRendererPublicTest.php`
Expected: PASS. If the loose regex in Step 2 doesn't match your exact final classes, tighten it now to check for the literal string `min-height: 22rem` in the hero section's style attribute instead, then re-run.

- [ ] **Step 6: Run the full Unit suite**

Run: `php vendor/bin/phpunit --testsuite Unit`
Expected: 0 failures. If `LandingPageControllerTest.php` or `LandingPageTemplateTest.php` had any assertion checking the old `pt-5 pb-4` hero classes or the old heredoc structure verbatim, fix those assertions to match the new markup (search first: `grep -n "pt-5 pb-4\|landing-hero" tests/Unit/Kernel/Controller/LandingPageControllerTest.php tests/Unit/Kernel/LandingPageTemplateTest.php`).

- [ ] **Step 7: Commit**

```bash
git add src/Kernel/View/LayoutRenderer.php \
  src/Domain/LandingPage/Presentation/LandingPageCopy.php \
  src/Domain/LandingPage/Resources/en-US.php \
  src/Domain/LandingPage/Resources/en-GB.php \
  src/Domain/LandingPage/Resources/es-419.php \
  src/Domain/LandingPage/Service/LandingPageCopyAdapter.php \
  src/Domain/LandingPage/Service/LandingPageService.php \
  tests/Unit/Kernel/View/LayoutRendererPublicTest.php
git commit -m "feat: grow landing hero to legacy's height and add the welcome bar"
```

---

### Task 4: Section header visual treatment (Rules, Entry Info, Winners, Contact, Sponsors)

**Files:**
- Modify: `templates/LandingPage/partials/rules.php`
- Modify: `templates/LandingPage/partials/entry-info.php`
- Modify: `templates/LandingPage/partials/winners.php`
- Modify: `templates/LandingPage/partials/contacts.php`
- Modify: `templates/LandingPage/partials/sponsors.php`
- Test: `tests/Unit/Kernel/Controller/LandingPageControllerTest.php`

**Interfaces:**
- No new data. Pure markup change: wrap each section's existing `<h2>` in a `<header class="landing-page-section-header">` for the bottom-border-rule treatment legacy's `Volunteers`/`Contact` headings have, and bump each heading's size/weight with Bootstrap's own `fs-1 fw-bold` utility classes (approximating legacy's `.landing-page-section-header h1` font-size rule, which is scoped to `h1` children — these sections keep `<h2>`, not `<h1>`, deliberately: legacy's homepage has three `<h1>` tags on one page (hero title, "Volunteers", "Contact"), which is not a correctly nested accessible heading outline. Modern's existing single-`<h1>`-per-page structure is more correct and is what `landing-page-accessibility.spec.ts`'s "uses unique IDs and an ordered heading outline" test already checks — keep it. `fs-1 fw-bold` is a self-contained Bootstrap utility-class equivalent that doesn't require an `<h1>`.)

**Note before starting:** `tests/Unit/Kernel/LandingPageTemplateTest.php` is a static-analysis test (it greps template source for `$_SESSION`/`$GLOBALS`/`mysqli_` and checks `home.php`'s exact `require` list) — it does not render anything and is the wrong place for this test. Use `tests/Unit/Kernel/Controller/LandingPageControllerTest.php` instead, which already renders full HTML via its existing `controller()` fixture + `$controller->show($request, $response)` pattern (see any existing test in that file, e.g. `test_locale_timezone_and_date_preferences_flow_into_the_typed_view`).

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/Kernel/Controller/LandingPageControllerTest.php` as a new test method:

```php
    public function test_rules_and_contact_section_headers_carry_the_bordered_header_treatment(): void
    {
        $response = $this->controller()->show(
            (new ServerRequestFactory())->createServerRequest('GET', '/')->withAttribute('identity', Identity::fromSession([])),
            (new ResponseFactory())->createResponse(),
        );
        $html = (string) $response->getBody();

        self::assertStringContainsString(
            '<header class="landing-page-section-header"><h2 id="rules-heading" class="fs-1 fw-bold">',
            $html,
        );
        self::assertStringContainsString(
            '<header class="landing-page-section-header"><h2 id="contacts-heading" class="fs-1 fw-bold">',
            $html,
        );
    }
```

(This file's existing `controller()` fixture already returns non-empty `competitionRules()` and `contacts()` — confirm both `#rules` and `#contact` sections actually render for this fixture before relying on it; if either is empty/conditionally hidden in the current fixture, adjust the mock's return values for just this test rather than changing the shared fixture.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit --filter test_rules_and_contact_section_headers tests/Unit/Kernel/Controller/LandingPageControllerTest.php`
Expected: FAIL — current markup is `<h2 id="rules-heading">`, no wrapping `<header>`, no `fs-1 fw-bold`.

- [ ] **Step 3: Apply the header wrapper to each of the 5 partials**

In `templates/LandingPage/partials/rules.php`, change:

```php
    <h2 id="rules-heading"><?= e($view->copy->rules) ?></h2>
```

to:

```php
    <header class="landing-page-section-header"><h2 id="rules-heading" class="fs-1 fw-bold"><?= e($view->copy->rules) ?></h2></header>
```

In `templates/LandingPage/partials/entry-info.php`, change:

```php
    <h2 id="entry-info-heading"><?= e($view->copy->entryInfo) ?></h2>
```

to:

```php
    <header class="landing-page-section-header"><h2 id="entry-info-heading" class="fs-1 fw-bold"><?= e($view->copy->entryInfo) ?></h2></header>
```

In `templates/LandingPage/partials/winners.php`, change:

```php
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 id="winners-heading" class="mb-0"><?= e($view->copy->results) ?></h2>
```

to:

```php
    <header class="landing-page-section-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 id="winners-heading" class="fs-1 fw-bold mb-0"><?= e($view->copy->results) ?></h2>
```

(Note: this section's existing `</div>` closing tag two lines below the "PDF"/"HTML" links must become `</header>` to match — check the file, the closing tag currently reads `</div>` right after the `<?php endif; ?>` that closes the results-links conditional.)

In `templates/LandingPage/partials/contacts.php`, change:

```php
    <h2 id="contacts-heading"><?= e($view->copy->officials) ?></h2>
```

to:

```php
    <header class="landing-page-section-header"><h2 id="contacts-heading" class="fs-1 fw-bold"><?= e($view->copy->officials) ?></h2></header>
```

In `templates/LandingPage/partials/sponsors.php`, change:

```php
    <h2 id="sponsors-heading"><?= e($view->copy->sponsors) ?></h2>
```

to:

```php
    <header class="landing-page-section-header"><h2 id="sponsors-heading" class="fs-1 fw-bold"><?= e($view->copy->sponsors) ?></h2></header>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit --filter test_rules_and_contact_section_headers tests/Unit/Kernel/Controller/LandingPageControllerTest.php`
Expected: PASS.

- [ ] **Step 5: Run the full Unit suite**

Run: `php vendor/bin/phpunit --testsuite Unit`
Expected: 0 failures. `LandingPageTemplateTest.php`'s static-analysis checks are unaffected by this task (heading markup changes don't touch `$_SESSION`/`$GLOBALS`/`mysqli_` usage or `home.php`'s `require` list) — Task 5 is the one that changes `home.php`'s requires and has its own explicit step for that file.

- [ ] **Step 6: Commit**

```bash
git add templates/LandingPage/partials/rules.php \
  templates/LandingPage/partials/entry-info.php \
  templates/LandingPage/partials/winners.php \
  templates/LandingPage/partials/contacts.php \
  templates/LandingPage/partials/sponsors.php \
  tests/Unit/Kernel/Controller/LandingPageControllerTest.php
git commit -m "feat: apply legacy's bordered section-header treatment to landing sections"
```

---

### Task 5: Unified card grid — merge Register + At-a-glance into legacy's 8-card layout

**Files:**
- Modify: `templates/LandingPage/partials/at-a-glance.php` (becomes the card-grid partial; keeps its filename and `id="at-a-glance"` section id for continuity with the existing e2e locator and any scroll-anchor use)
- Create: `templates/LandingPage/partials/volunteers.php` (split out of `registration.php`, which currently bundles it)
- Modify: `templates/LandingPage/partials/login.php`
- Modify: `templates/LandingPage/home.php`
- Delete: `templates/LandingPage/partials/registration.php`
- Modify: `tests/Unit/Kernel/Controller/LandingPageControllerTest.php`
- Modify: `tests/Unit/Kernel/View/LayoutRendererPublicTest.php`
- Modify: `tests/Unit/Kernel/LandingPageTemplateTest.php`

**Interfaces:**
- Consumes: `$view->registrationStatus`, `$view->entryStatus`, `$view->judgeStatus`, `$view->dropoffStatus`, `$view->shippingStatus` (all `WindowStatus`), `$view->capacity` (`CompetitionLimits`), `$view->locations` (`CompetitionLocations`), `$view->dates` (`?LandingPageDates`), `$view->actions` (`?LandingPageActions`), `$view->copy` (`LandingPageCopy`, including the four new fields from Task 1) — all already present on `LandingPageViewModel`, none new.
- Produces: each card wrapper carries `data-glance-card="<slug>"` (slugs: `entries`, `account-registration`, `entry-registration`, `judge-registration`, `steward-registration`, `dropoff`, `shipping`, `awards`) — Task 6 depends on these exact slug strings for its e2e locator rewrite.

Legacy's card grid (`pub/at-a-glance.pub.php`) has ~600 lines of procedural branching (live-polling entry counts, judging-progress state, per-role capacity limits, session-array reads). Reproducing that exactly is out of scope — the design doc scopes this as a **visual** restyle using the data modern's Domain layer *already computes* via `WindowStatus`/`LandingPageActions`, not a port of legacy's server-side business logic. Two deliberate simplifications from legacy, both consistent with "visual language parity, not byte-identical logic":
1. **3-color badge mapping** (`Open`→success/green, `Upcoming`→secondary/gray, `Closed`→danger/red) instead of legacy's 2-color collapse (which lumps upcoming and closed together as red) — modern's view model already distinguishes all three states via `WindowStatus`, and the CSS ships all three pill colors for exactly this purpose; collapsing them would be a real information loss modern doesn't have to accept.
2. **No disabled-button state.** Legacy shows a grayed-out disabled button when a registration type isn't open; modern's `LandingPageActions` already decides `null` vs. a real `LandingAction` per item (existing Service-layer eligibility logic) — when the action is `null`, the card simply has no button, which is simpler and avoids inventing a new "disabled but visible" button concept modern's action model doesn't already express.
3. **No live-polling entries counter** (legacy's "Resume Updates" button / 2-minute auto-refresh) — that's a dynamic/JS feature, not part of this visual-parity pass; the Entries card shows a static count, matching modern's current (already static) behavior, just re-skinned.

- [ ] **Step 1: Split Volunteers out of `registration.php` into its own partial**

Create `templates/LandingPage/partials/volunteers.php`:

```php
<?php
declare(strict_types=1);
?>
<section id="volunteers" class="container-xxl py-4" aria-labelledby="volunteers-heading">
    <header class="landing-page-section-header"><h2 id="volunteers-heading" class="fs-1 fw-bold"><?= e($view->copy->volunteers) ?></h2></header>
    <?php if ($view->judgeStatus === \Bcoem\Domain\Shared\ValueObject\WindowStatus::Open): ?>
    <p class="mb-0"><?= e($view->copy->judgeOpenMessage) ?></p>
    <?php elseif ($view->judgeStatus === \Bcoem\Domain\Shared\ValueObject\WindowStatus::Upcoming): ?>
    <p class="mb-0"><?= e($view->copy->judgeUpcomingMessage) ?></p>
    <?php else: ?>
    <p class="mb-0"><?= e($view->copy->closedMessage) ?></p>
    <?php endif; ?>
</section>
```

(This is the exact `<section id="volunteers">` block currently at the bottom of `registration.php`, lines 46-57, with the section-header wrapper from Task 4's pattern applied. The `$view->sections?->volunteers ?? true` conditional that currently wraps this in `registration.php` moves to `home.php` in Step 4 below, matching how every other optional section (`rules`, `entryInfo`, `winners`, `contact`, `sponsors`) is already gated in `home.php` rather than inside its own partial.)

- [ ] **Step 2: Write the failing tests for the card grid**

Add to `tests/Unit/Kernel/Controller/LandingPageControllerTest.php` as new test methods:

```php
    public function test_at_a_glance_renders_eight_status_cards_with_slugs(): void
    {
        $response = $this->controller()->show(
            (new ServerRequestFactory())->createServerRequest('GET', '/')->withAttribute('identity', Identity::fromSession([])),
            (new ResponseFactory())->createResponse(),
        );
        $html = (string) $response->getBody();

        foreach ([
            'entries', 'account-registration', 'entry-registration', 'judge-registration',
            'steward-registration', 'dropoff', 'shipping',
        ] as $slug) {
            self::assertStringContainsString('data-glance-card="' . $slug . '"', $html);
        }
        self::assertStringContainsString('glance-card-bg', $html);
        self::assertStringContainsString('bg-success-glance-pill', $html);
    }

    public function test_open_account_registration_shows_a_green_register_button(): void
    {
        $response = $this->controller()->show(
            (new ServerRequestFactory())->createServerRequest('GET', '/')->withAttribute('identity', Identity::fromSession([])),
            (new ResponseFactory())->createResponse(),
        );
        $html = (string) $response->getBody();

        self::assertStringContainsString(
            'class="btn btn-success" href="/index.php?section=register&amp;go=entrant"',
            $html,
        );
    }
```

The `controller()` fixture already in this file (used by every existing test) sets up windows via `CompetitionWindows($now - 3600, $now + 3600, ...)` for all five window types (registration/entry/judge/dropoff/shipping all currently open) — confirm this before writing the test, since these two new tests rely on that existing "everything open" fixture shape to assert the success/green badge and button. If the fixture's actual field order differs from that assumption, read `CompetitionWindows`'s constructor first and adjust the assertions' expected data accordingly rather than guessing.

- [ ] **Step 3: Run tests to verify they fail**

Run: `php vendor/bin/phpunit --filter "test_at_a_glance_renders_eight_status_cards_with_slugs|test_open_account_registration_shows_a_green_register_button" tests/Unit/Kernel/Controller/LandingPageControllerTest.php`
Expected: FAIL — no `data-glance-card` attributes exist yet, buttons are still `btn-primary`.

- [ ] **Step 4: Rewrite `home.php`'s include structure**

In `templates/LandingPage/home.php`, replace the whole file with:

```php
<?php
declare(strict_types=1);
?>
<main id="main-content" data-modern-landing-page="true">
    <?php require __DIR__ . '/partials/alerts.php'; ?>
    <?php if ($view->sections?->atAGlance ?? true): ?>
    <?php require __DIR__ . '/partials/at-a-glance.php'; ?>
    <?php endif; ?>
    <?php if ($view->sections?->volunteers ?? true): ?>
    <?php require __DIR__ . '/partials/volunteers.php'; ?>
    <?php endif; ?>
    <?php if ($view->sections?->rules ?? true): ?>
    <?php require __DIR__ . '/partials/rules.php'; ?>
    <?php endif; ?>
    <?php if ($view->sections?->entryInfo ?? true): ?>
    <?php require __DIR__ . '/partials/entry-info.php'; ?>
    <?php endif; ?>
    <?php if ($view->sections?->winners ?? true): ?>
    <?php require __DIR__ . '/partials/winners.php'; ?>
    <?php endif; ?>
    <?php if ($view->sections?->contact ?? true): ?>
    <?php require __DIR__ . '/partials/contacts.php'; ?>
    <?php endif; ?>
    <?php if ($view->sections?->sponsors ?? true): ?>
    <?php require __DIR__ . '/partials/sponsors.php'; ?>
    <?php endif; ?>
    <?php require __DIR__ . '/partials/login.php'; ?>
    <?php require __DIR__ . '/partials/archives.php'; ?>
</main>
```

(Only change from the original: `registration.php` is gone; `volunteers.php` is required directly, gated by `$view->sections?->volunteers ?? true` — the exact same conditional that used to live inside `registration.php`, just moved up to match every other section's gating style already used in this file.)

- [ ] **Step 5: Rewrite `at-a-glance.php` as the card grid**

Replace the entire contents of `templates/LandingPage/partials/at-a-glance.php` with:

```php
<?php
declare(strict_types=1);

use Bcoem\Domain\LandingPage\Presentation\LandingAction;
use Bcoem\Domain\Shared\ValueObject\WindowStatus;

/** @return array{0: string, 1: string, 2: string} [colorSuffix, faIcon, label] */
$statusBadge = static function (WindowStatus $status) use ($view): array {
    return match ($status) {
        WindowStatus::Open => ['success', 'fa-circle-check', $view->copy->statusOpen],
        WindowStatus::Upcoming => ['secondary', 'fa-clock', $view->copy->statusUpcoming],
        WindowStatus::Closed => ['danger', 'fa-circle-exclamation', $view->copy->statusClosed],
    };
};

/** Renders one "Open – <date> / Close – <date>" bullet list, matching legacy's card body pattern. */
$dateRangeBody = static function (\Bcoem\Domain\LandingPage\Presentation\LandingPageDateRange $range) use ($view): string {
    $items = '';
    if ($range->opens !== null) {
        $items .= '<li><strong>' . e($view->copy->opens) . '</strong> &ndash; ' . e($range->opens) . '</li>';
    }
    if ($range->closes !== null) {
        $items .= '<li><strong>' . e($view->copy->closes) . '</strong> &ndash; ' . e($range->closes) . '</li>';
    }
    return '<ul class="list-unstyled">' . $items . '</ul>';
};

$renderCard = static function (
    string $slug,
    string $title,
    string $color,
    string $icon,
    string $badgeLabel,
    string $bodyHtml,
    ?LandingAction $action,
) use ($view): void {
    ?>
    <div class="col" data-glance-card="<?= e($slug) ?>">
        <div class="card h-100 glance-card-bg">
            <div class="card-body glance-card-body">
                <h5 class="card-title pt-2 pb-2 glance-header text-<?= e($color) ?>-glance-header"><?= e($title) ?></h5>
                <div class="position-absolute top-0 start-50 translate-middle badge bg-<?= e($color) ?>-glance-pill dark rounded-pill glance-status-pill"><i class="fa <?= e($icon) ?> pe-2"></i><?= e($badgeLabel) ?></div>
                <p class="card-text glance-card-text"><small><?= $bodyHtml ?></small></p>
                <?php if ($action !== null): ?>
                <div class="d-grid"><a class="btn btn-success" href="<?= e($action->url) ?>"<?php if ($action->url === '#login-modal'): ?> data-bs-toggle="modal" data-bs-target="#login-modal"<?php endif; ?>><?= e($action->label) ?></a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
};
?>
<section id="at-a-glance" class="container-xxl py-4" aria-labelledby="glance-heading">
    <header class="landing-page-section-header"><h2 id="glance-heading" class="fs-1 fw-bold"><?= e($view->copy->atAGlance) ?></h2></header>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-3 g-4 justify-content-center">
        <?php
        $entryCountBody = '<ul class="list-unstyled">'
            . '<li><strong>' . e($view->copy->entries) . '</strong> &ndash; ' . e((string) $view->capacity->entryCount)
            . ($view->capacity->entryLimit !== null ? ' / ' . e((string) $view->capacity->entryLimit) : '') . '</li>'
            . '<li><strong>' . e($view->copy->paidEntries) . '</strong> &ndash; ' . e((string) $view->capacity->paidEntryCount)
            . ($view->capacity->paidEntryLimit !== null ? ' / ' . e((string) $view->capacity->paidEntryLimit) : '') . '</li>'
            . '</ul>';
        $renderCard('entries', $view->copy->entries, 'primary', 'fa-circle-info', $view->copy->cardStatusLabel, $entryCountBody, null);

        if ($view->dates !== null) {
            [$color, $icon, $badge] = $statusBadge($view->registrationStatus);
            $renderCard(
                'account-registration',
                $view->copy->registrationDates,
                $color,
                $icon,
                $badge,
                $dateRangeBody($view->dates->registration),
                $view->actions?->account,
            );

            [$color, $icon, $badge] = $statusBadge($view->entryStatus);
            $renderCard(
                'entry-registration',
                $view->copy->entryDates,
                $color,
                $icon,
                $badge,
                $dateRangeBody($view->dates->entries),
                $view->actions?->entry,
            );

            [$color, $icon, $badge] = $statusBadge($view->judgeStatus);
            $renderCard(
                'judge-registration',
                $view->copy->judgeRegistrationCardTitle,
                $color,
                $icon,
                $badge,
                $dateRangeBody($view->dates->judges),
                $view->actions?->judge,
            );

            [$color, $icon, $badge] = $statusBadge($view->judgeStatus);
            $renderCard(
                'steward-registration',
                $view->copy->stewardRegistrationCardTitle,
                $color,
                $icon,
                $badge,
                $dateRangeBody($view->dates->judges),
                $view->actions?->steward,
            );

            if ($view->dates->dropoff->opens !== null || $view->dates->dropoff->closes !== null) {
                [$color, $icon, $badge] = $statusBadge($view->dropoffStatus);
                $renderCard('dropoff', $view->copy->dropoffDates, $color, $icon, $badge, $dateRangeBody($view->dates->dropoff), null);
            }

            if ($view->locations->shippingEnabled && ($view->dates->shipping->opens !== null || $view->dates->shipping->closes !== null)) {
                [$color, $icon, $badge] = $statusBadge($view->shippingStatus);
                $renderCard('shipping', $view->copy->shippingDates, $color, $icon, $badge, $dateRangeBody($view->dates->shipping), null);
            }
        }

        if ($view->locations->awardsLocationName !== null || $view->locations->awardsLocation !== null || $view->locations->awardsDetails !== null) {
            $awardsBody = '<ul class="list-unstyled">';
            if ($view->locations->awardsLocationName !== null) {
                $awardsBody .= '<li><strong>' . e($view->locations->awardsLocationName) . '</strong></li>';
            }
            if ($view->locations->awardsLocation !== null) {
                $awardsBody .= '<li>' . e($view->locations->awardsLocation) . '</li>';
            }
            if ($view->dates?->awards !== null) {
                $awardsBody .= '<li><strong>' . e($view->copy->awardsTime) . '</strong> &ndash; ' . e($view->dates->awards) . '</li>';
            }
            if ($view->locations->awardsDetails !== null) {
                /* awardsDetails is trusted admin-authored HTML, matching legacy's own
                   unescaped rendering of the same contestAwards column
                   (pub/entry_info.pub.php:645) - not a new trust decision. */
                $awardsBody .= '<li>' . $view->locations->awardsDetails . '</li>';
            }
            $awardsBody .= '</ul>';
            $renderCard('awards', $view->copy->awards, 'primary', 'fa-circle-info', $view->copy->cardInfoLabel, $awardsBody, null);
        }
        ?>
    </div>
</section>
```

- [ ] **Step 6: Delete `registration.php`, and fix the login modal's button color**

```bash
rm templates/LandingPage/partials/registration.php
```

Legacy's login modal submit button is `btn btn-lg btn-success` (confirmed in the rendered legacy page's markup), matching every other CTA on this page — modern's is currently `btn btn-primary`, the same wrong-class issue Task 5's cards just fixed. In `templates/LandingPage/partials/login.php`, change:

```php
                    <button class="btn btn-primary" type="submit"><?= e($view->copy->login) ?></button>
```

to:

```php
                    <button class="btn btn-success" type="submit"><?= e($view->copy->login) ?></button>
```

(Leave the "Close" button's `btn btn-secondary` unchanged — legacy's equivalent is also a neutral/secondary color, not part of this fix.)

- [ ] **Step 7: Run the new tests to verify they pass**

Run: `php vendor/bin/phpunit --filter "test_at_a_glance_renders_eight_status_cards_with_slugs|test_open_account_registration_shows_a_green_register_button" tests/Unit/Kernel/Controller/LandingPageControllerTest.php`
Expected: PASS.

- [ ] **Step 8: Fix the two known-broken existing tests**

Two existing test files assert on the exact structures this task just removed. Fix both explicitly (don't rely on discovering them from a failing-test list — these are confirmed break points):

**`tests/Unit/Kernel/View/LayoutRendererPublicTest.php`**, inside `test_landing_renders_typed_model_in_bootstrap_five_chrome()`, replace:

```php
        self::assertStringContainsString('<section id="volunteers"', $html);
        self::assertStringContainsString('Entry status</dt><dd class="col-sm-8">Open</dd>', $html);
        self::assertStringContainsString('Drop-off status</dt><dd class="col-sm-8">Upcoming</dd>', $html);
        self::assertStringContainsString('Shipping status</dt><dd class="col-sm-8">Open</dd>', $html);
```

with:

```php
        self::assertStringContainsString('<section id="volunteers"', $html);
        self::assertStringContainsString('data-glance-card="entry-registration"', $html);
        self::assertStringContainsString('data-glance-card="dropoff"', $html);
        self::assertStringContainsString('data-glance-card="shipping"', $html);
```

This test's fixture (`landingView()`, bottom of the same file) sets `entryStatus: WindowStatus::Open`, `dropoffStatus: WindowStatus::Upcoming`, `shippingStatus: WindowStatus::Open` — if you want to keep asserting the actual badge text (not just that the card exists), use instead:

```php
        self::assertMatchesRegularExpression('#data-glance-card="entry-registration".*?glance-status-pill">[^<]*<i[^>]*></i>Open#s', $html);
        self::assertMatchesRegularExpression('#data-glance-card="dropoff".*?glance-status-pill">[^<]*<i[^>]*></i>Upcoming#s', $html);
        self::assertMatchesRegularExpression('#data-glance-card="shipping".*?glance-status-pill">[^<]*<i[^>]*></i>Open#s', $html);
```

**`tests/Unit/Kernel/LandingPageTemplateTest.php`**, inside `test_landing_templates_have_no_ambient_state_or_dynamic_includes()`, replace:

```php
        $expectedPartials = [
            'alerts.php',
            'registration.php',
            'at-a-glance.php',
            'rules.php',
            'entry-info.php',
            'winners.php',
            'contacts.php',
            'sponsors.php',
            'login.php',
            'archives.php',
        ];
```

with:

```php
        $expectedPartials = [
            'alerts.php',
            'at-a-glance.php',
            'volunteers.php',
            'rules.php',
            'entry-info.php',
            'winners.php',
            'contacts.php',
            'sponsors.php',
            'login.php',
            'archives.php',
        ];
```

The list order here must match the order `require` statements actually appear in `home.php` (Step 4 above) — the test checks each string is present via `assertStringContainsString`, not that they appear in this exact array order, but keep them aligned for readability. The `self::assertSame(10, preg_match_all('/\brequire\b/', $contents))` line directly below stays unchanged — still exactly 10 requires (`registration.php` removed, `volunteers.php` added, net zero change).

- [ ] **Step 9: Run the full Unit suite**

Run: `php vendor/bin/phpunit --testsuite Unit`
Expected: 0 failures. If anything else fails, it's a real gap in Step 8's fix list above — trace it back to what markup changed in this task rather than patching the test blind.

Also run: `composer stan` — expect clean (no new PHPStan errors from the new closures/typed arrays in `at-a-glance.php`).

- [ ] **Step 10: Commit**

```bash
git add templates/LandingPage/partials/at-a-glance.php \
  templates/LandingPage/partials/volunteers.php \
  templates/LandingPage/partials/login.php \
  templates/LandingPage/home.php \
  tests/Unit/Kernel/Controller/LandingPageControllerTest.php \
  tests/Unit/Kernel/View/LayoutRendererPublicTest.php \
  tests/Unit/Kernel/LandingPageTemplateTest.php
git rm templates/LandingPage/partials/registration.php
git commit -m "feat: merge Register and At-a-glance into legacy's unified card grid"
```

---

### Task 6: Update e2e assertions for the new card structure

**Files:**
- Modify: `e2e/tests/landing-page-dual-path.spec.ts`

**Interfaces:**
- Consumes: `data-glance-card="<slug>"` attributes produced by Task 5.

`landingDefinition(page, term)` (lines 145-149) reads `#at-a-glance dt`/`dd` pairs — that structure is gone. Its 4 call sites (checking `'Entry status'`, `'Drop-off status'`, `'Shipping status'` text) need to read from the new cards instead.

- [ ] **Step 1: Replace the `landingDefinition` helper**

In `e2e/tests/landing-page-dual-path.spec.ts`, replace:

```ts
function landingDefinition(page: Page, term: string) {
  return page.locator('#at-a-glance dt')
    .filter({ hasText: new RegExp(`^${term}$`, 'i') })
    .locator('xpath=following-sibling::dd[1]');
}
```

with:

```ts
function landingGlanceCardBadge(page: Page, slug: string) {
  return page.locator(`[data-glance-card="${slug}"] .glance-status-pill`);
}
```

- [ ] **Step 2: Update the call sites**

Find and replace each of the 4 call sites (`grep -n "landingDefinition" e2e/tests/landing-page-dual-path.spec.ts` to locate them precisely, since line numbers will have shifted from Task 5's changes elsewhere in the file):

```ts
await expect(landingDefinition(page, 'Entry status')).toHaveText('Open');
```
→
```ts
await expect(landingGlanceCardBadge(page, 'entry-registration')).toContainText('Open');
```

```ts
await expect(landingDefinition(page, 'Entry status')).toHaveText('Closed');
```
→
```ts
await expect(landingGlanceCardBadge(page, 'entry-registration')).toContainText('Closed');
```

```ts
await expect(landingDefinition(page, 'Drop-off status')).toHaveText('Open');
```
→
```ts
await expect(landingGlanceCardBadge(page, 'dropoff')).toContainText('Open');
```

```ts
await expect(landingDefinition(page, 'Shipping status')).toHaveText('Open');
```
→
```ts
await expect(landingGlanceCardBadge(page, 'shipping')).toContainText('Open');
```

(`toContainText` instead of `toHaveText`: the badge also contains an `<i>` icon element before the text, so an exact-text match would fail even though the visible label is correct — `toContainText` checks the badge's text content includes "Open"/"Closed", matching what a user actually reads.)

- [ ] **Step 3: Check for any other now-stale assertions in this spec file**

Run: `grep -n "registration-heading\|#registration\b" e2e/tests/landing-page-dual-path.spec.ts`

If any test references the old `#registration` section id or `#registration-heading` (the standalone "Register" heading removed in Task 5), update or remove that assertion — the section no longer exists as a separate element; equivalent content now lives inside the card grid.

- [ ] **Step 4: Bring up the app stack and run this spec against real data**

```bash
composer db:reset
docker compose up -d --force-recreate web
```

Run:
```bash
cd e2e && E2E_ALLOW_DESTRUCTIVE_FIXTURES=I_UNDERSTAND_THIS_WILL_RESET_A_DISPOSABLE_DATABASE npx playwright test landing-page-dual-path.spec.ts --update-snapshots
```

`--update-snapshots` here is deliberate and expected: this run's job is to get the *content* assertions green and to write fresh pixel baselines reflecting the new visuals in one pass, since the old pixel baselines are certain to fail otherwise (visuals changed on purpose). Watch the text-assertion failures specifically (not pixel-diff failures) — those are the ones that indicate a real bug in this task's markup, not just an expected pixel change.

Expected: all non-pixel assertions pass. Fix any real failures (e.g., a card slug typo, a status text mismatch) before moving on — do not treat this step as "just re-run with --update-snapshots and move on," the point is confirming the *content* is right first.

- [ ] **Step 5: Run the WCAG accessibility suite**

```bash
E2E_ALLOW_DESTRUCTIVE_FIXTURES=I_UNDERSTAND_THIS_WILL_RESET_A_DISPOSABLE_DATABASE npx playwright test landing-page-accessibility.spec.ts
```

Expected: all pass, including "uses unique IDs and an ordered heading outline" (this is exactly the test Task 4's h2-not-h1 decision was made to keep passing) and any color-contrast checks against the new green/gray/red badges (Bootstrap's own `bg-success`/`bg-secondary`/`bg-danger`-equivalent colors from `default-3.css`, already used elsewhere on this same page's alert banners — should already meet contrast, but confirm rather than assume).

If anything fails here, don't just adjust the test to match — read the failure. This is exactly the class of regression the design doc's "Testing impact" section flagged as worth a second look before dismissing as "just a pixel change."

- [ ] **Step 6: Commit**

```bash
git add e2e/tests/landing-page-dual-path.spec.ts \
  e2e/tests/landing-page-dual-path.spec.ts-snapshots/
git commit -m "test: update landing e2e assertions and snapshots for the card grid"
```

---

### Task 7: Full local verification (PHPStan, all PHPUnit tiers, full e2e suite)

**Files:** none (verification only)

**Interfaces:** none

- [ ] **Step 1: PHPStan**

Run: `composer stan`
Expected: `[OK] No errors`.

- [ ] **Step 2: Unit suite**

Run: `php vendor/bin/phpunit --testsuite Unit`
Expected: 0 failures/errors (pre-existing warnings from missing local PHP extensions, e.g. OpenTelemetry, are expected and unrelated — confirm by checking the failure, if any, references `SessionMiddlewareTest` and an `opentelemetry` extension warning specifically before treating it as pre-existing rather than a regression).

- [ ] **Step 3: Reseed and run Integration + Approval tiers**

```bash
composer db:reset
docker compose exec -T -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Integration
docker compose exec -T -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Approval
```

Expected: 0 failures. If `TotalFeesTest` or any other DB-row-counting test fails here, reseed again (`composer db:reset`) before concluding it's a real regression — this suite is known-sensitive to leftover rows from any e2e run against the same DB (documented in this project's `CLAUDE.md`), and Task 6 just ran e2e specs against this exact database.

- [ ] **Step 4: Reseed and run the full landing e2e suite (not just the one spec)**

```bash
composer db:reset
docker compose up -d --force-recreate web
cd e2e && E2E_ALLOW_DESTRUCTIVE_FIXTURES=I_UNDERSTAND_THIS_WILL_RESET_A_DISPOSABLE_DATABASE npx playwright test landing-page-dual-path.spec.ts landing-page-accessibility.spec.ts landing-fixture-safety.spec.ts registration-dual-path.spec.ts
```

(`registration-dual-path.spec.ts` is included because Task 5 removed the standalone `#registration` section that used to exist alongside `#at-a-glance` — confirm nothing in the registration flow itself, which is a separate page at `/register`, assumed anything about the landing page's now-removed section.)

Expected: 0 failures.

- [ ] **Step 5: Fix anything real, re-run, repeat until green**

If a genuine content/logic bug surfaces (not a pixel-snapshot diff, which Task 8 handles), fix it in the relevant template/service file from the earlier tasks, re-run the specific failing test, then re-run this task's full verification from Step 1.

- [ ] **Step 6: Commit any fixes made during this task**

```bash
git add -A
git commit -m "fix: address issues surfaced by full landing-page verification"
```

(Skip this step if Step 5 required no changes.)

---

### Task 8: Regenerate Playwright pixel snapshots (Darwin + Linux)

**Files:**
- Modify: `e2e/tests/landing-page-dual-path.spec.ts-snapshots/landing-modern-desktop-chromium-darwin.png`
- Modify: `e2e/tests/landing-page-dual-path.spec.ts-snapshots/landing-modern-mobile-chromium-darwin.png`
- Modify: `e2e/tests/landing-page-dual-path.spec.ts-snapshots/landing-modern-desktop-chromium-linux.png`
- Modify: `e2e/tests/landing-page-dual-path.spec.ts-snapshots/landing-modern-mobile-chromium-linux.png`

**Interfaces:** none — this task produces binary snapshot files, not code.

Legacy's own snapshots (`landing-legacy-*.png`) are unchanged by this whole plan (legacy isn't touched) and must NOT be regenerated — only the four `landing-modern-*` files.

- [ ] **Step 1: Regenerate the Darwin (local) snapshots**

```bash
composer db:reset
docker compose up -d --force-recreate web
cd e2e && E2E_ALLOW_DESTRUCTIVE_FIXTURES=I_UNDERSTAND_THIS_WILL_RESET_A_DISPOSABLE_DATABASE npx playwright test landing-page-dual-path.spec.ts -g "modern (desktop|mobile)? ?baseline" --update-snapshots
```

Expected: `landing-modern-desktop-chromium-darwin.png` and `landing-modern-mobile-chromium-darwin.png` are rewritten.

- [ ] **Step 2: Check the installed Playwright version, and pull the matching Docker image**

```bash
export PLAYWRIGHT_VERSION=$(node -e "console.log(require('@playwright/test/package.json').version)")
echo "$PLAYWRIGHT_VERSION"
docker pull "mcr.microsoft.com/playwright:v${PLAYWRIGHT_VERSION}-noble"
```

Do not assume this version matches any version mentioned in an old report or comment — `AGENTS.md`'s "E2E / Playwright conventions" section documents why this can drift. Keep the `PLAYWRIGHT_VERSION` shell variable set for the remaining steps in this task (they're written assuming it's still exported in the same shell session).

- [ ] **Step 3: Bring up an isolated stack, pinned to a known project name**

The `-p` flag makes every resulting resource name (network, container names) deterministic, so the following steps can reference them literally instead of needing to be discovered:

```bash
export REPO_ROOT=$(git rev-parse --show-toplevel)
cd "$REPO_ROOT"
docker compose -p landing-visual-parity-snapshots up -d --build web
```

This creates network `landing-visual-parity-snapshots_default`, container `landing-visual-parity-snapshots-web-1`, and (via `web`'s `depends_on`) `landing-visual-parity-snapshots-db-1` and `landing-visual-parity-snapshots-tempo-1`. Confirm before continuing:

```bash
docker ps --filter "name=landing-visual-parity-snapshots" --format "{{.Names}}"
```

Expected: the three container names above are listed. Then generate the Linux snapshots:

```bash
docker run --rm \
  --network landing-visual-parity-snapshots_default \
  -e BASE_URL=http://landing-visual-parity-snapshots-web-1 \
  -e E2E_DB_HOST=landing-visual-parity-snapshots-db-1 \
  -e E2E_DB_PORT=3306 \
  -e E2E_DB_USER=bcoem \
  -e E2E_DB_PASSWORD=bcoem_password \
  -e E2E_DB_NAME=bcoem \
  -e E2E_ALLOW_DESTRUCTIVE_FIXTURES=I_UNDERSTAND_THIS_WILL_RESET_A_DISPOSABLE_DATABASE \
  -v "${REPO_ROOT}:/repo" \
  -w /repo/e2e \
  "mcr.microsoft.com/playwright:v${PLAYWRIGHT_VERSION}-noble" \
  node_modules/.bin/playwright test landing-page-dual-path.spec.ts -g "modern (desktop|mobile)? ?baseline" --update-snapshots
```

Mount the **whole repo root**, not just `e2e/` — `e2e/node_modules` is a symlink (`../../../e2e/node_modules`) back to the main checkout, which only resolves correctly if the container sees the same directory topology as the host (see `AGENTS.md`'s "E2E / Playwright conventions" for the exact failure mode if this is skipped).

Expected: `landing-modern-desktop-chromium-linux.png` and `landing-modern-mobile-chromium-linux.png` are written under `e2e/tests/landing-page-dual-path.spec.ts-snapshots/`.

- [ ] **Step 4: Verify all four snapshots pass together, then tear down**

```bash
docker run --rm \
  --network landing-visual-parity-snapshots_default \
  -e BASE_URL=http://landing-visual-parity-snapshots-web-1 \
  -e E2E_DB_HOST=landing-visual-parity-snapshots-db-1 \
  -e E2E_DB_PORT=3306 \
  -e E2E_DB_USER=bcoem \
  -e E2E_DB_PASSWORD=bcoem_password \
  -e E2E_DB_NAME=bcoem \
  -e E2E_ALLOW_DESTRUCTIVE_FIXTURES=I_UNDERSTAND_THIS_WILL_RESET_A_DISPOSABLE_DATABASE \
  -v "${REPO_ROOT}:/repo" \
  -w /repo/e2e \
  "mcr.microsoft.com/playwright:v${PLAYWRIGHT_VERSION}-noble" \
  node_modules/.bin/playwright test landing-page-dual-path.spec.ts
```

Expected: all tests in the file pass (not just the baseline ones — the full dual-path suite, since a bad snapshot capture can sometimes pass in isolation but fail once run alongside the state-matrix tests in the same file).

Tear down the isolated stack:
```bash
docker compose -p landing-visual-parity-snapshots down -v
```

- [ ] **Step 5: Commit the four regenerated snapshots**

```bash
git add e2e/tests/landing-page-dual-path.spec.ts-snapshots/landing-modern-desktop-chromium-darwin.png \
  e2e/tests/landing-page-dual-path.spec.ts-snapshots/landing-modern-mobile-chromium-darwin.png \
  e2e/tests/landing-page-dual-path.spec.ts-snapshots/landing-modern-desktop-chromium-linux.png \
  e2e/tests/landing-page-dual-path.spec.ts-snapshots/landing-modern-mobile-chromium-linux.png
git commit -m "test: regenerate modern landing page pixel snapshots for the visual parity redesign"
```

---

### Task 9: Final whole-branch verification

**Files:** none (verification only)

**Interfaces:** none

- [ ] **Step 1: Full clean-DB verification, one more time, top to bottom**

```bash
composer stan
php vendor/bin/phpunit --testsuite Unit
composer db:reset
docker compose up -d --force-recreate web
docker compose exec -T -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Integration
docker compose exec -T -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Approval
```

Expected: all clean.

- [ ] **Step 2: Full e2e suite one more time on a fresh DB**

```bash
composer db:reset
docker compose up -d --force-recreate web
cd e2e && E2E_ALLOW_DESTRUCTIVE_FIXTURES=I_UNDERSTAND_THIS_WILL_RESET_A_DISPOSABLE_DATABASE npx playwright test
```

Expected: same pass/skip counts as the pre-existing baseline before this plan started (the known `test.fixme` stub specs still skip; nothing newly skipped or failed).

- [ ] **Step 3: Visual sanity check against legacy, side by side**

Reuse the same technique from the design doc's own "Evidence" section — a throwaway Playwright script (do not commit it) that navigates both `/index.php` and `/`, forces `.reveal-element { opacity: 1 !important }` via `page.addStyleTag()` before screenshotting legacy (so its scroll-reveal animation doesn't produce a false "blank" capture), and saves both full-page screenshots for a manual side-by-side look. Confirm the hero, card grid, and section headers now read as the same visual language before calling this done — this is a subjective check a passing test suite can't fully replace.

- [ ] **Step 4: Push**

```bash
git push origin slim
```

- [ ] **Step 5: Watch CI**

```bash
gh run list --repo bforrest/brewcompetitiononlineentry --branch slim --limit 1
```

Wait for the run to complete (`gh run view <run-id> --repo bforrest/brewcompetitiononlineentry`), confirm green. If it fails, read the actual failure log (`gh run view <run-id> --repo bforrest/brewcompetitiononlineentry --log-failed`) before assuming it's the same class of pre-existing flake documented elsewhere in this repo's CI history — confirm, don't assume.
