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
 * includes/output.inc.php). Entries marked "VERIFY" reproduce the app's own
 * $section_array whitelist (site/bootstrap.php) but have not yet had their
 * individual sections/*.sec.php file read for its actual role requirement -
 * see Task 3a's checklist to close these out before this phase ships.
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

    // ── Public pages (site/bootstrap.php's $section_array, VERIFY entries
    //    per Task 3a before this policy map is considered complete) ──
    'section:default' => Role::Anonymous,
    'section:rules' => Role::Anonymous,
    'section:entry' => Role::Anonymous,
    'section:volunteers' => Role::Anonymous,
    'section:contact' => Role::Anonymous,
    'section:login' => Role::Anonymous,
    'section:logout' => Role::Anonymous, // must work regardless of auth state
    'section:check' => Role::Anonymous,
    'section:setup' => Role::Anonymous, // matches today's (unauthenticated) reality - see Task 3a note on setup.php
    'section:judge' => Role::Anonymous, // VERIFY: sections/judge.sec.php
    'section:register' => Role::Anonymous,
    'section:sponsors' => Role::Anonymous,
    'section:past_winners' => Role::Anonymous,
    'section:past-winners' => Role::Anonymous,
    'section:step1' => Role::Anonymous, 'section:step2' => Role::Anonymous,
    'section:step3' => Role::Anonymous, 'section:step4' => Role::Anonymous,
    'section:step5' => Role::Anonymous, 'section:step6' => Role::Anonymous,
    'section:step7' => Role::Anonymous, 'section:step8' => Role::Anonymous,
    'section:update' => Role::Anonymous, // matches today - see Task 3a note on update.php
    'section:confirm' => Role::Anonymous, // VERIFY
    'section:delete' => Role::Anonymous, // VERIFY - GET-only render, not the POST action=delete (see process: below)
    'section:table_cards' => Role::Anonymous, 'section:table-cards' => Role::Anonymous, // VERIFY
    'section:participant_summary' => Role::Anonymous, // VERIFY
    'section:loc' => Role::Anonymous, // VERIFY
    'section:sorting' => Role::Anonymous, // VERIFY
    'section:output_styles' => Role::Anonymous, // VERIFY
    'section:map' => Role::Anonymous, // VERIFY
    'section:driving' => Role::Anonymous, // VERIFY
    'section:scores' => Role::Anonymous, // VERIFY - likely public results, confirm
    'section:entries' => Role::Anonymous, // VERIFY - distinct from admin|go:entries above
    'section:participants' => Role::Anonymous, // VERIFY - distinct from admin|go:participants above
    'section:emails' => Role::Anonymous, // VERIFY
    'section:assignments' => Role::Anonymous, // VERIFY
    'section:bos-mat' => Role::Anonymous, // VERIFY
    'section:dropoff' => Role::Anonymous, // VERIFY - distinct from admin|go:dropoff above
    'section:summary' => Role::Anonymous, // VERIFY
    'section:inventory' => Role::Anonymous, // VERIFY
    'section:pullsheets' => Role::Anonymous, // VERIFY
    'section:results' => Role::Anonymous, // VERIFY
    'section:staff' => Role::Anonymous, // VERIFY
    'section:styles' => Role::Anonymous, // VERIFY - distinct from admin|go:styles above
    'section:promo' => Role::Anonymous, // VERIFY
    'section:testing' => Role::Anonymous, // VERIFY
    'section:notes' => Role::Anonymous, // VERIFY
    'section:qr' => Role::Anonymous, // redirects to qr.php per bootstrap.php:33-36
    'section:shipping-label' => Role::Anonymous, // VERIFY
    'section:particpant-entries' => Role::Anonymous, // VERIFY (sic - typo is in the app's own array)
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
    'process:dbTable:baseline_users' => Role::Entrant, // registration (anonymous sub-case handled inside process_users_register.inc.php, unchanged) + self-service edits
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
    // VERIFY: enumerate includes/output.inc.php:27-30's $print_sections /
    // $export_sections / $label_sections / $entry_sections /
    // $scoresheet_sections arrays and declare one 'output:section:{value}'
    // entry per value, matching each target output/*.output.php file's own
    // existing check (all confirmed to have one - see Task 3a).
];
