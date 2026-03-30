# Integration Tests (Tier 2)

These tests exercise library functions that require a live MySQL connection.
They use **transaction rollback** for isolation — every test rolls back at the
end, so no data ever commits and the baseline schema is always clean.

---

## Quick Start

### 1. Start the Docker MySQL container

From the project root:

```bash
docker-compose up -d db
```

Wait a few seconds for MySQL to be healthy (the first start also loads
`sql/bcoem_baseline_3.0.X.sql` which takes a moment).

Check it's up:

```bash
docker-compose ps
# db container should show "healthy"
```

### 2. Run the integration tests

```bash
./vendor/bin/phpunit --testsuite Integration
```

Or run Unit + Integration together:

```bash
./vendor/bin/phpunit
```

---

## Environment Variables

The test base class and the modified `site/config.php` both read credentials
from env vars. The defaults match `docker-compose.yml`:

| Variable              | Default         | Notes                              |
|-----------------------|-----------------|------------------------------------|
| `BCOEM_DB_HOST`       | `127.0.0.1`     | Use 127.0.0.1, NOT 'db' (that's the Docker network alias) |
| `BCOEM_DB_USER`       | `bcoem`         |                                    |
| `BCOEM_DB_PASSWORD`   | `bcoem_password`|                                    |
| `BCOEM_DB_NAME`       | `bcoem`         |                                    |
| `BCOEM_DB_PORT`       | `3306`          |                                    |
| `BCOEM_DB_PREFIX`     | `baseline_`     | Must match the prefix in your SQL schema |

Override any of these for a non-Docker MySQL:

```bash
BCOEM_DB_HOST=localhost BCOEM_DB_USER=myuser BCOEM_DB_PASSWORD=mypass \
BCOEM_DB_NAME=bcoem_test BCOEM_DB_PREFIX=baseline_ \
./vendor/bin/phpunit --testsuite Integration
```

---

## How Transaction Rollback Works

`IntegrationTestCase` wraps each test in a MySQL transaction:

```
setUp()     → $connection->begin_transaction()
test runs   → inserts data, calls library functions
tearDown()  → $connection->rollback()
```

Because the library functions call `require(CONFIG.'config.php')` internally,
the modified `site/config.php` guards connection creation — if `$connection`
is already set (which it is after `setUp()` populates `$GLOBALS`), it reuses
the existing connection instead of opening a new one. This keeps all queries
on the same transaction.

---

## Skipping Integration Tests Gracefully

If the Docker database is not running, all integration tests are
**automatically skipped** (not failed). PHPUnit will show:

```
S  (skipped)
```

with the message: `Integration DB unavailable (127.0.0.1:3306) — run: docker-compose up -d db`

This means `./vendor/bin/phpunit` can always be run safely, even without Docker.

---

## Test Files

| File                       | Functions Under Test              | Tables Used                         |
|----------------------------|-----------------------------------|-------------------------------------|
| `VerifyTokenTest.php`      | `verify_token()`                  | `users`                             |
| `DisplayPlaceTest.php`     | `display_place()`                 | *(none, just needs DB connection)*  |
| `BrewerInfoTest.php`       | `brewer_info()`                   | `users`, `brewer`                   |
| `TotalFeesTest.php`        | `total_fees()`                    | `users`, `brewer`, `brewing`        |
| `GetTableInfoTest.php`     | `get_table_info()`                | `judging_tables`, `judging_locations` |
| `BestBrewerPointsTest.php` | `best_brewer_points()`, `total_paid_received()` | `users`, `brewer`, `brewing` |

---

## Discovered Bugs Exercised by These Tests

See `Docs/characterization-test-findings.md` for full details. Tests that pin
known-buggy behavior are annotated in their docblocks.

Notable:
- `DisplayPlaceTest::testMethod4PlaceFourRendersForestGreenDueToDuplicateCase` — pins the duplicate `case "4":` bug in `display_place()` method 4.
