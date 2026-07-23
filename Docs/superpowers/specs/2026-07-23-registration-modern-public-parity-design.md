# Modern Registration Public-Page Parity — Design

## Goal

Bring the standard entrant `/register` flow to full visual and interaction
parity with the legacy public registration page while strengthening its modern
architecture. The result must use the public navigation and Bootstrap shell,
show explicit placeholders for contest-backed information, and keep the
controller, form mapping, display-data reads, and registration service
separate and testable.

## Scope

This increment covers the standard entrant variant only:

- Public navigation and a complete Bootstrap public-page shell.
- Contest guidance and registration-state placeholders supplied as explicit
  data, never template globals.
- Account, contact/address, drop-off, supported volunteer opt-ins, waiver,
  inline error, and success-redirect behavior.
- Modern backend support for every field rendered in this variant, with
  legacy-vs-modern persistence equivalence tests extended accordingly.

It deliberately excludes Pro Edition organization fields, dedicated judge and
steward registration, location-preference variants, and admin/quick
registration. Those are later form variants, not conditions added to this
first template.

## Architecture

Add a `Bcoem\Domain\Registration\Form` layer:

- `RegistrationFormData` holds submitted values, field errors, and general
  errors for one render.
- `RegistrationFormOptions` holds all read-side data needed by the page:
  contest information, registration state, country/state/drop-off choices,
  opt-in availability, and public-navigation state.
- `RegistrationFormFactory` maps raw request input plus options to form data.
  It has no database writes or HTTP behavior.
- `RegistrationOptionsRepository` is the sole source of form options. It uses
  `Bcoem\Database\Connection` and prepared statements.

`RegistrationController` reads options, maps requests, delegates writes to
`RegistrationService`, owns session setup and redirects, and renders failures
as HTML. `RegistrationService` gains only the standard-entrant persistence
needed here. It does not acquire template or session responsibilities.

## View composition

Extend `LayoutRenderer` with a public rendering path that supplies the
legacy-equivalent public navigation and real Bootstrap assets without coupling
templates to legacy bootstrap globals. Registration views are content-only
partials:

- account;
- contact and address;
- logistics/drop-off;
- volunteer opt-ins;
- waiver;
- submit and error summary.

The controller passes each partial explicit data. Templates do not read
`$_SESSION`, globals, or database connections. Contest-specific content has
typed placeholders in `RegistrationFormOptions` and is populated by the
options repository.

## Behavior and errors

Successful registration retains the established modern flow: set
`loginUsername`, regenerate the CSRF token, then redirect to `/entries/my`.
Invalid input, closed registration, duplicate email, and CAPTCHA failure all
re-render the form with escaped submitted values and field/general errors;
they no longer return JSON for browser form submissions.

## Verification

- Unit tests cover form-data mapping and error-state rendering.
- Integration tests cover option reads and standard-entrant persistence.
- Controller tests cover success, validation failure, and each domain failure
  response.
- The existing legacy-vs-modern equivalence test expands only for fields added
  by this variant.
- Playwright verifies the modern page's public chrome, Bootstrap grouping, and
  standard entrant journey alongside the legacy page.

## Non-goals

This is not a rewrite of `sections/register.sec.php`, a reuse of legacy
templates, or a migration of every legacy registration condition. It creates
a maintainable base that subsequent, isolated variants can compose onto.

## Deferred legacy bug: waiver choice is ignored

Legacy registration initializes `brewerJudgeWaiver` to `Y` and never reads the
submitted waiver value. The modern path intentionally preserves that behavior
for parity: it also always stores `Y`, even if a browser submits `N` or an
empty value. A future, separately scoped fix should decide the intended waiver
semantics, make the choice explicit in both legacy and modern paths, and add
cross-path regression coverage before changing the stored value.
