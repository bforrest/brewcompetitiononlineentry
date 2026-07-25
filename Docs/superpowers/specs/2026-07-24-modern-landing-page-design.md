# Modern Landing Page Parity — Design

**Date:** 2026-07-24
**Status:** Proposed for user review
**Scope:** Replace the rendering of `GET /` with a modern Slim controller and
templates that reproduce the observable visual states and user interactions of
the current no-query-string `index.php` homepage. Keep the legacy implementation
available at `GET /index.php` during migration.

## Goal

Create a modern landing page at `/` that is visually and behaviorally equivalent
to the current public homepage returned by `index.php` with no query string,
without rendering legacy `index.pub.php` or `pub/*.pub.php` files and without
loading `lib/common.lib.php`.

This is a strangler migration, not a redesign. Existing copy, feature flags,
links, date-bound behavior, login behavior, responsive layout, and conditional
content remain the source of truth. Internal implementation changes from
ambient globals and procedural includes to explicit repositories, services,
value objects, and a typed view model.

## Current Request Path

The current no-query request follows this path:

1. Root `index.php` builds and runs the Slim application.
2. `src/Kernel/app.php` maps `GET /` to `LegacyPageHandler`.
3. `LegacyPageHandler` requires `legacy/index.php`.
4. `legacy/index.php` loads the legacy bootstrap and selects `index.pub.php`
   because the request is public rather than an admin request.
5. `index.pub.php` and its included `pub/*.pub.php` and `includes/*.inc.php`
   files assemble the complete page from session state, database result
   resources, global variables, and procedural helper functions.

The parity target is therefore the rendered no-query public homepage, especially:

- `index.pub.php` for the full document, hero, navigation, and script behavior;
- `pub/default.pub.php` for the main landing-page content;
- `pub/alerts.pub.php` for state-dependent alerts;
- `pub/at-a-glance.pub.php` for competition dates and logistics;
- the public navigation, login form, sponsors, contacts, archives, winners, and
  footer fragments included by that path.

`index.legacy.php` is not the direct rendering target for this page; it is the
admin/older fixed-layout path.

## Product Constraints

- Preserve the observable homepage rather than copying its internal structure.
- Do not delete or edit the legacy homepage files in this phase.
- Keep `/index.php` routed through `LegacyPageHandler` as the parity reference
  and temporary fallback.
- Route `/` to the new controller as a static route before the SEF catch-all.
- Do not call `open_or_closed()` or require `lib/common.lib.php` from new code.
- Preserve current authentication and form-processing endpoints; login,
  logout, registration, output downloads, and archive links continue to use
  their existing routes unless separately modernized.
- Use Bootstrap 5 markup and the existing pinned frontend assets. Do not port
  Bootstrap 3-only classes or JavaScript APIs.
- All new state used by templates must be validated or represented by typed
  values before rendering.
- No legacy ambient globals or raw database resources may enter a template.

## Approaches Considered

### 1. Include the existing public fragments from a modern controller

This is the smallest initial diff but is rejected. The fragments rely on the
legacy bootstrap, mutable globals, session variables, result resources, and
procedural helpers. Reusing them would make the new controller modern in route
name only and would preserve the dependency this work is intended to remove.

### 2. Modern controller, explicit read model, and parity templates

This is the selected approach. A landing-page query service builds one immutable
view model from repository data, identity/session input, feature preferences,
and typed date-window calculations. A modern template renders that model inside
the existing public layout infrastructure.

It requires more deliberate mapping than direct includes, but it creates a
testable boundary and allows legacy rendering to remain untouched for
side-by-side comparison.

### 3. Embed or proxy the legacy page and progressively replace regions

This offers immediate screenshot similarity but is rejected. It would put two
rendering lifecycles on one page, complicate forms, focus, accessibility,
navigation, analytics, and error handling, and provide weak proof that the new
implementation is independent.

## Architecture

### Route and controller

Change only the root route:

```php
$app->get('/', fn ($request, $response) =>
    $getLandingPageController()->show($request, $response)
)->setName('landing.page');
```

Keep the existing explicit `/index.php` route mapped to `LegacyPageHandler`.
Register `landing.page` in `config/access_policy.php` with anonymous access.
The route must remain before the SEF catch-all because route-registration order
is load-bearing in this application.

`LandingPageController` has one responsibility: obtain the request identity,
ask `LandingPageService` for the view model, render it, and return HTML through
the existing response helper. It contains no SQL and no presentation-state
branching.

### Competition landing-page service

`LandingPageService` coordinates:

- contest and host presentation data;
- current authenticated identity and greeting state;
- site preferences and feature flags;
- registration, entry, judge, drop-off, shipping, and payment window states;
- competition limits and remaining-capacity warnings;
- judging progress and winner-display eligibility;
- dates, locations, contacts, sponsors, archives, and current winners;
- the hero-image candidate set;
- existing destination URLs and output/download URLs.

The service returns a single `LandingPageViewModel`. Templates must not call
repositories, inspect `$_SESSION`, calculate time windows, or build SQL.

### Repository

`LandingPageRepository` is a read-only repository over the existing schema. It
returns typed arrays or small data-transfer objects rather than `mysqli_result`
resources. It centralizes the queries currently spread across the landing-page
include graph.

The repository must use the existing `Connection` abstraction. Every new query
with variable values uses prepared statements. Dynamic identifiers such as
prefixed table names cannot be bound; they must come only from trusted
configuration and be validated against the repository's fixed identifier set.
The migration must not reproduce raw `sprintf()` value interpolation.

Repository methods should be organized by independently testable read:

```php
contestOverview(): ?ContestOverview
competitionWindows(): ?CompetitionWindows
competitionLimits(): CompetitionLimits
judgingProgress(): JudgingProgress
locations(): CompetitionLocations
contacts(): array
sponsors(): array
visibleArchives(): array
winnerSummary(): WinnerSummary
```

The final names may follow established repository conventions, but the
separation of responsibilities is required. A single untyped `homepageData()`
array is not acceptable.

### Date-window extraction

Introduce a shared domain value object and enum:

```php
enum WindowStatus
{
    case Upcoming;
    case Open;
    case Closed;
}

final readonly class DateWindow
{
    public function __construct(
        private int $opensAt,
        private int $closesAt,
    );

    public function statusAt(int $timestamp): WindowStatus;
    public function isOpenAt(int $timestamp): bool;
}
```

It must preserve the exact legacy boundaries:

| Condition | Status |
|---|---|
| `now < opensAt` | `Upcoming` |
| `opensAt <= now <= closesAt` | `Open` |
| `now > closesAt` | `Closed` |

The landing-page service uses `DateWindow` for registration, entry, judge,
drop-off, shipping, and payment windows. Missing optional drop-off or shipping
windows retain the current effective behavior rather than being silently
treated as closed.

The existing rule that registration-related activity is force-closed once a
judging session has begun remains application policy in the relevant service.
It must not be embedded in `DateWindow`.

`RegistrationService` should be migrated to the same `DateWindow` abstraction
in this phase so the new type has one source of behavior and the modern service
can remove its on-demand load of `common.lib.php`. The legacy
`open_or_closed()` function remains for legacy callers.

### View model

The view model represents decisions already made, not raw database state:

```php
final readonly class LandingPageViewModel
{
    public function __construct(
        public ContestPresentation $contest,
        public ViewerPresentation $viewer,
        public WindowStates $windows,
        public CapacityState $capacity,
        public JudgingPresentation $judging,
        public CompetitionLocations $locations,
        public array $alerts,
        public array $contacts,
        public array $sponsors,
        public array $archives,
        public WinnerPresentation $winners,
        public HeroPresentation $hero,
        public LandingPageLinks $links,
    );
}
```

Each collection has a documented item type. Optional content uses nullable
typed properties or explicit empty collections. Templates do not use magic
sentinels such as `"default"`, numeric window-state codes, or loosely shaped
session arrays.

### Rendering

Extend the modern public layout rather than building another standalone
document renderer. The public layout must accept explicit viewer and landing
presentation data needed for:

- contest title and host salutation;
- logged-in greeting;
- responsive public navigation;
- home/section anchors;
- login controls;
- archive offcanvas;
- sticky return-to-top control;
- hero background;
- footer and pinned scripts.

The layout remains reusable: homepage-only regions belong in
`templates/LandingPage/`, not in generic layout partials.

Suggested template decomposition:

| Template | Responsibility |
|---|---|
| `templates/LandingPage/home.php` | Orders landing-page regions; contains no business decisions. |
| `templates/LandingPage/partials/alerts.php` | Renders the precomputed alert list. |
| `templates/LandingPage/partials/registration.php` | Registration/entry CTA state and account guidance. |
| `templates/LandingPage/partials/at-a-glance.php` | Dates, drop-off, shipping, and judging logistics. |
| `templates/LandingPage/partials/contacts.php` | Competition officials. |
| `templates/LandingPage/partials/sponsors.php` | Sponsor content and links. |
| `templates/LandingPage/partials/winners.php` | BOS, category/subcategory winners, and delayed-results state. |
| `templates/LandingPage/partials/login.php` | Existing login fields, validation markup, and action URL. |
| `templates/LandingPage/partials/archives.php` | Past-winner links/offcanvas content. |

All dynamic output is escaped with `templates/helpers.php`. URLs derived from
data are validated and encoded at their construction boundary. Templates may
select CSS classes from typed presentation variants, but may not recalculate
domain state.

## Observable Parity Matrix

Parity is defined by user-visible state, not byte-identical HTML. At minimum,
the implementation must cover:

| Scenario | Required behavior |
|---|---|
| Anonymous, registration upcoming | Upcoming-registration/entry messaging and appropriate CTAs are shown. |
| Anonymous, registration and entries open | Registration/login actions, date information, and capacity state match legacy. |
| Registration closed, judge window open | Judge-registration opportunity remains visible where legacy shows it. |
| Registration and entries closed, judging not complete | Closed-state messaging and winner-delay behavior match legacy. |
| Judging complete, winners hidden or delayed | Delay/no-results presentation matches legacy. |
| Judging complete, winners visible | BOS and configured category/subcategory winner presentation and downloads are available. |
| Entry or paid-entry limit reached | Registration state and warning/closure messaging match legacy. |
| Near entry limit | The existing near-capacity warning appears. |
| Optional drop-off/shipping dates absent | Current default behavior is preserved without warnings or undefined values. |
| Logged-out visitor | Login form, registration actions, validation, and navigation match legacy behavior. |
| Logged-in entrant | Greeting and account-oriented links replace anonymous actions as legacy does. |
| Sponsors disabled or empty | Sponsor region is absent. |
| Sponsors enabled | Sponsor logos/text and sponsor-page links match configured behavior. |
| Contacts absent/present | Officials region is omitted or rendered with the same singular/plural behavior. |
| Archives absent/present | Archive control is hidden or exposes valid past-winner destinations. |
| Mobile viewport | Navigation, offcanvas, cards, forms, and anchors remain usable without horizontal overflow. |

## Interactive Behavior

The following behaviors must remain functional, not merely visually present:

- login form submission to the existing authentication process;
- HTML5/Bootstrap validation feedback for required login fields;
- register, account, entry, judge, contact, sponsor, and archive navigation;
- logout for authenticated viewers;
- responsive navbar collapse;
- archive offcanvas open/close and keyboard focus behavior;
- tooltips where the current page provides explanatory titles;
- same-page anchor navigation and sticky return-to-top;
- winner PDF/HTML download links;
- external host and sponsor links with safe target/relationship attributes;
- loader/hide-loader behavior only where still supported by the shared scripts.

No new mutation endpoint is introduced. Existing POST/GET destinations retain
their authorization and CSRF behavior.

## Hero Selection

The legacy homepage chooses a hero image from beverage-type-compatible
candidates and stores candidate information in session state. The modern page
must preserve the same eligible image set and acceptable randomized visual
behavior, but selection logic moves out of the template.

`HeroPresentation` contains the selected, validated asset URL and alternative
presentation data. The renderer must fall back to a known default asset if
preferences are empty, malformed, or produce no eligible candidate. User data
must never become an arbitrary filesystem path.

## Localization and Copy

This phase does not rewrite the language system. Existing user-visible copy is
the parity source. A small presentation/copy adapter reads the already selected
language resources at the application boundary and returns a typed
`LandingPageCopy` object.

Legacy language variables must not be exposed directly to templates as ambient
globals. Missing required copy is an explicit configuration error handled by
the normal application error middleware; optional copy has documented
fallbacks.

## Error Handling

- If contest overview data is missing, return the existing branded error
  response rather than rendering a partially initialized homepage.
- Optional lists such as sponsors, contacts, archives, and winners degrade to
  empty regions.
- Repository failures propagate to the centralized error handler and are
  logged with the existing request reference ID; database details are never
  rendered.
- Invalid dates are rejected or normalized at the repository/service boundary,
  never compared loosely in a template.
- Invalid external URLs are omitted or rendered as plain text rather than
  emitted into `href`.
- Invalid hero configuration falls back to a safe bundled image.
- An authenticated identity with incomplete optional profile data receives a
  generic greeting rather than an undefined-variable warning.

## Testing Strategy

### Characterization before replacement

Create a legacy homepage fixture matrix before changing `/`. Each fixture
records the database/session state, expected visible regions, actionable links,
and a screenshot from `/index.php`.

Characterization must cover every row in the parity matrix. Do not rely on one
default seeded state; the landing page is primarily a state machine.

### Unit tests

- `DateWindow` boundary tests: before open, exactly open, during, exactly
  closed boundary, and after close.
- Landing-page service tests for window combinations, judging override,
  capacity warnings, optional dates, winner eligibility, sponsor/contact
  visibility, and hero fallback.
- View-model construction tests that reject invalid or incomplete required
  values.
- Controller tests for route response, identity propagation, content type, and
  error propagation.

Use a clock abstraction or pass a fixed timestamp into the service. Tests must
not depend on wall-clock time.

### Integration tests

Repository integration tests verify each read against seeded fixtures,
including empty optional tables. A route integration test confirms `/` resolves
to `landing.page`, `/index.php` remains legacy, and both are anonymously
authorized.

### Dual-path Playwright tests

Add `e2e/tests/landing-page-dual-path.spec.ts`. For each seeded scenario:

1. Load `/index.php` as the legacy reference.
2. Load `/` as the modern page.
3. Compare normalized visible copy, region presence, enabled/disabled actions,
   destinations, form field names/actions, and key accessibility state.
4. Exercise navbar, login validation, offcanvas, anchors, and downloads.
5. Capture desktop and mobile screenshots for review.

Exact DOM equality is not required because Bootstrap 5 markup may differ.
Behavior, copy, destinations, and recognizable visual hierarchy are required.

Playwright must encode URLs with `encodeURIComponent` when constructing
scenario-specific query or fixture-control values.

### Accessibility

Run an automated accessibility scan on the modern page in representative
logged-out, logged-in, open, and closed states. Required baseline:

- semantic landmarks and heading order;
- labeled login controls;
- keyboard-operable navbar and offcanvas;
- visible focus;
- alert semantics that do not rely on color alone;
- meaningful alternative text;
- no duplicate IDs;
- WCAG 2.1 AA color contrast using the supported themes.

## Rollout and Fallback

1. Keep `/index.php` unchanged and available throughout development.
2. Add the modern components and tests while `/` still points to legacy.
3. Verify the complete parity matrix against `/index.php`.
4. Switch only the static `/` route to `LandingPageController`.
5. Retain `/index.php` as the dual-path reference until a separate deletion
   design is approved using caller analysis and production evidence.
6. Treat removal of legacy homepage files and helpers as a separate cleanup
   proposal backed by caller analysis and production evidence.

Rollback is one route-line change: restore `/` to `LegacyPageHandler`. No data
migration or schema rollback is required.

## Files Expected to Change

The implementation plan should refine exact names, but the intended ownership
is:

- Modify `src/Kernel/app.php` — root route.
- Modify `config/access_policy.php` — `landing.page` anonymous policy.
- Modify `src/Kernel/container.php` — lazy landing-page dependencies.
- Create `src/Kernel/Controller/LandingPageController.php`.
- Create a focused competition/landing-page read model under `src/Domain/`.
- Create `DateWindow` and `WindowStatus` in a shared domain location.
- Modify `RegistrationService.php` to use `DateWindow`.
- Modify `src/Kernel/View/LayoutRenderer.php` and public layout partials only as
  required for explicit homepage presentation inputs.
- Create `templates/LandingPage/home.php` and focused partials.
- Create Unit and Integration tests mirroring the new file ownership.
- Create `e2e/tests/landing-page-dual-path.spec.ts`.

No legacy PHP page, include, or caller is deleted in this phase.

## Out of Scope

- Redesigning the homepage or changing product copy.
- Modernizing login, logout, registration, payment, archive, or export
  processing endpoints.
- Replacing the complete language subsystem.
- Rewriting winner calculation logic; this phase consumes a read model of the
  existing outcomes.
- Removing `open_or_closed()` from `lib/common.lib.php`.
- Deleting `index.pub.php`, `pub/default.pub.php`, or related fragments.
- Migrating query-string routes other than the no-query root homepage.
- Broad SQL remediation outside the landing-page reads introduced here.

## Acceptance Criteria

- `GET /` is served by `LandingPageController`, not `LegacyPageHandler`.
- `GET /index.php` still serves the legacy public homepage.
- The modern root route does not require `lib/common.lib.php` or render any
  legacy homepage fragment.
- `DateWindow` preserves all legacy window boundaries and is used by both the
  landing page and `RegistrationService`.
- Every parity-matrix scenario has automated behavioral coverage.
- Logged-out and logged-in interactions work at desktop and mobile widths.
- All data passed to templates is typed, validated, and escaped.
- New routes are authorized deny-by-default through `config/access_policy.php`.
- Unit, Integration, PHPStan, and relevant Playwright suites pass.
- Accessibility scans report no serious or critical violations in the required
  representative states.
- The legacy files remain unchanged and rollback requires no data operation.
