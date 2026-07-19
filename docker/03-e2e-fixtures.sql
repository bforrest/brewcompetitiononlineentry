-- ---------------------------------------------------------------------------
-- E2E test fixtures (Docker-local ONLY — never ship to a real deployment).
--
-- Applied by docker-entrypoint-initdb.d on every FRESH database volume, after
-- 01-baseline.sql (schema + fixture data) and 02-open-registration.sql
-- (deadline/version patches). Ordering comes from the filename prefix.
-- Changes here take effect only after a reseed:
--     docker compose down -v && docker compose up -d
--
-- CAPTCHA-off flag
-- ----------------
-- What:  `prefsCAPTCHA` (tinyint) in `baseline_preferences`. When 1, the
--        public registration form (sections/register.sec.php:1035) embeds
--        Google reCAPTCHA/hCaptcha, and process_users_register.inc.php:31-36
--        rejects any registration POST lacking a captcha response token
--        (redirect carries msg=4).
-- Why 0: the Playwright suite registers throwaway entrants through the real
--        form (e2e/helpers/auth.ts registerEntrant()). A third-party captcha
--        cannot — and should not — be solved by automation, so the flag must
--        be off for the e2e and write-load-test harnesses to exercise the
--        registration flow.
-- Scope: the 3.0.X baseline seed already ships prefsCAPTCHA = 0; this UPDATE
--        pins that deterministically so a future baseline change can't
--        silently break the e2e suite. It affects only this Docker instance's
--        database. Production installs configure CAPTCHA in the admin UI
--        (Site Preferences), which writes this same column.
-- Re-enable locally: set prefsCAPTCHA = 1 in Site Preferences (or via SQL)
--        and log out/in — preferences are cached in $_SESSION at login.
-- ---------------------------------------------------------------------------

USE bcoem;

UPDATE baseline_preferences SET prefsCAPTCHA = 0;
