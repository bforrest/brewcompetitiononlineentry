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

-- Winners-display-off flag
-- ------------------------
-- What:  `prefsDisplayWinners` ('Y'/'N') in `baseline_preferences`, plus the
--        `prefsWinnerDelay` timestamp. When 'Y' and the delay has passed
--        (includes/constants.inc.php:531-536), every logged-in user is put in
--        "scores mode" ($show_scores = TRUE): the account page shows results
--        and HIDES the entire entry-management UI (Add Entry / Entries / Pay
--        buttons, pub/list.pub.php:25-32).
-- Why N: the baseline fixture is a post-competition snapshot — its
--        prefsWinnerDelay (2023-11-02) has long passed. The e2e journeys need
--        the pre-judging state where entrants can still create/edit/pay for
--        entries, so winners display must be off. 02-open-registration.sql
--        already reopens the entry deadlines; this closes the other half.
-- Scope: Docker-local only, same as above. Real competitions toggle this in
--        Site Preferences when they're ready to publish results.
-- ---------------------------------------------------------------------------

USE bcoem;

-- Destructive landing fixtures hard-fail unless the operator explicitly opts
-- in and this Docker-only marker proves the selected database is disposable.
CREATE TABLE IF NOT EXISTS bcoem_e2e_disposable_database (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  marker VARCHAR(64) NOT NULL
) ENGINE=InnoDB;
INSERT INTO bcoem_e2e_disposable_database (id, marker)
VALUES (1, 'BCOEM_E2E_DISPOSABLE_V1')
ON DUPLICATE KEY UPDATE marker = VALUES(marker);

UPDATE baseline_preferences SET prefsCAPTCHA = 0, prefsDisplayWinners = 'N';

-- Landing-page state-matrix baseline
-- ----------------------------------
-- Task 9 mutates only baseline_* rows through bound mysql2 statements, then
-- restores these exact values after every serial Playwright scenario.
UPDATE baseline_contest_info SET
  contestRegistrationOpen     = 1685664000,
  contestRegistrationDeadline = 1893456000,
  contestEntryOpen            = 1685664000,
  contestEntryDeadline        = 1893456000,
  contestJudgeOpen            = 1685664000,
  contestJudgeDeadline        = 1893456000,
  contestDropoffOpen          = 1685664000,
  contestDropoffDeadline      = 1893456000,
  contestShippingOpen         = 1685664000,
  contestShippingDeadline     = 1893456000,
  contestAwardsLocTime        = 1698890400
WHERE id = 1;

UPDATE baseline_preferences SET
  prefsSponsors       = 'N',
  prefsSponsorLogos   = 'N',
  prefsContact        = 'N',
  prefsEntryLimit     = NULL,
  prefsEntryLimitPaid = NULL,
  prefsDisplayWinners = 'N',
  prefsWinnerDelay    = 1698899400
WHERE id = 1;

DELETE FROM baseline_brewing;
DELETE FROM baseline_judging_locations;
DELETE FROM baseline_sponsors;
DELETE FROM baseline_archive;
DELETE FROM baseline_contacts;
INSERT INTO baseline_contacts
  (contactFirstName, contactLastName, contactPosition, contactEmail)
VALUES
  ('Default', 'Admin', 'Competition Coordinator', 'user.baseline@brewingcompetitions.com');
