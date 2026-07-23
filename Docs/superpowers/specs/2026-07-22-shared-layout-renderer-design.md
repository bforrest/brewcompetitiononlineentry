# Shared Layout Renderer for Modernized Views — Design

## Goal

Give Slim controllers a single, reusable way to render HTML that visually
matches the legacy app, so the next controller/view in the modernization
roadmap doesn't repeat the mistake found this session: Judging
(`templates/Judging/*.php`) and Export (`ExportController::renderExportForm()`)
render real, reachable HTML today with zero connection to the app's actual
stylesheet (`css/common.css`, Bootstrap-based) and use invented CSS class
names (`button-primary`, `judging-controls`, `tables-list`) that don't exist
anywhere in the real CSS. Entry has dead/unused template files with the same
anti-pattern, not yet wired up. This design builds the fix as a reusable
mechanism ("path of least resistance" for every future controller), proven
live by migrating Judging — the most complete Phase 3 domain today — to use
it.

## Background

Legacy pages get their look from one shared shell in `legacy/index.php`,
which emits `<head>` (with the real stylesheet links) and then `require`s
either `index.legacy.php` (admin pages: top nav + sidebar + page-header +
footer) or `index.pub.php` (public pages: top nav + footer, no sidebar).

That chrome is *not* self-contained: `sections/nav.sec.php` and
`admin/sidebar.admin.php` both read a large set of ambient variables
(`$base_url`, `$comp_entry_limit`, `$remaining_entries`,
`$_SESSION['contestLogo']`, etc.) computed earlier in legacy's global
bootstrap chain (`paths.php` → `site/bootstrap.php` → ...). Reusing those
files verbatim would mean re-coupling every modern controller to that
bootstrap chain — the opposite of what this migration is trying to achieve.
This design instead builds a clean, parameterized reimplementation of the
chrome (explicit inputs only, no ambient globals), accepting a one-time
faithful-port cost in exchange for every future controller being fully
decoupled from legacy's global state.

Two chrome variants are needed, not one: `judging.scoresheet.view` requires
`Role::Judge` (`config/access_policy.php:328`), not `Role::Admin` — a judge
using the scoresheet is an authenticated non-admin user and should get the
top-nav-only chrome (`index.pub.php`'s shape), while the three table-management
views require `Role::Admin` and the full sidebar chrome
(`index.legacy.php`'s shape). Building both from day one is what makes
Judging a real proof-of-concept rather than an admin-only special case.

The stylesheet URL itself is simple to replicate correctly: `site/bootstrap.php:411-448`
computes `$css_url = $base_url."css/"`, then (for the non-hosted, non-admin-vs-admin
split relevant here) `$css_common_url = $css_url."common.min.css"` and
`$theme = $css_url.($_SESSION['prefsTheme'] ?? 'default').".min.css"`. This is
a self-contained session-preference lookup, not tangled in the rest of the
global soup, so it's safe to port directly.

## Architecture

A new `src/Kernel/View/LayoutRenderer.php`, registered in the DI container
the same way `ResponseHelper` is available today. Two public methods:

```php
namespace Bcoem\Kernel\View;

final class LayoutRenderer
{
    public function admin(
        Identity $identity,
        string $title,
        string $activeNav,
        string $templatePath,
        array $vars = []
    ): string;

    public function authenticated(
        Identity $identity,
        string $title,
        string $templatePath,
        array $vars = []
    ): string;
}
```

Both return a complete HTML string. Controllers pass that string straight to
`ResponseHelper::html($response, $html)`, replacing today's per-action
`ob_start(); include $path; $html = ob_get_clean();` boilerplate with one
call.

New template partials live under `templates/layout/`:
- `head.php` — doctype, meta tags, stylesheet `<link>`s (theme-resolved per
  above), page `<title>`.
- `nav.php` — top nav: logo/home link, section links appropriate to the
  identity's role, logout link. A trimmed, modern-context version of
  `nav.sec.php` — not the full public marketing nav (registration CTAs,
  entry-limit-aware links), since those only apply to anonymous/public
  visitors, which this renderer doesn't serve.
- `sidebar.php` — admin-only. A static list of admin section links matching
  `admin/sidebar.admin.php`'s current real links (exact link set pulled from
  that file at implementation time, not guessed here) plus the current
  active-section highlight.
- `footer.php` — simple static footer.

None of these four partials read `$_SESSION` or any global directly — every
value they need (identity, title, active nav, theme URLs) is passed in
explicitly by `LayoutRenderer`, which is the only place session/config
values get read.

## Components

| File | Responsibility |
|---|---|
| `src/Kernel/View/LayoutRenderer.php` | Orchestrates rendering: resolves theme URLs, renders the inner template, wraps it with head/nav/(sidebar/)footer, returns the final HTML string. |
| `templates/layout/head.php` | `<head>` block only. |
| `templates/layout/nav.php` | Top nav only. |
| `templates/layout/sidebar.php` | Admin sidebar only (used by `admin()`, not `authenticated()`). |
| `templates/layout/footer.php` | Footer only. |
| `templates/Judging/*.php` (existing 4 files) | Migrated in this same plan: rewritten to use real Bootstrap classes (`btn btn-primary`, not `button-primary`) instead of the invented vocabulary. Content only — no more `<!DOCTYPE>`/`<head>`, since that's now `LayoutRenderer`'s job. |
| `src/Kernel/Controller/JudgingController.php` | Migrated: each HTML-rendering action calls `$this->layout->admin(...)` or `$this->layout->authenticated(...)` instead of its own `ob_start`/`include`. |

## Data Flow

1. A controller action builds its domain data as local variables (as today),
   collects them into an explicit `$vars` array, and calls e.g.
   `$this->layout->admin($identity, 'Judging Tables', 'judging', $templatePath, $vars)`.
2. `LayoutRenderer` renders the inner content:
   `extract($vars); ob_start(); include $templatePath; $content = ob_get_clean();`
3. It resolves the stylesheet URLs per the Background section's formula.
4. It renders `head.php`, `nav.php` (and `sidebar.php` for `admin()`) using
   `$identity` for the logged-in-user bits and `$activeNav` for the current
   section's highlight.
5. It concatenates: head + nav + (sidebar +) page-header (`$title`) +
   `$content` + footer, and returns the full HTML string.

**Behavior change from today:** `JudgingController`'s current actions
`include` their templates directly from the controller method, so template
variables are automatically in scope via same-scope `include`. Once
rendering goes through `LayoutRenderer`, that's a different scope, so
variables must be explicitly collected into `$vars` and `extract()`-ed
inside `LayoutRenderer`. The templates' own "Available variables" docblocks
don't change — only how the controller supplies them.

**Explicit simplification (in scope, stated plainly, not silently
dropped):** legacy's `DEBUG`/`TESTING`-mode unminified-CSS swap and
cache-busting `?t=` query param (`site/bootstrap.php:445-466`) are not
replicated. `LayoutRenderer` always links the `.min.css` files. This is a
dev-convenience detail, not part of "looks like legacy," and skipping it
avoids plumbing `DEBUG`/`TESTING` constants into a class designed to have no
ambient dependencies.

**Asset paths are root-relative** (`/css/common.min.css`), matching the
convention Judging's own existing templates already use for links
(`/judging/tables/create`, no `$base_url` prefix) — no `$base_url`
reconstruction needed, consistent with how Phase 3 routes already ignore
legacy's sub-directory-install support.

## Error Handling

- `Identity $identity` is a required, non-nullable parameter on both
  methods — the type system prevents calling `LayoutRenderer` without one,
  and `AuthorizationMiddleware` already guarantees identity is present by
  the time a gated controller action runs.
- If `$templatePath` doesn't exist, `LayoutRenderer` checks explicitly and
  throws `\RuntimeException` naming the missing path, rather than surfacing
  a bare PHP `include` warning — cheap, and pays off specifically because
  this class will be called by many future controllers.
- An unrecognized `$activeNav` value renders with nothing highlighted —
  graceful degradation, not an error.
- Theme resolution keeps legacy's existing tolerance: an invalid
  `$_SESSION['prefsTheme']` value isn't validated today and won't be
  validated here — not a regression, out of scope to fix.
- A controller that omits a variable its template needs fails the same way
  it does today (undefined-variable warning) — `extract()` doesn't change
  that risk.

## Testing

- **Unit tests for `LayoutRenderer`** against a fixture `Identity` and a
  throwaway fixture template (not real Judging content): stylesheet
  `<link>` reflects theme resolution across a couple of `prefsTheme`
  scenarios; nav shows the identity's username/logout link; sidebar present
  for `admin()` / absent for `authenticated()`; `$title` appears in the
  page-header; inner content appears verbatim; footer present; missing
  template path throws `\RuntimeException`.
- **`JudgingControllerTest` updated** (existing file, not new) to assert
  migrated actions return HTML containing real Bootstrap classes (`btn
  btn-primary`, not `button-primary`) instead of only checking for domain
  data.
- **Manual visual check**: load a migrated Judging admin page in the running
  Docker stack and compare against a legacy admin page. No new
  Playwright/screenshot spec in this plan — the project's existing Judging
  E2E specs are already known-broken for unrelated reasons (stale `/bcoem`
  path hardcode, modal-vs-page login mismatch), and layering visual-regression
  tooling on top isn't warranted for this proof-of-concept.

## Scope

**In scope for the implementation plan that follows this spec:**
- `LayoutRenderer` and the four `templates/layout/*.php` partials.
- Migrating all four `JudgingController` HTML-rendering actions and their
  four templates to the new renderer and real Bootstrap classes.
- Unit tests for `LayoutRenderer`; updated `JudgingControllerTest` assertions.

**Explicitly out of scope (follow-up work, not this plan):**
- Migrating `ExportController::renderExportForm()` or Entry's dead template
  files — fast follow-ups once this pattern is proven, using the now-built
  mechanism.
- `AdminPreferences` — has no HTTP controller yet; nothing to migrate.
- Any new automated visual-regression/screenshot testing.
- Fixing the pre-existing, unrelated Judging E2E spec failures (`/bcoem`
  hardcode, login modal mismatch) documented in `TESTING_HEALTH_DASHBOARD.md`.
- Replicating legacy's `DEBUG`/`TESTING`-mode CSS swap or cache-busting
  query param (see Data Flow's "Explicit simplification").
