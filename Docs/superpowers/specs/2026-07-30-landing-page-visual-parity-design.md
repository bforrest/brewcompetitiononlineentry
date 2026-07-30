# Landing Page Visual Parity — Design

**Date:** 2026-07-30
**Status:** Approved, ready for planning

## Background

The modern landing page (`GET /`, `src/Kernel/Controller/LandingPageController.php` +
`templates/LandingPage/`) was built to replace the legacy homepage (`GET /index.php`,
`pub/default.pub.php` + `pub/*.pub.php`) with structural/data parity — same regions,
same conditional visibility rules, same underlying data. That work is done and merged
(see `Docs/superpowers/plans/2026-07-24-modern-landing-page.md`).

What it did not achieve is **visual** parity. Side-by-side screenshots (same seed
data, both pages fully rendered — see "Evidence" below) show the two pages read as
different products: legacy is a card-based, colorful, badge-driven design; modern is
a plain table-and-list layout in the wrong theme colors.

## Goal

Restyle modern's landing page to match legacy's visual language — hero treatment,
card-based registration status, color palette, section header styling — **while
keeping modern's richer content** (Rules, Entry Info, Competition Officials, and
other sections legacy's current homepage doesn't render at all stay as-is). This is
a styling/template change, not a re-scope of what data modern shows.

## Evidence

Both pages were screenshotted against the same freshly-seeded local DB
(`composer db:reset`), with `.reveal-element { opacity: 1 !important }` forced via
injected CSS to defeat legacy's scroll-triggered reveal animation (which otherwise
makes below-the-fold content register as blank in an automated capture — confirmed
by manually scrolling and observing `.active-element` getting added on real scroll
events; not a bug, just an animation that a naive full-page screenshot fights).

Both pages load the **identical** theme stylesheets:
`/css/common-3.min.css` and `/css/default-3.min.css`, plus the same Bootstrap 5.3.3
CDN build. Every difference below is a template/markup difference, not a missing
stylesheet or a "need to invent new CSS" problem — the classes already exist and are
already used correctly on legacy.

## Changes

### 1. Hero

Legacy: full-bleed hero image ~370px tall, large centered title with a drop shadow,
followed by a full-width black bar containing an intro sentence ("Thank you for your
interest in `<contest>` organized by `<host>`, `<location>`.").

Modern (`LayoutRenderer::wrapLanding()`, `src/Kernel/View/LayoutRenderer.php`): short
hero (~150px), small left-aligned title, host/location shown inline in the hero
itself, no separate intro bar.

**Change:** grow the hero to legacy's height, center the title with the drop-shadow
treatment, and add back the full-width welcome bar below the hero using the existing
`LandingPageCopy`/`ContestOverview` data already available to the view model (contest
name, host name, host location) — no new data plumbing needed, this is template-only.

### 2. Unified card grid (Register + At-a-glance merge)

Legacy renders one grid of 7 colored cards (`pub/at-a-glance.pub.php`, class
`glance-card-bg`), each pairing a status badge with its own CTA button: Entries,
Account Registration, Entry Registration, Judge Registration, Steward Registration,
Entry Drop-Off, Entry Shipping.

Modern currently splits this into two separate, differently-styled regions:
`templates/LandingPage/partials/registration.php` (4 flat buttons + a plain
opens/closes list) and `templates/LandingPage/partials/at-a-glance.php` (a 2-column
`<dl>` definition list: Entries, Paid entries, Entry/Drop-off/Shipping status,
Awards, Awards time).

**Change:** merge both partials into one card grid matching legacy's 7-card style
exactly, plus an 8th card for Awards (location + time) — legacy's homepage doesn't
show Awards as a card at all, but modern's richer content stays per the stated goal.
Each card keeps the badge-plus-CTA pattern legacy uses. The underlying view-model
data (`LandingPageViewModel`, `CompetitionWindows`, `CompetitionLimits`,
`LandingPageActions`) already carries everything both partials need — this is a
template restructuring, not a new data requirement.

### 3. Button color: `btn-primary` → `btn-success`

Legacy's CTA buttons use plain Bootstrap `btn btn-success` (green) — e.g.
`<a href="...?section=register&go=entrant" class="btn btn-success">Register`.
Modern's equivalent buttons (`templates/LandingPage/partials/registration.php:17`,
`templates/LandingPage/partials/login.php:27`) use `btn btn-primary` (blue). Since
both pages load the same theme, this isn't a design choice — it's the wrong existing
utility class. Fix: swap to `btn-success` everywhere on the landing page action
buttons (register/login/judge/steward CTAs, and per-card CTAs in the new grid).

### 4. Section header styling

Legacy's section headers (`Volunteers`, `Contact`) render with a bottom-border rule
under the `<h1>` (from the shared theme CSS, section-header pattern). Modern's
equivalent headers currently don't carry this treatment. Apply the same header
markup/classes legacy uses across all of modern's landing sections (At a glance,
Rules, Entry Info, Volunteers, Contact, Competition Officials, etc.) for visual
consistency.

### 5. Bug fix: Awards description renders literal HTML tags

`templates/LandingPage/partials/at-a-glance.php:25` calls
`e($view->locations->awardsDetails)`. `awardsDetails` is a rich-text admin field that
legitimately contains HTML (`<p>` tags, seen literally in the rendered page instead
of as paragraph breaks). `e()` HTML-escapes it, which is correct for plain-text
fields but wrong here. Fix: render `awardsDetails` unescaped (it should already be
HTMLPurifier-sanitized upstream, matching this codebase's established pattern for
admin-authored rich text — verify at the repository/service layer during
implementation, don't assume). This bug carries into the new Awards card (item 2)
since that's where this field moves.

## Testing impact

`e2e/tests/landing-page-dual-path.spec.ts` has Playwright pixel-snapshot assertions
(`landing-modern-desktop.png` / `landing-modern-mobile.png`, both Darwin and Linux
variants under `e2e/tests/landing-page-dual-path.spec.ts-snapshots/`) that will fail
once these visual changes land — expected, not a regression. Snapshots need
re-baselining, including the Linux ones (same Docker approach used previously:
`mcr.microsoft.com/playwright:v<installed-version>-noble` on an isolated
docker-compose stack matching the installed `@playwright/test` version — see
`AGENTS.md`'s "E2E / Playwright conventions" section for the exact gotchas).

The existing WCAG accessibility suite (`landing-page-accessibility.spec.ts`) and the
dual-path parity assertions that check *content* (not pixels) should keep passing
unchanged, since this work doesn't alter what data renders — only how it looks. Any
that do break are worth a second look before just re-baselining, since they may be
catching a real accessibility regression in the new card markup (e.g. color contrast
on the new badges, focus order in the merged grid).

## Out of scope

- No change to what sections/data the modern page shows (Rules, Entry Info,
  Competition Officials, etc. all stay).
- No change to the legacy page (`/index.php`) itself.
- No change to routing, authorization, or the domain/repository/service layers —
  this is templates + one small escaping fix.
- Legacy's scroll-reveal animation (`.reveal-element`/`.active-element`) is not being
  ported to modern; not part of the stated visual-parity goal and not raised as a
  requirement.
