<?php

declare(strict_types=1);

use Bcoem\Security\Role;

/**
 * Central, deny-by-default authorization policy. Every reachable
 * section/go/action combination, process.inc.php dispatch value, and
 * self-bootstrapping side door must be declared here or
 * AuthorizationMiddleware denies it - see Task 4.
 *
 * Verified against the 2026-07-19 route inventory (index.php, index.legacy.php,
 * index.pub.php, includes/process.inc.php, ajax/*.php, admin/*.admin.php,
 * includes/output.inc.php). Task 3a (2026-07-19) closed out every placeholder
 * entry left by Task 3: each site/bootstrap.php $section_array value
 * was checked against index.pub.php's own inline $section blocks first, then
 * index.legacy.php's public-pages block, then any matching sections/*.sec.php
 * file, before a role was assigned - see .superpowers/sdd/task-3a-report.md
 * for the full per-entry citation trail. includes/output.inc.php's own
 * $section namespace (output:section:*) was enumerated and declared in the
 * same pass.
 */
return [
    // ── Admin base gate + per-go refinements (index.legacy.php dispatch) ──
    'section:admin' => Role::Admin,
    'section:admin|go:user' => Role::SuperAdmin,
    'section:admin|go:styles' => Role::SuperAdmin,
    'section:admin|go:archive' => Role::SuperAdmin,
    'section:admin|go:make_admin' => Role::SuperAdmin,
    'section:admin|go:contest_info' => Role::SuperAdmin,
    'section:admin|go:preferences' => Role::SuperAdmin,
    'section:admin|go:sponsors' => Role::SuperAdmin,
    'section:admin|go:style_types' => Role::SuperAdmin,
    'section:admin|go:special_best' => Role::SuperAdmin,
    'section:admin|go:special_best_data' => Role::SuperAdmin,
    'section:admin|go:mods' => Role::SuperAdmin,
    'section:admin|go:upload' => Role::SuperAdmin,
    'section:admin|go:change_user_password' => Role::SuperAdmin,
    'section:admin|go:dates' => Role::SuperAdmin,
    'section:admin|go:default' => Role::Admin,
    'section:admin|go:judging' => Role::Admin,
    'section:admin|go:non-judging' => Role::Admin,
    'section:admin|go:judging_preferences' => Role::Admin,
    'section:admin|go:judging_tables' => Role::Admin,
    'section:admin|go:judging_flights' => Role::Admin,
    'section:admin|go:judging_scores' => Role::Admin,
    'section:admin|go:judging_scores_bos' => Role::Admin,
    'section:admin|go:participants' => Role::Admin,
    'section:admin|go:entries' => Role::Admin,
    'section:admin|go:contacts' => Role::Admin,
    'section:admin|go:dropoff' => Role::Admin,
    'section:admin|go:checkin' => Role::Admin,
    'section:admin|go:count_by_style' => Role::Admin,
    'section:admin|go:count_by_substyle' => Role::Admin,
    'section:admin|go:upload_scoresheets' => Role::Admin,
    'section:admin|go:payments' => Role::Admin,
    'section:admin|go:evaluation' => Role::Entrant, // further gated on $_SESSION['prefsEval']==1 by legacy code, unchanged

    // ── Account pages (index.php's own $account_pages array) ──
    'section:list' => Role::Entrant,
    'section:pay' => Role::Entrant,
    'section:brewer' => Role::Entrant,
    'section:user' => Role::Entrant,
    'section:brew' => Role::Entrant,
    'section:evaluation' => Role::Entrant,

    // ── Public pages (site/bootstrap.php's $section_array) ──
    // Task 3a closed out every placeholder entry below (formerly defaulted
    // to Anonymous, unconfirmed). Method: for each $section value, grep
    // index.pub.php's own inline
    // `$section == "..."` blocks first (the normal, non-admin GET path),
    // then index.legacy.php's public-pages block (reached only when
    // $section=="admin" or the undocumented $admin GET param forces it),
    // then any matching sections/*.sec.php file. The great majority of the
    // flagged values (confirm, delete, table_cards/table-cards,
    // participant_summary, loc, sorting, output_styles, map, driving, scores,
    // entries, participants, emails, assignments, bos-mat, dropoff, summary,
    // inventory, pullsheets, results, staff, styles, promo, testing, notes,
    // shipping-label, particpant-entries) turned out to be dead entries in
    // bootstrap.php's whitelist: grepping index.pub.php and index.legacy.php
    // for each value's own `$section == "..."` block returns nothing, and no
    // sections/*.sec.php file with that name is ever included from either
    // rendering path. Several of the same words are reused elsewhere in the
    // app (as includes/output.inc.php's own, unrelated $section values, or as
    // $go values, or as $tb sub-filter values inside an already-included
    // page) but that reuse doesn't make the *index.php* $section value of
    // the same name reachable. Empirically verified for a sample (results,
    // scores, entries, participants, dropoff, styles, staff, summary) via
    // `curl http://localhost:8080/index.php?section=<value>` during Task 3a:
    // response is byte-for-byte the generic chrome with no section-specific
    // content, confirming Anonymous is correct because there is nothing to
    // gate, not because it was left unchecked. Full per-value citations are
    // in .superpowers/sdd/task-3a-report.md.
    'section:default' => Role::Anonymous,
    'section:rules' => Role::Anonymous,
    'section:entry' => Role::Anonymous,
    'section:volunteers' => Role::Anonymous,
    'section:contact' => Role::Anonymous,
    'section:login' => Role::Anonymous,
    'section:logout' => Role::Anonymous, // must work regardless of auth state
    'section:check' => Role::Anonymous,
    'section:setup' => Role::Anonymous, // matches today's (unauthenticated) reality - see Task 3a note on setup.php
    'section:judge' => Role::Anonymous, // confirmed dead: no $section=="judge" block in index.pub.php/index.legacy.php; sections/judge.sec.php's own auth check is commented out (lines 9-17) and the file is never included from any reachable path
    'section:register' => Role::Anonymous,
    'section:sponsors' => Role::Anonymous,
    'section:past_winners' => Role::Anonymous,
    'section:past-winners' => Role::Anonymous,
    'section:step1' => Role::Anonymous, 'section:step2' => Role::Anonymous,
    'section:step3' => Role::Anonymous, 'section:step4' => Role::Anonymous,
    'section:step5' => Role::Anonymous, 'section:step6' => Role::Anonymous,
    'section:step7' => Role::Anonymous, 'section:step8' => Role::Anonymous,
    'section:update' => Role::Anonymous, // matches today - see Task 3a note on update.php
    'section:confirm' => Role::Anonymous, // confirmed dead: string appears only in bootstrap.php's whitelist array (site/bootstrap.php:27)
    'section:delete' => Role::Anonymous, // confirmed dead as a GET render (no matching block anywhere); the POST action=delete path is separately covered by process:action:delete below
    'section:table_cards' => Role::Anonymous, 'section:table-cards' => Role::Anonymous, // confirmed dead for index.php; both strings are reused as includes/output.inc.php's own section value (see output:section:table-cards below) and inside includes/db/admin_common.db.php:79,90,106 as a $go-compound modifier, neither of which is reachable via index.php's plain $section dispatch
    'section:participant_summary' => Role::Anonymous, // confirmed dead for index.php; reused by output/participant_summary.output.php:11 (see output:section:summary below) and includes/db/brewer.db.php:59 (compound with $section=="admin", unrelated)
    'section:loc' => Role::Anonymous, // confirmed dead: only reused inside includes/db/output_participants_export.db.php:14, which is only reachable from the output.inc.php dispatch, not index.php
    'section:sorting' => Role::Anonymous, // confirmed dead for index.php; reused by includes/output.inc.php:29 (see output:section:sorting below) and includes/db/styles.db.php:87 (only relevant when that file is included from the output/admin contexts, not index.php's plain dispatch)
    'section:output_styles' => Role::Anonymous, // confirmed dead: string appears only in bootstrap.php's whitelist array (site/bootstrap.php:27)
    'section:map' => Role::Anonymous, // confirmed dead: string appears only in bootstrap.php's whitelist array (site/bootstrap.php:27)
    'section:driving' => Role::Anonymous, // confirmed dead: string appears only in bootstrap.php's whitelist array (site/bootstrap.php:27)
    'section:scores' => Role::Anonymous, // confirmed dead for index.php (empirically verified via curl - identical to an unhandled section); "scores" also appears as an unrelated $tb sub-filter value inside sections/winners*.sec.php
    'section:entries' => Role::Anonymous, // confirmed dead as a bare $section - distinct from admin|go:entries above; the only "entries" matches elsewhere are $go values (e.g. sections/alerts.sec.php:250, admin/entries.admin.php:664)
    'section:participants' => Role::Anonymous, // confirmed dead as a bare $section - distinct from admin|go:participants above; the only "participants" matches elsewhere are $go values (includes/db/brewer.db.php:59-112)
    'section:emails' => Role::Anonymous, // confirmed dead: string appears only in bootstrap.php's whitelist array (site/bootstrap.php:27)
    'section:assignments' => Role::Anonymous, // confirmed dead for index.php; reused by includes/output.inc.php:29 (see output:section:assignments below)
    'section:bos-mat' => Role::Anonymous, // confirmed dead for index.php; reused by includes/output.inc.php:29 (see output:section:bos-mat below)
    'section:dropoff' => Role::Anonymous, // confirmed dead as a bare $section - distinct from admin|go:dropoff above (empirically verified via curl); reused by includes/output.inc.php:29 (see output:section:dropoff below)
    'section:summary' => Role::Anonymous, // confirmed dead for index.php (empirically verified via curl); reused by includes/output.inc.php:29 (see output:section:summary below)
    'section:inventory' => Role::Anonymous, // confirmed dead for index.php; reused by includes/output.inc.php:29 (see output:section:inventory below)
    'section:pullsheets' => Role::Anonymous, // confirmed dead for index.php; reused by includes/output.inc.php:29 (see output:section:pullsheets below)
    'section:results' => Role::Anonymous, // confirmed dead for index.php (empirically verified via curl - identical to an unhandled section); sections/winners*.sec.php and sections/bestbrewer.sec.php check $section=="results" but those files are only included via output/results.output.php and admin/default.admin.php, never via index.php's plain dispatch
    'section:staff' => Role::Anonymous, // confirmed dead for index.php (empirically verified via curl); reused by includes/output.inc.php:29 (see output:section:staff below)
    'section:styles' => Role::Anonymous, // confirmed dead as a bare $section - distinct from admin|go:styles above (empirically verified via curl); includes/db/styles.db.php:69,92 checks $section=="styles" but that file is never included from index.php's plain dispatch for that value
    'section:promo' => Role::Anonymous, // confirmed dead: string appears only in bootstrap.php's whitelist array (site/bootstrap.php:27)
    'section:testing' => Role::Anonymous, // confirmed dead as a $section; an unrelated $go value of the same name exists in includes/constants.inc.php:602's $datetime_load array
    'section:notes' => Role::Anonymous, // confirmed dead for index.php; reused by includes/output.inc.php:29 (see output:section:notes below)
    'section:qr' => Role::Anonymous, // redirects to qr.php per bootstrap.php:33-36
    'section:shipping-label' => Role::Anonymous, // confirmed dead for index.php; reused by includes/output.inc.php:29 (see output:section:shipping-label below)
    'section:particpant-entries' => Role::Anonymous, // confirmed dead for index.php (sic - typo is in the app's own array); reused by includes/output.inc.php:29 (see output:section:particpant-entries below)
    'section:competition' => Role::Anonymous, // dead sections/ reference per inventory, renders via index.pub.php inline instead
    'section:winners' => Role::Anonymous, // public results page (used by e2e security-invariants.spec.ts today)

    // ── process.inc.php: $action-first dispatch ──
    'process:action:login' => Role::Anonymous,
    'process:action:logout' => Role::Anonymous,
    'process:action:forgot' => Role::Anonymous,
    'process:action:reset' => Role::Anonymous,
    'process:action:delete' => Role::Entrant, // per-row ownership enforced by legacy code, unchanged
    'process:action:barcode_check_in' => Role::Admin,
    'process:action:update_judging_flights' => Role::Admin,
    'process:action:delete_scoresheets' => Role::Admin,
    'process:action:clear_session' => Role::Entrant,
    'process:action:purge' => Role::SuperAdmin,
    'process:action:cleanup' => Role::SuperAdmin,
    'process:action:generate_judging_numbers' => Role::Admin,
    'process:action:check_discount' => Role::Entrant,
    'process:action:convert_bjcp' => Role::SuperAdmin,
    'process:action:archive' => Role::SuperAdmin,
    'process:action:publish' => Role::SuperAdmin,
    'process:action:email' => Role::Entrant,
    'process:action:paypal' => Role::Anonymous, // PayPal IPN-style POST, no session
    'process:action:dates' => Role::SuperAdmin,

    // ── process.inc.php: $dbTable fallback dispatch (generic CRUD) ──
    'process:dbTable:baseline_brewing' => Role::Entrant,
    // Anonymous, not Entrant (Task 10 fix): includes/process/process_users.inc.php's
    // own dispatch (reached for EVERY action once $dbTable falls through to this
    // generic CRUD branch, since process.inc.php's else-block at line 410 doesn't
    // itself branch on $action) has three independently-gated sub-cases of its
    // own: action=add&section=register -> process_users_register.inc.php with NO
    // session check at all (genuine anonymous registration, by design); action=
    // add&section=admin -> requires $_SESSION['userLevel']<=1 internally; action=
    // edit -> requires isset(loginUsername) and isset(userLevel) internally. A
    // central-gate floor of Role::Entrant blocked the FIRST of those - the
    // anonymous registration path - before process_users.inc.php's own dispatch
    // ever ran, since a brand-new visitor's identity is Role::Anonymous, which
    // never satisfies a required Role::Entrant. Anonymous is the correct floor
    // here precisely because legacy code enforces the finer-grained per-branch
    // rules itself, unchanged - confirmed by reading process_users.inc.php lines
    // 15-129. Caught by Task 10's equivalence gate (fresh-entrant registration
    // 403ing end to end).
    'process:dbTable:baseline_users' => Role::Anonymous,
    'process:dbTable:baseline_brewer' => Role::Entrant,
    'process:dbTable:baseline_contest_info' => Role::SuperAdmin,
    'process:dbTable:baseline_preferences' => Role::SuperAdmin,
    'process:dbTable:baseline_sponsors' => Role::SuperAdmin,
    'process:dbTable:baseline_judging_locations' => Role::Admin,
    'process:dbTable:baseline_drop_off' => Role::Admin,
    'process:dbTable:baseline_styles' => Role::SuperAdmin,
    'process:dbTable:bcoem_shared_styles' => Role::SuperAdmin,
    'process:dbTable:baseline_contacts' => Role::Anonymous, // public contact form submission
    'process:dbTable:baseline_judging_preferences' => Role::Admin,
    'process:dbTable:baseline_judging_tables' => Role::Admin,
    'process:dbTable:baseline_judging_flights' => Role::Admin,
    'process:dbTable:baseline_judging_assignments' => Role::Admin,
    'process:dbTable:baseline_judging_scores' => Role::Judge,
    'process:dbTable:baseline_judging_scores_bos' => Role::Judge,
    'process:dbTable:baseline_style_types' => Role::SuperAdmin,
    'process:dbTable:baseline_special_best_info' => Role::SuperAdmin,
    'process:dbTable:baseline_special_best_data' => Role::SuperAdmin,
    'process:dbTable:baseline_mods' => Role::SuperAdmin,
    'process:dbTable:baseline_evaluation' => Role::Entrant,

    // ── Self-bootstrapping side doors (file: keyed by basename) ──
    'file:qr.php' => Role::Anonymous, // internally gates via qrPasswordOK, unchanged
    'file:handle.php' => Role::Entrant, // covers pdf-download; upload sub-case needs userLevel==0, enforced by legacy code, unchanged
    'file:ppv.php' => Role::Anonymous, // PayPal IPN webhook - cannot authenticate via session by design
    'file:awards.php' => Role::Anonymous, // internally gates on display_to_public / display_to_admin, unchanged
    'file:maintenance.php' => Role::Anonymous,
    'file:setup.php' => Role::Anonymous, // matches today's reality (unauthenticated) - flagged as a P2 finding, not fixed by this phase (no behavior change), tracked separately
    'file:update.php' => Role::Anonymous, // pre-setup wizard exposure matches setup.php; post-setup body is internally gated, unchanged
    'file:phinx-migrate.php' => Role::SuperAdmin, // Task 13: browser-triggered Phinx migration runner for shared hosting; matches its own internal userLevel==0 check
    'file:400.php' => Role::Anonymous, 'file:401.php' => Role::Anonymous,
    'file:403.php' => Role::Anonymous, 'file:404.php' => Role::Anonymous,
    'file:500.php' => Role::Anonymous,
    'file:admin/send_test_email.admin.php' => Role::Admin, // matches its own internal userLevel<2 check
    'file:output/maps.output.php' => Role::Anonymous, // matches today (no auth check exists) - open-redirect risk flagged separately, not this phase's scope

    // ── ajax/*.php (each keyed as its own file, matching its own internal check) ──
    'file:ajax/account_checks.ajax.php' => Role::Anonymous,
    'file:ajax/count_records.ajax.php' => Role::Anonymous,
    'file:ajax/custom_style.ajax.php' => Role::Admin,
    'file:ajax/import_scores.ajax.php' => Role::Admin,
    'file:ajax/practice_session.ajax.php' => Role::SuperAdmin,
    'file:ajax/purge.ajax.php' => Role::SuperAdmin,
    'file:ajax/regenerate.ajax.php' => Role::SuperAdmin,
    'file:ajax/save.ajax.php' => Role::Judge, // per-action further refinement (userLevel<=1 for some) enforced by legacy code, unchanged
    'file:ajax/tables_mode.ajax.php' => Role::Judge,
    'file:ajax/username.ajax.php' => Role::Anonymous,
    'file:ajax/valid_email.ajax.php' => Role::Anonymous,

    // ── includes/output.inc.php (dispatcher has no gate of its own -
    //    every reachable $section under it must be declared here) ──
    // Enumerated from includes/output.inc.php:29-32's $print_sections /
    // $export_sections / $label_sections / $entry_sections /
    // $scoresheet_sections arrays. Each role below matches the target
    // output/*.output.php file's own existing check, verified by reading it.

    // $print_sections -> output/print.output.php. Outer gate (lines 8-16)
    // requires (logged in) OR (a print $token). Most values additionally
    // require $_SESSION['userLevel'] <= 1 (line 139) - Role::Admin.
    'output:section:admin' => Role::Admin, // output/print.output.php:162 - userLevel<=1
    'output:section:assignments' => Role::Admin, // output/print.output.php:139-140
    'output:section:bos-mat' => Role::Admin, // output/print.output.php:139,141
    'output:section:dropoff' => Role::Admin, // output/print.output.php:139,142
    'output:section:summary' => Role::Admin, // output/print.output.php:139,143
    'output:section:particpant-entries' => Role::Admin, // output/print.output.php:139,144 (sic - typo is in the app's own array)
    'output:section:inventory' => Role::Admin, // output/print.output.php:139,145
    'output:section:pullsheets' => Role::Admin, // output/print.output.php:139,146
    'output:section:results' => Role::Admin, // output/print.output.php:139,147 - the public-facing results view/download instead goes through output:section:export-results below
    'output:section:sorting' => Role::Admin, // output/print.output.php:139,148
    'output:section:staff' => Role::Admin, // output/print.output.php:139,149
    'output:section:table-cards' => Role::Admin, // output/print.output.php:139,150
    'output:section:notes' => Role::Admin, // output/print.output.php:139,151
    'output:section:styles' => Role::Entrant, // output/print.output.php:154-155 - only requires isset(loginUsername), no userLevel check
    'output:section:shipping-label' => Role::Entrant, // output/print.output.php:154,156 - only requires isset(loginUsername)
    'output:section:evaluation' => Role::Anonymous, // output/print.output.php:160 - guarded only by the file's outer login-OR-token gate (lines 8-16); token bypass is by design (electronic scoresheet print links), unchanged
    'output:section:contact' => Role::Anonymous, // output/print.output.php:83 - only renders when a decrypt $token is present (`$token != "default"`); there is no plain-login path for this value, so a valid token is the real (unchanged) gate

    // $export_sections -> output/export.output.php. Outer gate (lines 49-69)
    // requires $_SESSION['userLevel'] <= 1 UNLESS the section is
    // export-results with go=judging_scores(_bos)&view=html|pdf, or the
    // section is export-personal-results (both explicitly exempted at
    // lines 56-60) - those two stay Anonymous at this layer; legacy code
    // narrows them further internally (unchanged).
    'output:section:export-entries' => Role::Admin, // output/export.output.php:49-69 (no exemption), 230 ($admin_role recheck)
    'output:section:export-loc' => Role::Admin, // output/export.output.php:49-69 (no exemption); the value is only ever used as a modifier inside the export-entries/export-emails blocks (lines 239, 999, 1214), never its own top-level dispatch
    'output:section:export-emails' => Role::Admin, // output/export.output.php:49-69, 989
    'output:section:export-participants' => Role::Admin, // output/export.output.php:49-69, 1199
    'output:section:export-promo' => Role::Admin, // output/export.output.php:49-69 (no exemption; block at line 1341 has no further internal admin_role check, relies entirely on the outer gate)
    'output:section:export-results' => Role::Anonymous, // output/export.output.php:53-58 explicit public exemption for go=judging_scores(_bos)&view=html|pdf ("Available publicly on the results page"); empirically verified during Task 3a - unauthenticated GET to that exact combo returns 200, other go/view combos still redirect to 403. Non-exempt go/view stays admin-gated internally (line 1512 $results_download), unchanged
    'output:section:export-staff' => Role::Admin, // output/export.output.php:49-69, 2403
    'output:section:export-personal-results' => Role::Anonymous, // output/export.output.php:60 - $authorized=TRUE unconditionally; actual content additionally requires isset(loginUsername) && $id!="default" at line 3400, unchanged

    // $label_sections -> output/labels.output.php. This file does not
    // branch on $section at all - the same top-of-file gate (line 4: any
    // logged-in user) applies to all three values, so all three share the
    // same minimum role. Most content is further gated to userLevel<=1
    // (line 76); one explicit non-admin carve-out exists for judging labels
    // (line 1147, "the only label output that non-admins can access") -
    // both narrowings are unchanged legacy behavior.
    'output:section:labels-admin' => Role::Entrant, // output/labels.output.php:4-8
    'output:section:labels-participant' => Role::Entrant, // output/labels.output.php:4-8
    'output:section:labels-judge' => Role::Entrant, // output/labels.output.php:4-8, 1147

    // $entry_sections -> output/bottle_label.output.php (entry.output.php's
    // dispatch line is commented out - both values currently route here).
    // Gate is isset($_SESSION['loginUsername']) only, no userLevel check.
    'output:section:entry-form' => Role::Entrant, // output/bottle_label.output.php:3-8
    'output:section:entry-form-multi' => Role::Entrant, // output/bottle_label.output.php:3-8

    // $scoresheet_sections -> output/scoresheets.output.php. Requires login;
    // per-record ownership (own brewerEmail, or userLevel<=1 admin bypass)
    // is enforced internally, unchanged.
    'output:section:scoresheet' => Role::Entrant, // output/scoresheets.output.php:19-25,69-74

    // ── Phase 3: Entry (brewing) workflow routes ──
    'entry.create.form' => Role::Entrant,
    'entry.edit.form' => Role::Entrant,
    'entry.create' => Role::Entrant,
    'entry.update' => Role::Entrant,
    'entry.delete' => Role::Entrant,
    'entry.list' => Role::Entrant,

    // Phase 3.4: Export routes
    'export.form' => Role::Admin,     // Export form (admin only)
    'export.download' => Role::Admin, // Export download (admin only; future: public for results)
    'export.preview' => Role::Admin,  // Preview before download (admin only)
];
