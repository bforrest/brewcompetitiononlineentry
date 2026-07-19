## How do I run these tests locally?

Two suites, both against the Docker stack. From the repo root:

1. Start the stack (once per session):


docker compose up -d
2. PHPUnit characterization tests (336 tests, run inside the web container):

```bash
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit
```

The `-e BCOEM_DB_HOST=db` points the tests at the DB container. Run one tier with `--testsuite Unit` (or `Integration / Approval`). The Unit tier is the only one that also works on your host without Docker (`vendor/bin/phpunit --testsuite Unit`) — the DB tiers need the container because they rely on `docker/config.php`.

3. Playwright e2e tests (run on your host, driving the running stack):

```bash
cd e2e
npm install                        # first time only
npx playwright install chromium    # first time only
npx playwright test                # all specs
```

Useful variants: `npx playwright test --headed` to watch it, `npx playwright show-report` for the last HTML report.

One caveat that will bite you: don't run the PHPUnit Integration tier against a database an e2e run already touched. Playwright commits real entry rows, and TotalFeesTest sums fees across all rows, so it'll fail with inflated totals. Reseed first:


```bash
docker compose down -v && docker compose up -d
```

That wipes the DB volume and re-seeds clean (fixtures set CAPTCHA off / winners-display off so the baseline behaves like a live competition). The e2e suite itself is fine to re-run repeatedly — it creates unique users each time.

All of this is also written up in `e2e/README.md` and the `runbook`. If you'd like, I can add a couple of composer script shortcuts (e.g. composer test) so you don't have to remember the container invocation.

## Composer Shortcuts

| Command | What it does | Needs Docker? |
| ------- | ------------ | ------------- |
| composer test:unit	| DB-free Unit tier (fast) | No |
| composer stan | PHPStan (memory limit handled for you) | No |
| composer test	| Full 3-tier PHPUnit in the container | Yes (stack up)|
| composer test:db | Just Integration + Approval tiers | Yes |
| composer e2e | Playwright suite; args pass through, e.g. composer e2e -- --headed | Yes |
| composer e2e:install | One-time npm + Chromium install | — |
| composer db:reset | Wipe + re-seed the DB volume (clean fixtures) | Yes |
| composer ci	| Everything CI runs: stan → full PHPUnit → e2e	| Yes |

Each has a description visible via composer run-script --list. Typical loop: composer db:reset once, then composer test and composer e2e as you work — and reset again before composer test if you've run e2e in between (the fee-test pollution caveat, which this session reproduced and then cleared to confirm the reset shortcut works).

One note: composer stan and composer test:unit print some E_STRICT/deprecation noise ahead of their output — that's your PHP 8.5 composer binary complaining about itself, not the scripts. The actual test/analysis results are clean.