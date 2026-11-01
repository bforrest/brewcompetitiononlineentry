# BCOE&M end-to-end tests (Playwright)

Browser-driven regression tests for the critical entrant and admin journeys,
plus authorization invariants. These are the Phase 0 safety net the
modernization work (see `Docs/superpowers/specs/`) refactors behind.

## Prerequisites

- Node 20+
- The Docker stack running: `docker compose up -d` (from the repo root)

## Run

```bash
cd e2e
npm install
npx playwright install chromium
npx playwright test              # all specs
npx playwright test --headed     # watch it run
npx playwright show-report       # open the last HTML report
```

- `BASE_URL` overrides the target (default `http://localhost:8080`).
- Single worker, serial journeys — they share one seeded database and must
  run in a deterministic order.

## Specs

| Spec | Covers |
| --- | --- |
| `tests/smoke.spec.ts` | App serves; login modal; admin login; entrant registration |
| `tests/entrant-journey.spec.ts` | Register → create entry → edit → payment page |
| `tests/admin-journey.spec.ts` | Entries list → check-in → judging session/table → score |
| `tests/security-invariants.spec.ts` | Authorization boundaries (one `fixme` = Phase 1 work) |

Helpers live in `helpers/`: `auth.ts` (login/registration against the real
public UI) and `entries.ts` (style selection, create-entrant-with-entry).

## Fresh database state

Journeys create unique users per run, so back-to-back runs don't collide and
no reseed is normally needed. To start completely clean:

```bash
docker compose down -v && docker compose up -d
```

The seed (`sql/bcoem_baseline_3.0.X.sql` + `docker/02-open-registration.sql` +
`docker/03-e2e-fixtures.sql`) makes the post-competition baseline behave like
a live, open competition — see the header of `docker/03-e2e-fixtures.sql` for
what each fixture flag does and why.

## Important: don't share a DB between e2e and the PHPUnit fee tests

The Playwright suite commits real brewing/brewer rows. Some PHPUnit
integration tests (`TotalFeesTest`) sum fees across all rows, so running them
against a database an e2e run already touched inflates those sums. CI keeps
the two in separate jobs/databases; locally, reseed (`down -v && up -d`)
before running the PHPUnit integration tier after an e2e run.

## Relationship to the PHPUnit suite

The 3-tier PHPUnit characterization suite (Unit/Integration/Approval) runs
inside the web container:

```bash
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit
```

Both suites, plus PHPStan, run on every push/PR via `.github/workflows/ci.yml`.
