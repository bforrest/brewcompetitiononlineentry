# Complete InnoDB Conversion and Set Charset/Collation Defaults — Design

## Goal

Fix two database-configuration gaps found while investigating the DB creation scripts:

1. The MyISAM→InnoDB conversion (commit `4023a6fc`) only touched `sql/bcoem_baseline_3.0.X.sql` — the Docker/test seed file. The real fresh-install script (`setup/install_db.setup.php`), the four version-upgrade scripts (`update/1.2.0.0_update.php`, `1.2.1.0_update.php`, `1.3.0.0_update.php`, `2.1.0.0_update.php`), and a runtime `payments` table creation (`includes/process/process_prefs.inc.php:542,616`) all still declare `ENGINE=MyISAM`. A real fresh install or in-place upgrade today would still end up on MyISAM.
2. `site/config.php:111-114` issues `mysqli_set_charset('utf8mb4')` + `SET NAMES 'utf8mb4'` (redundant with each other) + `SET COLLATION_CONNECTION='utf8mb4_unicode_ci'` on every single connection. `docker-compose.yml`'s `db` service has no server-level charset/collation configuration at all, so these per-connection calls are currently the *only* thing guaranteeing the right charset/collation — not just redundant ceremony.

## Background

There is no `CREATE DATABASE` statement anywhere in this codebase — the database is created either by Docker's `MARIADB_DATABASE` env var or manually during a shared-hosting setup, using whatever the MySQL/MariaDB server's own default charset/collation happens to be at that moment. A database-level charset default therefore has to come from server configuration, not app-owned SQL.

`update.php` is a live, still-reachable legacy entry point (`config/access_policy.php:213`, `Role::Anonymous` pre-setup) that dispatches version-specific upgrade scripts based on a stored version read from `{prefix}bcoem_sys.version`. Investigation found this dispatch is more layered than it first appears: `update.php` includes `update/current_update.php`, which — per its own docblock — only runs `update/current_alter_tables.php`/`update/current_data_updates.php` (both currently empty hook files) for installs **below version 2.1.8.0**; anyone already past that version skips them entirely. This rules out those hook files as a universal place to convert existing installs' tables, since most active installs are likely already past 2.1.8.0.

`ALTER TABLE ... ENGINE=InnoDB` is a safe no-op when a table is already InnoDB, so the conversion doesn't need to participate in that version-gated dispatch at all — it can run unconditionally on every `update.php` load, the same way `update.php` already does an idempotent `RENAME TABLE` (guarded by `table_exists()`) near its top, regardless of stored version.

No `FULLTEXT` indexes exist anywhere in the schema (confirmed via grep across `sql/bcoem_baseline_3.0.X.sql`, `setup/install_db.setup.php`, `update/*.php`), which removes the classic MyISAM→InnoDB conversion risk of FULLTEXT-index incompatibility on older MySQL/MariaDB versions.

No logger is reachable from this legacy procedural context — Monolog's channels (added in Phase 2) are wired through the DI container for the Slim/modern routes only, not exposed to raw scripts like `update.php`.

## Architecture

Two independent, small fixes, bundled in one spec because they were found together during the same investigation and touch overlapping files:

1. **Charset/collation**: set MariaDB server-level defaults via `docker-compose.yml`'s `db` service `command:` flags, so the database inherits `utf8mb4`/`utf8mb4_unicode_ci` at creation time on the primary (Docker) deployment target. `site/config.php`'s existing per-connection `SET` calls are left completely untouched — a harmless, redundant confirmation on Docker, still load-bearing on shared hosting (where the operator doesn't control server config, and this fix's Docker-only change can't reach them).
2. **InnoDB conversion, completed**: fix every remaining `ENGINE=MyISAM` declaration in table-creation code (`setup/install_db.setup.php`, the four `update/*.php` scripts, `process_prefs.inc.php`'s runtime `payments` table), so any freshly-created table is consistent with the already-converted baseline. Additionally, add one new, idempotent `ALTER TABLE ... ENGINE=InnoDB` step covering all 24 real tables, run unconditionally near the top of `update.php`, so a real existing production install — regardless of what version it's currently on — gets converted the next time its operator visits the update page.

## Components

| File | Change |
|---|---|
| `docker-compose.yml` | `db` service `command:` gains `--character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci` |
| `setup/install_db.setup.php` | All 22 `ENGINE=MyISAM` occurrences → `ENGINE=InnoDB` |
| `update/1.2.0.0_update.php`, `update/1.2.1.0_update.php`, `update/1.3.0.0_update.php`, `update/2.1.0.0_update.php` | Their own `CREATE`/`ALTER` statements' `ENGINE=MyISAM` → `ENGINE=InnoDB` |
| `includes/process/process_prefs.inc.php:542,616` | The runtime `payments` table `CREATE`, `ENGINE=MyISAM` → `ENGINE=InnoDB` |
| `lib/update.lib.php` | New function `convert_myisam_tables_to_innodb(mysqli $connection, string $prefix): array` — issues `ALTER TABLE {prefix}{table} ENGINE=InnoDB` for each of the 24 real tables (list below), returns an array of `[table => error message]` for any that failed, rather than dying. Extracted into a standalone function (not inlined into `update.php`) specifically so it's unit-testable without executing the entire legacy script. |
| `update.php` | Calls the new function unconditionally near the top (alongside the existing idempotent `bcoem_sys` rename check), and displays a short on-page notice if the returned failure array is non-empty — but continues executing the rest of the update flow either way. |

The 24 real tables `convert_myisam_tables_to_innodb()` targets (bare names, `{prefix}` applied by the function): `archive`, `bcoem_sys`, `brewer`, `brewing`, `contacts`, `contest_info`, `drop_off`, `evaluation`, `judging_assignments`, `judging_flights`, `judging_locations`, `judging_preferences`, `judging_scores`, `judging_scores_bos`, `judging_tables`, `mods`, `preferences`, `special_best_data`, `special_best_info`, `sponsors`, `staff`, `styles`, `style_types`, `users`.

## Data Flow

**Docker path**: `docker compose up` boots `mariadb:11` with the new `command:` flags → server-level `utf8mb4`/`utf8mb4_unicode_ci` defaults apply at startup, before MariaDB's entrypoint creates the `MARIADB_DATABASE=bcoem` database → the baseline SQL seeds tables (already `InnoDB`, unaffected by this change) → the app's existing `site/config.php` `SET` calls still fire per connection, now a redundant-but-harmless confirmation of what the server already defaults to.

**Existing production install, upgrading**: operator visits `update.php` → near the top, the new idempotent block calls `convert_myisam_tables_to_innodb()` for all 24 tables → any table still on `MyISAM` gets converted; any already-`InnoDB` (e.g. from a Docker/test environment, or a previous run of this same code) is a safe no-op → the rest of `update.php`'s existing version-dispatch logic runs unchanged, so this doesn't interfere with whatever version-specific migration steps the operator's install actually needs.

**Fresh install**: setup wizard runs `setup/install_db.setup.php` → tables are created as `InnoDB` directly, no `ALTER` ever needed for them.

**Shared hosting**: neither fix assumes Docker. The `docker-compose.yml` change only affects the Docker path. The InnoDB conversion runs identically on shared hosting the moment the operator visits `update.php` — same code, no environment-specific branching.

## Error Handling

- `convert_myisam_tables_to_innodb()` attempts each of the 24 tables independently and catches failures per-table rather than using the legacy `mysqli_query(...) or die(...)` convention seen elsewhere in `update.php` — a slow or failed `ALTER` on one large production table shouldn't block the operator's entire upgrade. Failures are collected into a return array (table name → `mysqli_error()` message).
- `update.php` displays a short, visible notice if the returned array is non-empty ("N table(s) could not be converted to InnoDB: ...") but continues executing the rest of the update flow regardless — this is a background-safe improvement, not a blocking requirement (the app works fine on a mix of storage engines in the interim).
- No logger is available from this legacy procedural context — the on-page notice is the only failure surface, matching how `update.php` already reports other problems to the operator running it.
- The charset/collation fix has no runtime error handling to design — it's a Docker startup-time flag; either the container starts with the right server defaults or it fails to start at all (an obvious, immediately visible failure mode, not a silent one).

## Testing

- **`convert_myisam_tables_to_innodb()`**: an Integration-tier test (real DB, using the existing `IntegrationTestCase` pattern) — create a table with `ENGINE=MyISAM` matching one of the 24 real table names in the test schema, call the function, assert the table's engine is now `InnoDB` via `information_schema.TABLES.ENGINE`. Also test that an already-`InnoDB` table is left alone without error (the idempotency case), and that a deliberately-unconvertible scenario (e.g. a nonexistent table name) is captured in the returned failure array rather than throwing.
- **`update.php`'s integration of that function**: not separately unit-tested — `update.php` is a legacy procedural entry point with no existing test harness (confirmed: no test file targets it today), and adding one is out of scope for this fix. The function-level test above proves the logic is correct; wiring it into `update.php` is a two-line call, verified by manual smoke-test rather than automated coverage.
- **Charset/collation**: no PHPUnit test — this is server configuration, not application code. Verification is manual: `docker compose up -d db`, then `docker compose exec db mariadb -u root -proot_password -e "SHOW VARIABLES LIKE 'character_set_server'; SHOW VARIABLES LIKE 'collation_server';"`, confirming both report `utf8mb4`/`utf8mb4_unicode_ci`.
- **Fresh-install/update-script MyISAM→InnoDB edits** (`setup/install_db.setup.php`, the four `update/*.php` files, `process_prefs.inc.php`): no new tests — these are one-line-per-occurrence string changes (`ENGINE=MyISAM` → `ENGINE=InnoDB`) with no logic to exercise; correctness is verified by grep (zero remaining `ENGINE=MyISAM` occurrences in the touched files) plus the existing PHPStan/Unit suite continuing to pass.

## Scope

**In scope:**
- `docker-compose.yml`'s `db` service server-level charset/collation defaults.
- Completing the InnoDB conversion across `setup/install_db.setup.php`, the four named `update/*.php` scripts, `process_prefs.inc.php`'s `payments` table, and a new unconditional, idempotent conversion step in `update.php` covering all 24 real tables.

**Explicitly out of scope:**
- Removing or modifying `site/config.php`'s existing per-connection `SET` calls — they stay exactly as-is (shared-hosting safety net).
- Any change to shared-hosting deployment's server-level MySQL configuration — outside this codebase's control, not something this fix can address.
- `update/current_alter_tables.php`/`current_data_updates.php` (the pre-2.1.8.0-only hooks) — investigated and rejected as the conversion's insertion point for the reason described in Background; left untouched.
- Any of the other `update/` directory files not named above (`styles_*.php`, `move_files.php`, etc.) — unrelated to storage engine or charset.
- Adding a test harness for `update.php` itself — out of scope per the Testing section.
