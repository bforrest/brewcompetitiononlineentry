# Phase 3.8: `lib/common.lib.php` Audit

**Date:** 2026-07-24
**Scope:** Full inventory of all 111 functions in `lib/common.lib.php` (5,488 lines) — the largest untouched file in the modernization roadmap. See `Docs/superpowers/specs/2026-07-24-phase3.8-common-lib-audit-design.md` for the approved design and `Docs/superpowers/specs/2026-07-21-phase3-sequencing-strategies.md` for how this phase fits the broader plan.

This is an audit-only deliverable. No code in `lib/common.lib.php` or its callers changed as part of this document.

## Scope & Methodology

Caller counts, SQL-execution flags, and test coverage below come from a repo-wide static `grep` for each function's name (script: `Docs/superpowers/scripts/2026-07-24-phase3.8-audit-collect.sh`; raw data: the sibling `.tsv` in the same directory), not from tracing actual runtime call graphs. Two limitations follow directly from that:

- **False negatives:** dynamic dispatch (a function called via a variable holding its name, `call_user_func`, etc.) would not be counted. No such pattern is known to exist for these functions today, but this audit did not exhaustively rule it out.
- **False positives:** a "caller" is any line anywhere in a `.php` file containing the literal text `functionname(` — including inside comments or docblocks that merely *reference* the function without calling it (several exist in this codebase's `src/Domain/Registration` files, which cite `lib/common.lib.php` line numbers in comments). Caller counts above roughly 2-3 are unlikely to be entirely comment noise, but low counts (1-2) for any function flagged below as a "dead-code candidate" or "extraction candidate" should be read as a lead, not a verified fact.

**SQL execution** is flagged per-function as "contains at least one `mysqli_query()`/`mysqli_multi_query()` call" — this is a *surface area* flag (this function is part of the file's 130 total SQL-execution call sites, confirmed by whole-file `grep -c`), not a per-function vulnerability confirmation. The codebase has zero prepared-statement usage anywhere (confirmed in `Docs/SQLi Remediation - mysqli_real_escape_string Audit.md`), so any function that executes SQL at all is a candidate for the file's eventual Track B (parameterization) work regardless of whether its current escaping happens to be correct.

**Escape-discard bug** flags the specific, already-documented anti-pattern from the SQLi Remediation audit: `mysqli_real_escape_string()` called without capturing its return value. This file has exactly 5 such sites (verified: whole-file `grep -c` for the pattern equals the per-function sum), concentrated in three functions — see the narrative section below.

**Classification** in the table is a provisional, mechanically-computed label, not a final recommendation:
- *dead-code candidate (unverified)* — zero legacy callers, zero modern callers, no test references found.
- *extraction candidate* — executes SQL (see above).
- *keep-as-legacy-only* — has callers or tests, but no SQL execution.

The Recommendation section near the end of this document is where these provisional labels get corrected against things the script cannot know (e.g. two functions already have a modern adapter wrapping them; several "extraction candidate" functions are already the tested source of truth via Approval/Integration tests, not blindly duplicatable).

## Master Function Table

| Function (line range) | Legacy callers | Modern callers | Test coverage | SQL execution | Escape-discard bug | Classification (provisional) |
|---|---|---|---|---|---|---|
| `csrf_token_generate()` 14-33 | 7 | 1 | none | 0 | 0 | keep-as-legacy-only |
| `password_verify_legacy()` 34-47 | 5 | 0 | Integration/PasswordLegacyMigrationTest.php;Unit/SecurityAndCryptoTest.php | 0 | 0 | keep-as-legacy-only |
| `password_needs_legacy_upgrade()` 48-58 | 3 | 0 | Integration/PasswordLegacyMigrationTest.php;Unit/SecurityAndCryptoTest.php | 0 | 0 | keep-as-legacy-only |
| `upgrade_legacy_password_hash()` 59-72 | 3 | 0 | Integration/PasswordLegacyMigrationTest.php | 0 | 0 | keep-as-legacy-only |
| `version_check()` 73-92 | 1 | 0 | bootstrap.php | 0 | 0 | keep-as-legacy-only |
| `search_array()` 93-103 | 0 | 0 | Unit/StringUtilitiesTest.php | 0 | 0 | keep-as-legacy-only |
| `in_string()` 104-108 | 0 | 0 | Unit/StringUtilitiesTest.php | 0 | 0 | keep-as-legacy-only |
| `designations()` 109-118 | 5 | 0 | Unit/HtmlGeneratorsTest.php | 0 | 0 | keep-as-legacy-only |
| `build_action_link()` 119-166 | 11 | 0 | Approval/LinkBuilderApprovalTest.php;Unit/UrlAndNavigationTest.php | 0 | 0 | keep-as-legacy-only |
| `build_output_link()` 167-184 | 0 | 0 | Approval/LinkBuilderApprovalTest.php;Unit/UrlAndNavigationTest.php | 0 | 0 | keep-as-legacy-only |
| `build_form_action()` 185-200 | 7 | 0 | Approval/LinkBuilderApprovalTest.php;Unit/UrlAndNavigationTest.php | 0 | 0 | keep-as-legacy-only |
| `build_public_url()` 201-228 | 120 | 0 | Approval/LinkBuilderApprovalTest.php;Unit/UrlAndNavigationTest.php | 0 | 0 | keep-as-legacy-only |
| `display_array_content()` 229-243 | 17 | 0 | Unit/StringUtilitiesTest.php | 0 | 0 | keep-as-legacy-only |
| `addOrdinalNumberSuffix()` 244-258 | 79 | 0 | Unit/OrdinalAndNumberFunctionsTest.php | 0 | 0 | keep-as-legacy-only |
| `purge_entries()` 259-410 | 5 | 0 | none | 5 | 0 | extraction candidate |
| `random_generator()` 411-428 | 40 | 0 | Unit/SecurityAndCryptoTest.php | 0 | 0 | keep-as-legacy-only |
| `relocate()` 429-467 | 67 | 0 | none | 0 | 0 | keep-as-legacy-only |
| `check_judging_numbers()` 468-482 | 0 | 0 | none | 1 | 0 | dead-code candidate (unverified) |
| `temp_convert()` 483-494 | 0 | 0 | Unit/ConversionFunctionsTest.php | 0 | 0 | keep-as-legacy-only |
| `weight_convert()` 495-516 | 0 | 0 | Unit/ConversionFunctionsTest.php | 0 | 0 | keep-as-legacy-only |
| `volume_convert()` 517-539 | 0 | 0 | Unit/ConversionFunctionsTest.php | 0 | 0 | keep-as-legacy-only |
| `GetSQLValueString()` 540-569 | 16 | 0 | Unit/HtmlGeneratorsTest.php | 0 | 0 | keep-as-legacy-only |
| `currency_info()` 570-812 | 3 | 0 | Unit/SecurityAndCryptoTest.php | 0 | 0 | keep-as-legacy-only |
| `total_fees()` 813-994 | 6 | 0 | Integration/TotalFeesTest.php | 8 | 0 | extraction candidate |
| `total_fees_paid()` 995-1298 | 6 | 0 | none | 11 | 0 | extraction candidate |
| `total_entries_brewer()` 1299-1310 | 0 | 0 | none | 1 | 0 | dead-code candidate (unverified) |
| `total_not_paid_brewer()` 1311-1328 | 6 | 0 | none | 2 | 0 | extraction candidate |
| `total_paid_received()` 1329-1345 | 5 | 0 | Integration/BestBrewerPointsTest.php | 1 | 0 | extraction candidate |
| `total_paid()` 1346-1356 | 0 | 0 | none | 1 | 0 | dead-code candidate (unverified) |
| `total_nopay_received()` 1357-1368 | 1 | 0 | none | 1 | 0 | extraction candidate |
| `style_convert()` 1369-1817 | 53 | 0 | Approval/StyleConvertApprovalTest.php | 7 | 0 | extraction candidate |
| `get_table_info()` 1818-2123 | 49 | 0 | Integration/GetTableInfoTest.php | 14 | 0 | extraction candidate |
| `style_type()` 2124-2170 | 9 | 0 | Approval/StyleTypeApprovalTest.php | 2 | 0 | extraction candidate |
| `check_bos_loc()` 2171-2181 | 0 | 0 | none | 1 | 0 | dead-code candidate (unverified) |
| `table_location()` 2182-2213 | 15 | 0 | none | 2 | 0 | extraction candidate |
| `score_count()` 2214-2237 | 5 | 0 | none | 1 | 0 | extraction candidate |
| `best_brewer_points()` 2238-2346 | 12 | 0 | Integration/BestBrewerPointsTest.php | 0 | 0 | keep-as-legacy-only |
| `bjcp_rank()` 2347-2432 | 7 | 0 | Unit/OrdinalAndNumberFunctionsTest.php | 0 | 0 | keep-as-legacy-only |
| `srm_color()` 2433-2470 | 6 | 0 | Approval/SrmColorApprovalTest.php;Unit/OrdinalAndNumberFunctionsTest.php | 0 | 0 | keep-as-legacy-only |
| `get_contact_count()` 2471-2480 | 2 | 0 | none | 1 | 0 | extraction candidate |
| `brewer_info()` 2481-2529 | 39 | 0 | Integration/BrewerInfoTest.php | 2 | 0 | extraction candidate |
| `get_entry_count()` 2530-2560 | 23 | 0 | none | 1 | 0 | extraction candidate |
| `get_evaluation_count()` 2561-2584 | 6 | 0 | none | 1 | 0 | extraction candidate |
| `get_participant_count()` 2585-2640 | 41 | 0 | none | 1 | 0 | extraction candidate |
| `display_place()` 2641-2696 | 52 | 0 | Integration/DisplayPlaceTest.php;Unit/OrdinalAndNumberFunctionsTest.php | 0 | 0 | keep-as-legacy-only |
| `entry_info()` 2697-2706 | 7 | 0 | Approval/EntryInfoApprovalTest.php | 1 | 0 | extraction candidate |
| `get_suffix()` 2707-2712 | 18 | 0 | none | 0 | 0 | keep-as-legacy-only |
| `score_check()` 2713-2725 | 3 | 0 | none | 1 | 0 | extraction candidate |
| `minibos_check()` 2726-2736 | 6 | 0 | none | 1 | 0 | extraction candidate |
| `winner_check()` 2737-2822 | 3 | 0 | none | 5 | 0 | extraction candidate |
| `brewer_assignment()` 2823-2880 | 9 | 0 | none | 1 | 0 | extraction candidate |
| `entries_unconfirmed()` 2881-2899 | 4 | 0 | none | 1 | 0 | extraction candidate |
| `check_special_ingredients()` 2900-2932 | 9 | 0 | none | 1 | 0 | extraction candidate |
| `entries_no_special()` 2933-2957 | 2 | 0 | none | 1 | 0 | extraction candidate |
| `data_integrity_check()` 2958-3128 | 3 | 0 | none | 9 | 0 | extraction candidate |
| `readable_number()` 3129-3173 | 3 | 0 | Unit/OrdinalAndNumberFunctionsTest.php | 0 | 0 | keep-as-legacy-only |
| `winner_method()` 3174-3199 | 0 | 0 | none | 0 | 0 | dead-code candidate (unverified) |
| `table_exists()` 3200-3210 | 26 | 0 | none | 1 | 0 | extraction candidate |
| `judge_assignment()` 3211-3223 | 1 | 0 | none | 1 | 0 | extraction candidate |
| `table_assignments()` 3224-3331 | 17 | 0 | none | 1 | 0 | extraction candidate |
| `available_at_location()` 3332-3360 | 0 | 0 | none | 1 | 0 | dead-code candidate (unverified) |
| `str_osplit()` 3361-3364 | 2 | 0 | Unit/UrlAndNavigationTest.php | 0 | 0 | keep-as-legacy-only |
| `readable_judging_number()` 3365-3381 | 6 | 0 | none | 0 | 0 | keep-as-legacy-only |
| `dropoff_location()` 3382-3393 | 2 | 0 | none | 1 | 0 | extraction candidate |
| `judge_steward_availability()` 3394-3460 | 3 | 0 | none | 1 | 0 | extraction candidate |
| `judge_entries()` 3461-3489 | 10 | 0 | none | 1 | 0 | extraction candidate |
| `judging_winner_display()` 3490-3494 | 7 | 0 | none | 0 | 0 | keep-as-legacy-only |
| `format_phone_us()` 3495-3549 | 23 | 0 | none | 0 | 0 | keep-as-legacy-only |
| `check_judging_flights()` 3550-3574 | 0 | 0 | none | 3 | 0 | dead-code candidate (unverified) |
| `get_archive_count()` 3575-3583 | 11 | 0 | none | 1 | 0 | extraction candidate |
| `number_pad()` 3584-3587 | 3 | 0 | Unit/OrdinalAndNumberFunctionsTest.php | 0 | 0 | keep-as-legacy-only |
| `open_or_closed()` 3588-3608 | 6 | 3 | Unit/Domain/Registration/Service/RegistrationServiceTest.php;Unit/OrdinalAndNumberFunctionsTest.php | 0 | 0 | keep-as-legacy-only |
| `limit_subcategory()` 3609-3679 | 5 | 3 | none | 3 | 0 | extraction candidate |
| `highlight_required()` 3680-3754 | 0 | 0 | none | 4 | 0 | dead-code candidate (unverified) |
| `user_check()` 3755-3772 | 0 | 0 | none | 1 | 0 | dead-code candidate (unverified) |
| `judging_location_info()` 3773-3800 | 6 | 1 | none | 1 | 0 | extraction candidate |
| `yes_no()` 3801-3836 | 41 | 0 | none | 0 | 0 | keep-as-legacy-only |
| `styles_active()` 3837-3929 | 12 | 0 | none | 4 | 0 | extraction candidate |
| `check_exension()` 3930-3946 | 0 | 0 | Unit/StringUtilitiesTest.php | 0 | 0 | keep-as-legacy-only |
| `open_limit()` 3947-3962 | 2 | 0 | Unit/OrdinalAndNumberFunctionsTest.php | 0 | 0 | keep-as-legacy-only |
| `obfuscateURL()` 3963-4016 | 14 | 0 | Unit/SecurityAndCryptoTest.php | 0 | 0 | keep-as-legacy-only |
| `deobfuscateURL()` 4017-4054 | 2 | 0 | Unit/SecurityAndCryptoTest.php | 0 | 0 | keep-as-legacy-only |
| `get_ba_style_info()` 4055-4081 | 0 | 0 | none | 0 | 0 | dead-code candidate (unverified) |
| `convert_to_ba()` 4082-4150 | 0 | 0 | none | 4 | 2 | dead-code candidate (unverified) |
| `convert_to_pro()` 4151-4197 | 0 | 0 | none | 3 | 1 | dead-code candidate (unverified) |
| `remove_sensitive_data()` 4198-4355 | 0 | 0 | none | 5 | 2 | dead-code candidate (unverified) |
| `verify_token()` 4356-4388 | 2 | 0 | Integration/VerifyTokenTest.php;Unit/SecurityAndCryptoTest.php | 1 | 0 | extraction candidate |
| `tiebreak_rule()` 4389-4493 | 43 | 0 | none | 0 | 0 | keep-as-legacy-only |
| `is_dir_empty()` 4494-4501 | 4 | 0 | none | 0 | 0 | keep-as-legacy-only |
| `pro_am_check()` 4502-4514 | 1 | 0 | none | 1 | 0 | extraction candidate |
| `is_html()` 4515-4518 | 27 | 0 | Unit/StringUtilitiesTest.php | 0 | 0 | keep-as-legacy-only |
| `style_number_const()` 4519-4545 | 52 | 1 | Unit/HtmlGeneratorsTest.php | 0 | 0 | keep-as-legacy-only |
| `user_flight_assignment()` 4546-4559 | 2 | 0 | none | 1 | 0 | extraction candidate |
| `entry_flight_assignment()` 4560-4570 | 2 | 0 | none | 1 | 0 | extraction candidate |
| `flight_count_info()` 4571-4623 | 4 | 0 | none | 3 | 0 | extraction candidate |
| `user_submitted_eval()` 4624-4639 | 2 | 0 | none | 1 | 0 | extraction candidate |
| `eval_exits()` 4640-4684 | 10 | 0 | none | 1 | 0 | extraction candidate |
| `remove_accents()` 4685-5216 | 5 | 0 | Unit/StringUtilitiesTest.php | 0 | 0 | keep-as-legacy-only |
| `truncate_string()` 5217-5231 | 18 | 0 | Unit/StringUtilitiesTest.php | 0 | 0 | keep-as-legacy-only |
| `place_heirarchy()` 5232-5250 | 8 | 0 | Unit/OrdinalAndNumberFunctionsTest.php | 0 | 0 | keep-as-legacy-only |
| `normalizeClubs()` 5251-5257 | 6 | 0 | Unit/StringUtilitiesTest.php | 0 | 0 | keep-as-legacy-only |
| `clean_up_text()` 5258-5264 | 0 | 0 | Unit/StringUtilitiesTest.php | 0 | 0 | keep-as-legacy-only |
| `prep_redirect_link()` 5265-5274 | 173 | 0 | Unit/UrlAndNavigationTest.php;bootstrap.php | 0 | 0 | keep-as-legacy-only |
| `display_array_content_style()` 5275-5304 | 0 | 0 | none | 0 | 0 | dead-code candidate (unverified) |
| `admin_relocate()` 5305-5313 | 1 | 0 | Unit/StringUtilitiesTest.php | 0 | 0 | keep-as-legacy-only |
| `scrub_filename()` 5314-5319 | 1 | 0 | Unit/StringUtilitiesTest.php | 0 | 0 | keep-as-legacy-only |
| `clean_filename()` 5320-5366 | 1 | 0 | Unit/StringUtilitiesTest.php | 0 | 0 | keep-as-legacy-only |
| `create_bs_alert()` 5367-5394 | 8 | 0 | Unit/HtmlGeneratorsTest.php | 0 | 0 | keep-as-legacy-only |
| `create_bs_popover()` 5395-5420 | 0 | 0 | Unit/HtmlGeneratorsTest.php | 0 | 0 | keep-as-legacy-only |
| `simpleEncrypt()` 5421-5454 | 5 | 0 | Unit/SecurityAndCryptoTest.php | 0 | 0 | keep-as-legacy-only |
| `simpleDecrypt()` 5455-5488 | 5 | 0 | Unit/SecurityAndCryptoTest.php | 0 | 0 | keep-as-legacy-only |

## Narrative: High-Priority Functions

The master table above is mechanically generated and flat — every function gets one row regardless of how much traffic it carries or what shape of risk it represents. This section is not: it is the union of three deliberately-chosen groups, each read from source (not inferred from the table), because these are the functions any future extraction or remediation work will actually touch first.

Selection is the union of:
1. All 3 functions where the escape-discard bug fires (`escape_discard_count > 0`).
2. The top 15 functions that execute SQL (`sql_query_calls > 0`), ranked by `legacy_callers + modern_callers` descending — the file's highest-traffic, DB-touching functions.
3. The 2 functions already wrapped by a modern adapter, regardless of caller rank.

20 functions total (no overlap between the groups).

### Escape-discard bug functions

### `convert_to_ba()` (4082-4150), `convert_to_pro()` (4151-4197), `remove_sensitive_data()` (4198-4355)

All three share the exact anti-pattern documented in `Docs/SQLi Remediation - mysqli_real_escape_string Audit.md`'s "Pattern B": each builds a complete UPDATE statement with `sprintf()` against already-interpolated values, then calls `mysqli_real_escape_string($connection, $updateSQL)` on the *finished query string* and discards the result. This is not fixable by capturing the return value — by the time the call runs, any attacker-controlled value already went through `sprintf()` unescaped. These are one-time admin-triggered conversion utilities (BJCP-to-BA and BA-to-Pro style conversion, and a data-scrubbing routine), not high-traffic request-path code, but the fix is identical regardless: delete the dead escape call, escape each interpolated value individually at its `sprintf()` argument position (the working template is `includes/process/process_brewer.inc.php:593`, cited in the same audit doc). Confirmed directly in source: `convert_to_ba()` (lines 4128-4130 and 4142-4144) builds two separate `UPDATE {prefix}brewing ...` statements per loop iteration/call and calls the discard pattern on both.

### Top 15 SQL-executing functions by combined caller count

Ranked via `awk -F'\t' 'NR>1 && $7>0 {print $1"\t"($4+$5)}' Docs/superpowers/scripts/2026-07-24-phase3.8-audit-data.tsv | sort -t$'\t' -k2 -rn | head -15`.

### `style_convert()` (1369-1817)

The single highest-traffic function selected here (53 combined callers) and one of the largest in the file (448 lines). It's a nine-branch dispatch on a `$type` parameter (`"1"` through `"9"`), with cases `"2"`, `"3"`, and `"5"` fully dead/commented out (`"2"` and `"3"` share one `/* ... */` block spanning lines 1430-1576; `"5"` is separately commented at lines 1704-1710), each live branch a completely different code path: `$type=="1"` does no SQL at all — it `include`s `styles.inc.php` and walks an in-memory `$style_sets` array keyed by `$_SESSION['prefsStyleSet']` and `$_SESSION['style_set_category_end']`; `$type=="4"` (lines ~1600-1701) runs one `SELECT ... FROM {prefix}styles WHERE id='%s'` per style *and* builds raw Bootstrap modal HTML strings inline — data lookup and view rendering interleaved in the same loop; `$type=="6"`,`"7"`,`"8"`,`"9"` each run their own single-row `sprintf()`-templated style lookup. Requires `config.php` and `language.lang.php` unconditionally and `styles.inc.php` conditionally. Any extraction has to treat this as up to 9 separate functions behind one name — the `$type` values are effectively an undocumented internal API baked into call sites across the codebase.

### `get_table_info()` (1818-2123)

Second-highest by caller count (49) and the file's single highest SQL-surface function (14 query-execution sites, tallied by the collector script). Dispatches on `$method` (`"basic"`, `"location"`, `"styles"`, `"assigned"`, `"list"`, `"count_total"`, `"score_total"`, `"count"`, `"count_scores"`, `"count_single_table"`), each building its own query against `judging_tables`/`judging_locations`/`judging_scores`/`brewing`, several resolved to archive-suffixed table names via `get_suffix()` plus an extra archive-lookup query. Notably, the initial table-info query (lines 1856-1858) is built with **raw string interpolation of `$table_id` and `$param` directly into the SQL string — no `sprintf()`, no escaping call of any kind**: `$query_table = "SELECT * FROM $judging_tables_db_table"; ... $query_table .= " WHERE id='$table_id'"; ... $query_table .= " WHERE tableLocation='$param'";`. This is a distinct, currently-unflagged SQLi surface: the escape-discard detector only catches the specific "escape-then-discard" pattern from the SQLi audit, not "never escaped at all," which is what's happening here. Reads `$_SESSION['prefsStyleSet']` and `$_SESSION['jPrefsTablePlanning']`; calls `get_suffix()` and `style_number_const()`.

### `get_participant_count()` (2585-2640)

41 combined callers, 1 SQL call site (single query per invocation, but 11 possible query bodies). Dispatches on `$type` (`default`, `judge`, `judge-assigned`, `steward-assigned`, `steward`, `staff`, `staff-assigned`, `organizer-assigned`, `received-entrant`, `with-entries`, `received-club`), building a `COUNT(*)`/`COUNT(DISTINCT ...)` query per type against archive-suffixable `brewer`/`staff`/`brewing` tables. The `organizer-assigned` branch is a two-table join returning an associative array (`first_name`/`last_name`/`uid`) instead of the scalar int every other branch returns — a return-shape inconsistency any typed extraction must handle explicitly rather than assume a uniform `int` signature. `$filter` is interpolated directly into table names (e.g. `$brewer_db_table = $prefix."brewer_".$filter`) with no allow-list check inside the function itself.

### `brewer_info()` (2481-2529)

39 combined callers. Looks up a brewer by `uid` first; if `mysqli_num_rows()` is 0, falls back to a second query by `id` — two sequential round trips in the miss case. Assembles the result into a single caret-delimited (`^`) string of 19 positional fields (recounting the `$r .=` statements at lines 2504-2526, treating the mutually-exclusive judge-rank branch at 2507-2511 as one field: name, phone, judge rank, BJCP judge ID, MHP, email, uid, clubs, discount, address, city, state, zip, country, and — gated on `$_SESSION['prefsProEdition']` — brewery name, mead-judge indicator, and two TTB/production fields decoded from a `brewerBreweryInfo` JSON column). Note the source's own inline `// N` position comments are unreliable — lines 2525 and 2526 are both labeled `// 17`, an apparent copy-paste error in the legacy code — so this count comes from the actual statements, not those comments. Callers parse this string positionally by index, which is a real obstacle to extraction: a typed replacement can't just return the row array, every call site depends on this exact field ordering and the `^`-join format.

### `table_exists()` (3200-3210)

26 combined callers. Very small function but the most directly unescaped-interpolation site reviewed in this batch: `$query_exists = "SHOW TABLES LIKE '".$table_name."'";` — plain string concatenation, no `sprintf()` placeholder, no `mysqli_real_escape_string()` call at all. `$table_name` is caller-supplied and nothing inside this function validates or escapes it.

### `get_entry_count()` (2530-2560)

23 combined callers, 1 SQL site with 9 possible WHERE-clause variants appended based on `$method` (`paid`, `received`, `paid-received`, `unpaid-received`, `paid-not-received`, `total-logged`, `unconfirmed`, `placing-entries`, `scored`). The appended clause fragments are hardcoded literals (no interpolated user data in the WHERE itself), but — same pattern as `get_participant_count()` — `$filter` is interpolated straight into the table name (`$judging_scores_db_table = $prefix."judging_scores_".$filter`).

### `table_assignments()` (3224-3331)

17 combined callers. Fetches a user's judging/stewarding table assignments via one properly `sprintf()`-templated query (`SELECT ... FROM {prefix}judging_assignments WHERE bid='%s' AND assignment='%s'`), then formats each row into one of five different output shapes selected by `$method2` (0, 1, 2, 3, or default) — raw `<tr>` HTML strings for two of them, a bare array of table IDs for `method2==2`, anchor-tag HTML for `method2==1`. Calls `get_table_info()` twice per row (once with `"basic"`, once with `"location"`) and `getTimeZoneDateTime()`. The four presentation branches are interleaved inside the same `do...while` fetch loop as the one data fetch — an extraction needs to separate "get the assignments" from "render them four different ways" rather than move this as a single unit.

### `table_location()` (2182-2213)

15 combined callers. Two-query lookup (`judging_tables` → `tableLocation` column, then `judging_locations` filtered by that id), both properly `sprintf()`-templated with `'%s'` placeholders — no raw-interpolation issue here, unlike several others in this list. The signature declares `$date_format`, `$time_zone`, and `$time_format` parameters, but the body never references them: the final `getTimeZoneDateTime()` call (line 2208) reads `$_SESSION['prefsTimeZone']`, `$_SESSION['prefsDateFormat']`, and `$_SESSION['prefsTimeFormat']` directly instead, the same `$_SESSION`-coupling pattern flagged elsewhere in this document (e.g. `get_table_info()`, `judge_entries()`). The three declared parameters are dead — passed by every caller but silently ignored.

### `styles_active()` (3837-3929)

12 combined callers, 4 SQL call sites. Three-mode dispatch (`$method` 0/1/2: distinct active style groups; count of BOS-eligible style types; full active style list with names), plus an archive path that resolves `$style_set`/`$style_types_db` via an extra lookup query keyed on `$archive`. Reads `$_SESSION['prefsStyleSet']`. Worth flagging: the `$method==2` branch's result loop (`do { $a[] = ...; } while ($row_styles = mysqli_fetch_assoc($styles));`) has no guard for a zero-row result the way the `$method==0` branch does (which checks `if ($row_styles)` before entering its loop) — a `do...while` on a false `$row_styles` would emit a PHP warning/notice on the first iteration. Not a currently-observed failure (the style table is presumably never empty in practice), but a pre-existing correctness gap worth carrying into any extraction rather than reproducing unnoticed.

### `get_archive_count()` (3575-3583)

11 combined callers. Like `table_exists()`, built with raw interpolation and no escaping: `$query_archive_count = "SELECT COUNT(*) as 'count' FROM \`$table\`";` — backtick-quoted but not escaped, no `sprintf()`. `$table` is a caller-supplied parameter. A second unescaped-interpolation site the escape-discard-only scan (Task 1/2's methodology) does not surface, because it never calls `mysqli_real_escape_string()` at all — there's nothing to discard.

### `judge_entries()` (3461-3489)

10 combined callers, 1 SQL site. Single `sprintf()`-templated query listing a brewer's entries ordered by `brewCategorySort`. Branches on `$_SESSION['prefsStyleSet'] == "BA"` to decide which columns to display, and on `$method` to decide whether each entry is wrapped in an admin-linking `<a href="...index.php?section=admin&go=entries&filter=...">` (HTML built directly in what is otherwise a data-fetch function) or returned as a plain label.

### `eval_exits()` (4640-4684)

10 combined callers, 1 SQL site with two shapes: `$eid=="default"` fetches all distinct eids (`SELECT DISTINCT eid FROM ...`); a specific `$eid` fetches the full row (`SELECT * ... WHERE eid='%s'`, `sprintf()`-templated). `$method` (`judge_scores`, `consensus_scores`, or default) then controls three different return semantics from the same rows: raw eid list, a computed per-evaluation score sum (aroma+appearance+flavor+mouthfeel+overall), or the stored `evalFinalScore` consensus value. Three distinct behaviors behind one signature — a straight port would need three typed methods, not one.

### `style_type()` (2124-2170)

9 combined callers, 2 SQL sites out of 4 total `$method` branches. `$method=="1"` and `$method=="2"` (when `$source=="bcoe"`) are pure in-memory `switch` lookup tables mapping between numeric and text type codes — no database access. `$method=="2"` (when `$source=="custom"`) and `$method=="3"` both hit `{prefix}style_types` via `sprintf()`. Because half the branches never touch the DB, an extraction should split the static mapping table from the DB-backed lookup rather than moving the whole function as a single SQL-executing unit.

### `check_special_ingredients()` (2900-2932)

9 combined callers, 1 SQL site. Looks up `brewStyleReqSpec` for a style (branching on BJCP2025 vs. other version formats for how it builds the `WHERE` clause), then applies a hardcoded exception list (`C2-C`, `C2-D`, `C4-C` — specific 2025 cider styles) that forces a `FALSE` return regardless of what the database flag says. This business rule is embedded directly in application code rather than being data-driven; an extraction has to carry the exception list forward deliberately rather than let it silently disappear as "just the DB lookup."

### `brewer_assignment()` (2823-2880)

9 combined callers, 1 SQL site. Looks up a user's row in `{prefix}staff` (or an archive-suffixed variant) and formats a human-readable assignment label per `$method` (`"1"` builds an array of role labels — organizer/BOS/judge/steward/staff — sourced from `language.lang.php`, which is `require`d inline; `"3"` maps a `$filter` value to a label; default falls through to `$r = ""`). Independent of this audit's SQLi focus, there is a latent bug at lines 2857-2858: the `"staff_judge"` case (`case "staff_judge": // for $filter URL variable`) branches on `elseif ($a == "stewards")` / `elseif ($a == "staff")` / `elseif ($a == "bos")`, but `$a` is never assigned anywhere in this function — the comment implies it should be `$filter`. This branch is effectively dead unless `$a` happens to be set as a leftover global from a prior call elsewhere in the request, which is exactly the kind of latent-state risk worth flagging for any team about to extract this function's logic.

### Already adapter-wrapped functions

### `limit_subcategory()` (3609-3679)

Already partially extracted: `src/Domain/Entry/Adapter/LegacyQueryAdapter::limitSubcategory()` wraps this directly (`require_once` + a straight passthrough call), per that adapter's own docblock stating the intended long-term rule ("no direct calls to common.lib.php outside this class"). Not yet a full extraction — the adapter is a typed pass-through, not a reimplementation — but it establishes the shape any further extraction from this file should follow. Confirmed in source: the function builds a style lookup (`SELECT id FROM {prefix}styles WHERE ...`, `sprintf()`-templated) and then one of two entry-count queries depending on `$_SESSION['prefsStyleSet'] == "BA"`, comparing the count against `$pref_num` and an optional per-subcategory exception (`$pref_exception_sub_num`/`$pref_exception_sub_array`) to decide whether an entry limit has been reached.

### `open_or_closed()` (3588-3608)

Wrapped by `src/Domain/Registration/Service/RegistrationService.php` (found during Phase 3.7 — see [[project-modernization]]'s note that this function had to be required on-demand from `common.lib.php` specifically because `paths.php` alone doesn't define it). Same adapter-shape opportunity as `limit_subcategory()` above, currently done via a direct `require_once` in the service rather than a dedicated adapter class. Confirmed in source: this is a pure function with no SQL and no global reads beyond its three parameters — it compares `$now` against `$date1`/`$date2` and returns `0` (not yet open), `1` (open), or `2` (closed), the simplest function in this entire narrative section and the easiest candidate for a genuine (not just passthrough) reimplementation.

## Recommendation

**Can `lib/common.lib.php` be deleted?** No, not yet. It is `require`d unconditionally by `site/bootstrap.php` (i.e. on every legacy page render) and directly by four self-bootstrapping side doors (`update.php`, `handle.php`, `setup.php`, `qr.php`). 14 of its 111 functions show zero static callers outside itself — the rest are load-bearing by at least one measure (a legacy caller, a modern adapter, or a test asserting its behavior directly).

### Bucket 1: Confirmed-dead candidates (14 functions)

- `check_judging_numbers()` (468-482)
- `total_entries_brewer()` (1299-1310)
- `total_paid()` (1346-1356)
- `check_bos_loc()` (2171-2181)
- `winner_method()` (3174-3199)
- `available_at_location()` (3332-3360)
- `check_judging_flights()` (3550-3574)
- `highlight_required()` (3680-3754)
- `user_check()` (3755-3772)
- `get_ba_style_info()` (4055-4081)
- `convert_to_ba()` (4082-4150)
- `convert_to_pro()` (4151-4197)
- `remove_sensitive_data()` (4198-4355)
- `display_array_content_style()` (5275-5304)

None were empirically (curl) verified as unreachable — that's explicitly out of scope for this pass (see the design doc). Recommended next step: a small, standalone future PR that empirically confirms each (the same method `config/access_policy.php`'s own audit used) before deleting.

### Bucket 2: Extraction-worthy

The 3 escape-discard-bug functions (`convert_to_ba`, `convert_to_pro`, `remove_sensitive_data`) and the highest-traffic SQL-executing functions from the narrative section above are the strongest candidates for a future targeted-extraction phase, following the `LegacyQueryAdapter` shape already established by `limit_subcategory()` and `open_or_closed()`. This phase does not pick which one goes first — that's a future phase's own brainstorm.

### Bucket 3: Legacy-only-forever

No function in this file is a clear "never worth moving" case purely by nature (unlike, say, a one-time install script) — even the display/formatting functions (`srm_color`, `style_convert`, etc.) are exercised on every relevant page render. This bucket is empty for this file; noted for completeness since the design doc named it as a possible outcome, not a required one.

### Superseded functions: none found

Checked directly: `grep -rl "srmColor\|SrmColor\|bestBrewerPoints\|BestBrewerPoints" src/Domain/` returns no matches. None of the Approval/Integration-tested functions in this file (`style_convert`, `srm_color`, `style_type`, `entry_info`, `build_action_link`, `build_output_link`, `build_form_action`, `build_public_url`, `best_brewer_points`, `total_fees`, `get_table_info`, `display_place`, `verify_token`) are reimplemented in `src/Domain/` — each is tested *as* the legacy source of truth, not as a stand-in for an existing `src/Domain` reimplementation (per each test file's own docblock, e.g. `SrmColorApprovalTest.php`'s stated purpose of pinning `srm_color()`'s own output table). No function qualifies for the "superseded by src/Domain equivalent" label today.
