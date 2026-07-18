# Docker + Write Load Test Runbook

Built 2026-07-14. Local Docker stack (mariadb:11 + php:8.2-apache) plus a
working `bcoem-loadtest-writes.sh` smoke test for catching regressions.

## Bring up / reset

```bash
docker compose down -v          # wipe everything (safe — throwaway local data)
docker compose up -d --build    # fresh volume, reseeds automatically
```

`docker-entrypoint-initdb.d` runs, in order, on every fresh volume:
1. `sql/bcoem_baseline_3.0.X.sql` — schema + baseline fixture data
2. `docker/02-open-registration.sql` — two local-testing patches (see below)

App is at `http://localhost:8080`. Admin login:
`user.baseline@brewingcompetitions.com` / `bcoem`.

## Files added this session

- `docker/apache/vhost.conf`, `docker/config.php`, `docker/.htaccess` —
  referenced by `Dockerfile`/`docker-compose.yml` but didn't exist on disk;
  authored to match the env vars already defined in `docker-compose.yml`.
- `docker/02-open-registration.sql` — two fixes needed for the fixture to be
  usable at all, see "Gotchas" below.

## Gotchas found (worth knowing before touching this again)

1. **CSRF token required on every POST.** `includes/process.inc.php:109-134`
   validates `user_session_token` (a per-session token rendered as a hidden
   field on forms) against `$_SESSION['user_session_token']` for any POST
   where `section` isn't in `{login,logout,forgot,reset,paypal}`. The
   registration form only renders this token at `?section=register&go=entrant`
   — **not** at `go=default` (that's just the Entrant/Judge/Steward chooser
   page). `bcoem-loadtest-writes.sh` now scrapes the token from the GET
   response before POSTing.

2. **A successful registration logs the session in.** After that, the same
   cookie jar can't register again — the anonymous form (and its token) is
   gone from that session. The load test now resets its cookie jar after
   every iteration so each one is a fresh anonymous visitor, matching what a
   real distinct entrant would do anyway.

3. **Real bug: unguarded auto-migration under concurrency.**
   `lib/preflight.lib.php` compares the DB's stored `bcoem_sys.version`/
   `version_date` against `includes/current_version.inc.php`, and if they
   don't match, `site/bootstrap.php:62` does
   `if ($force_update) include (UPDATE.'run_update.php');` — inline, on
   *every* session lacking `$_SESSION['currentVersion']`, with no lock or
   guard. The baseline fixture seeds version `3.0.1.0` against code at
   `3.0.3.0`, so every fresh session ran the *entire* multi-hundred-statement
   `update/run_update.php` migration. Under this load test's concurrency
   (10 fresh sessions), that produced a real MariaDB metadata-lock pile-up
   (`SHOW PROCESSLIST` showed dozens of queued `ALTER TABLE baseline_styles`
   / `UPDATE baseline_styles` statements, oldest stuck 300+ seconds) that
   stalled the entire site, including plain page loads, for minutes. This
   would happen on a real production instance too, right after a version
   bump, if enough concurrent traffic hits before any single request
   completes the update and sets `$_SESSION['currentVersion']`.
   `docker/02-open-registration.sql` stamps the seed row's version/date to
   match current so this fixture never triggers that path — but the
   underlying app bug (no concurrency guard around the inline migration) is
   still there and would be worth a real fix separately.

## Running the smoke test

```bash
bash .claude/bcoem-loadtest-writes.sh -u http://localhost:8080 -p baseline_ -c 10 -d 20
```

`-p baseline_` matches the table prefix set in `docker/config.php` (must
match whatever the fixture uses). Validated clean run from a truly cold
`docker compose up`: 297 requests, 100% success, p50 0.088s / p99 0.170s,
207 registration rows landed in `baseline_users`.

Reset between runs with `docker compose down -v && docker compose up -d`
(re-seeds clean; the version-stamp fix means no manual warm-up or DB
poking is needed after that).
