-- Brings the committed 2.7.X baseline (stamped 2.7.0.0) up to the 2.7.1.0 schema
-- this branch's code expects. Mirrors the "2.7.1" section of update/run_update.php;
-- without it every page 500s ("Unknown column 'prefsUserEntryLimitDates'").
-- Only the schema/data steps that apply to the baseline are replicated (the
-- entry-form and deprecated-theme conversions are no-ops: baseline already has
-- prefsEntryForm=5, prefsTheme=default).

USE bcoem;

ALTER TABLE baseline_brewer
  CHANGE brewerAHA brewerAHA VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

ALTER TABLE baseline_preferences
  ADD prefsUserEntryLimitDates MEDIUMTEXT NULL DEFAULT NULL AFTER prefsUSCLExLimit;

UPDATE baseline_styles SET brewStyleReqSpec = 1
WHERE brewStyleVersion = 'BJCP2021' AND brewStyleGroup = 'C2' AND brewStyleNum IN ('A','B','D','E');
