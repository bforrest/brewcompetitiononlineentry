# Phase 2 — Slim Shell + Central Authorization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce a single Slim 4 front controller in front of every existing route — legacy and new — with a central, deny-by-default authorization policy map (design spec success criterion 1). This is the upstream-divergence point: nothing in `sections/`, `admin/`, `includes/process/`, `lib/` changes behavior; they run exactly as today, just reached through one gate instead of dozens of independent entry points.

**Architecture:** A single front controller (`index.php`, ~10 lines) boots a Slim 4 app via PHP-DI. A middleware pipeline runs on every request (session → identity → **authorization**, deny-by-default) before anything else executes. Legacy pages/scripts are wrapped by thin bridge handlers that `require` the existing file — its output, `header()` calls, and `exit()` all behave exactly as they do today; the bridge's job is only to guarantee the authorization check ran first and that whatever must survive an in-script `exit()` (audit trail, tracing span close) does so via `register_shutdown_function()`. No attempt is made to convert legacy output into a "clean" PSR-7 response — that would require intercepting `header()`/`exit()`, which isn't reliably possible in userland PHP and isn't necessary for the goal (central authorization), only for a cosmetic uniformity Phase 3 doesn't need either.

**Tech Stack:** Slim 4 (`slim/slim`, `slim/psr7`), PHP-DI (`php-di/php-di`, `php-di/slim-bridge`), symfony/validator (installed now, used starting Phase 3), PHP 8.2, MariaDB 11, Docker.

## Global Constraints

- **Role hierarchy is the app's existing numeric `userLevel` convention, unchanged** — lower number = more privileged. Verified against real checks in the codebase (`process_users.inc.php:23,158`, `ajax/save.ajax.php:41,70`, `sections/brew.sec.php:14,66`, `admin/*.admin.php` gates found in the route inventory below): `0` = super-admin, `1` = admin (checks are almost always `userLevel <= 1`, i.e. 0 or 1 both pass), `2` = judge/steward (checks are `userLevel <= 2` when admins-and-judges both qualify, or `== 2` when judge-only), anything else (including `NULL`, which is what public registration leaves the column as) = entrant. A role "satisfies" a required role if its ordinal is `<=` the required ordinal — this is a direct, verified translation of the existing convention, not a new hierarchy.
- **`$logged_in` today is `isset($_SESSION['loginUsername'])`** (`includes/constants.inc.php:487`). `Identity::fromSession()` must use exactly this key, plus `$_SESSION['userLevel']`.
- **The session cookie name is `md5($installation_id)`** (or `md5(__FILE__)` if unset), set via `session_name()` in `paths.php:222,239` — `SessionMiddleware` must call `session_name()` with the same value before `session_start()`, or the bridge will silently start a *different* session than legacy code expects.
- **The `?admin=` GET parameter forces `index.legacy.php` rendering for an otherwise-public `$section`** (`index.php:177`, confirmed by Explore agent research 2026-07-19) — this is exactly why the policy map is keyed on request parameters (section/go/action), evaluated by middleware *before* either legacy render path runs, not on which file happens to execute. This ambiguity cannot cause a lockout-bypass because deny-by-default doesn't care which file would have rendered.
- **`includes/output.inc.php` is a self-bootstrapping dispatcher with no auth check of its own** — each of its five `output/*.output.php` targets checks independently. The policy map must declare each reachable `$section` value under `output.inc.php` individually (see Task 3), not rely on one blanket rule for the dispatcher file.
- **Composer additions** (append to existing `require`, keep `pixel418/markdownify`): `slim/slim:^4.14`, `slim/psr7:^1.7`, `php-di/php-di:^7.0`, `php-di/slim-bridge:^3.4`, `symfony/validator:^7.0`. Install inside the container: `docker compose exec web sh -c "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer"` (skip if already present from Phase 0), then `docker compose exec web composer require slim/slim:^4.14 slim/psr7:^1.7 php-di/php-di:^7.0 php-di/slim-bridge:^3.4 symfony/validator:^7.0`.
- **`vendor/` stops being committed to git in this phase** (design spec Section 6, now that CI installs it fresh every run per the Phase 0 CI gotcha) — Task 1 removes it from git tracking once the new dependencies are in, to stop bloating history with Slim/PHP-DI's dependency tree.
- New PHP code lives under `src/` (PSR-4 `Bcoem\`) and `config/`; `templates/` is Phase 3 (no page has been extracted into a template yet). Nothing under `src/` outside `src/Legacy/` may reference legacy globals (`$connection`, `$prefix`, `$_SESSION` directly) or call `common.lib.php` functions — enforce this with a PHPStan custom rule in the task that adds it (Task 9).
- **This phase's acceptance gate**: the full Phase 0/1 e2e suite (`cd e2e && npx playwright test`) and the full 3-tier PHPUnit suite must pass **identically** once the shell is in front of everything — same assertions, same commands, zero spec changes. Any spec change needed to make this phase pass is a signal the bridge changed observable behavior and must be treated as a bug, not accommodated in the test.
- Docker/DB/e2e conventions are unchanged from Phase 0/1 (`Docs/superpowers/plans/2026-07-18-phase0-e2e-safety-net.md` Global Constraints apply — app URL, DB creds, seeded admin, reseed command).

---

## Task Group A — Foundation: shell, identity, and the central gate

### Task 1: Composer scaffold + directory layout + "hello world" route

**Files:**
- Modify: `composer.json` (add deps, PSR-4 autoload for `Bcoem\`), `.gitignore` (stop ignoring nothing extra — vendor removal is a git-rm, not a gitignore change alone)
- Create: `src/Kernel/container.php`, `src/Kernel/app.php`, `tests/Unit/Kernel/HelloWorldRouteTest.php`

**Interfaces:**
- Produces: a working PHP-DI-backed Slim app object from `src/Kernel/app.php`, reusable by every later task in this group.

- [ ] **Step 1: Add dependencies**

```bash
docker compose exec web sh -c "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer" 2>/dev/null || true
docker compose exec web composer require slim/slim:^4.14 slim/psr7:^1.7 php-di/php-di:^7.0 php-di/slim-bridge:^3.4 symfony/validator:^7.0
```

- [ ] **Step 2: Add the `Bcoem\` PSR-4 autoload entry**

Add to `composer.json`'s top level (sibling to `autoload-dev`):

```json
    "autoload": {
        "psr-4": {
            "Bcoem\\": "src/"
        }
    },
```

```bash
docker compose exec web composer dump-autoload
```

- [ ] **Step 3: Write the container**

`src/Kernel/container.php`:

```php
<?php

declare(strict_types=1);

use DI\ContainerBuilder;

/**
 * PHP-DI container. Legacy globals (mysqli connection, table prefix) are
 * NOT wired here - src/Legacy/ reads them directly from $GLOBALS, exactly
 * as legacy code always has. Only genuinely new (Phase 3+) services get
 * container entries.
 */
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    // Populated starting Phase 3 (Bcoem\Database\Connection, Bcoem\Audit\AuditLogger, ...).
]);

return $containerBuilder->build();
```

- [ ] **Step 4: Write the Slim app factory**

`src/Kernel/app.php`:

```php
<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;
use Slim\App;

/**
 * Builds the Slim app. Does NOT run it - callers (index.php, tests) decide
 * whether to ->run() against real superglobals or ->handle() a constructed
 * PSR-7 request.
 */
function buildApp(): App
{
    $container = require __DIR__ . '/container.php';
    $app = Bridge::create($container);

    $app->get('/__kernel_hello', function ($request, $response) {
        $response->getBody()->write('ok');
        return $response;
    });

    return $app;
}
```

- [ ] **Step 5: Write the failing test**

`tests/Unit/Kernel/HelloWorldRouteTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once ROOT . 'src/Kernel/app.php';

class HelloWorldRouteTest extends TestCase
{
    public function test_kernel_hello_route_responds_ok(): void
    {
        $app = buildApp();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/__kernel_hello');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string)$response->getBody());
    }
}
```

- [ ] **Step 6: Run — verify it fails, then passes**

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit --filter HelloWorldRouteTest
```

Expected first run (before Steps 3-4 exist): class/function-not-found error. After implementing: `OK (1 test)`.

- [ ] **Step 6a: Extend PHPStan's scope to cover the new `src/` tree**

`phpstan.neon` currently restricts analysis to `paths: [lib]` (a deliberate prior scope decision — the rest of the legacy tree isn't gated yet). None of this phase's new code lives under `lib/`, so every later task's "run PHPStan, expect clean" step is meaningless unless `src/` is added now. Modify the **existing** `parameters:` block's `paths:` list (do not add a second `parameters:` key):

```yaml
    paths:
        - lib
        - src
```

```bash
docker compose exec web vendor/bin/phpstan analyse
```

Expected: `[OK] No errors` (nothing under `src/` exists yet besides what Steps 1-4 just added, which must already be clean).

- [ ] **Step 7: Stop committing `vendor/`**

```bash
git rm -r --cached vendor
echo "vendor/" >> .gitignore
docker compose exec web composer install   # confirm it still installs cleanly from composer.lock
```

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock .gitignore src/Kernel/container.php src/Kernel/app.php tests/Unit/Kernel/HelloWorldRouteTest.php
git commit -m "Scaffold Slim 4 + PHP-DI kernel; stop committing vendor/"
```

---

### Task 2: `Role` and `Identity` — pure, unit-tested

**Files:**
- Create: `src/Security/Role.php`, `src/Security/Identity.php`, `tests/Unit/Security/RoleTest.php`, `tests/Unit/Security/IdentityTest.php`

**Interfaces:**
- Produces: `Bcoem\Security\Role` (backed enum: `SuperAdmin=0, Admin=1, Judge=2, Entrant=3, Anonymous=100`) with `fromUserLevel(?string $userLevel): self` and `satisfies(Role $required): bool`. `Bcoem\Security\Identity` (readonly: `loggedIn`, `username`, `role`) with `fromSession(array $session): self`.

- [ ] **Step 1: Write the failing Role tests**

`tests/Unit/Security/RoleTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Security\Role;

class RoleTest extends TestCase
{
    public function test_from_user_level_maps_known_values(): void
    {
        $this->assertSame(Role::SuperAdmin, Role::fromUserLevel('0'));
        $this->assertSame(Role::Admin, Role::fromUserLevel('1'));
        $this->assertSame(Role::Judge, Role::fromUserLevel('2'));
    }

    public function test_from_user_level_null_is_entrant(): void
    {
        // Public registration leaves userLevel NULL in the DB.
        $this->assertSame(Role::Entrant, Role::fromUserLevel(null));
    }

    public function test_from_user_level_unknown_value_is_entrant(): void
    {
        $this->assertSame(Role::Entrant, Role::fromUserLevel('7'));
    }

    public function test_super_admin_satisfies_every_required_role(): void
    {
        $this->assertTrue(Role::SuperAdmin->satisfies(Role::SuperAdmin));
        $this->assertTrue(Role::SuperAdmin->satisfies(Role::Admin));
        $this->assertTrue(Role::SuperAdmin->satisfies(Role::Judge));
        $this->assertTrue(Role::SuperAdmin->satisfies(Role::Entrant));
        $this->assertTrue(Role::SuperAdmin->satisfies(Role::Anonymous));
    }

    public function test_admin_does_not_satisfy_super_admin_only_route(): void
    {
        $this->assertFalse(Role::Admin->satisfies(Role::SuperAdmin));
    }

    public function test_judge_satisfies_entrant_and_anonymous_but_not_admin(): void
    {
        $this->assertTrue(Role::Judge->satisfies(Role::Entrant));
        $this->assertTrue(Role::Judge->satisfies(Role::Anonymous));
        $this->assertFalse(Role::Judge->satisfies(Role::Admin));
    }

    public function test_anonymous_satisfies_only_anonymous(): void
    {
        $this->assertTrue(Role::Anonymous->satisfies(Role::Anonymous));
        $this->assertFalse(Role::Anonymous->satisfies(Role::Entrant));
    }
}
```

- [ ] **Step 2: Run — verify it fails**

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit --filter RoleTest
```

Expected: class `Bcoem\Security\Role` not found.

- [ ] **Step 3: Implement `src/Security/Role.php`**

```php
<?php

declare(strict_types=1);

namespace Bcoem\Security;

enum Role: int
{
    case SuperAdmin = 0;
    case Admin = 1;
    case Judge = 2;
    case Entrant = 3;
    case Anonymous = 100;

    public static function fromUserLevel(?string $userLevel): self
    {
        // Fail safe to Entrant for anything that isn't a clean non-negative
        // integer string - PHP's (int) cast turns '', 'abc', etc. into 0,
        // which would otherwise silently escalate a malformed session value
        // to SuperAdmin. Deny-by-default groundwork must not have this hole.
        if ($userLevel === null || !ctype_digit($userLevel)) {
            return self::Entrant;
        }
        return match ((int)$userLevel) {
            0 => self::SuperAdmin,
            1 => self::Admin,
            2 => self::Judge,
            default => self::Entrant,
        };
    }

    /**
     * True if this role grants at least as much privilege as $required,
     * using the app's existing numeric userLevel convention: lower value
     * = more privileged. Anonymous only satisfies an Anonymous requirement.
     */
    public function satisfies(Role $required): bool
    {
        if ($required === self::Anonymous) {
            return true;
        }
        if ($this === self::Anonymous) {
            return false;
        }
        return $this->value <= $required->value;
    }
}
```

- [ ] **Step 4: Run — verify Role tests pass**

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit --filter RoleTest
```

Expected: `OK (7 tests)`.

- [ ] **Step 5: Write the failing Identity tests**

`tests/Unit/Security/IdentityTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Security\Identity;
use Bcoem\Security\Role;

class IdentityTest extends TestCase
{
    public function test_no_loginUsername_is_anonymous(): void
    {
        $identity = Identity::fromSession([]);
        $this->assertFalse($identity->loggedIn);
        $this->assertNull($identity->username);
        $this->assertSame(Role::Anonymous, $identity->role);
    }

    public function test_loginUsername_present_is_logged_in_with_mapped_role(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'admin@example.com', 'userLevel' => '1']);
        $this->assertTrue($identity->loggedIn);
        $this->assertSame('admin@example.com', $identity->username);
        $this->assertSame(Role::Admin, $identity->role);
    }

    public function test_loginUsername_present_without_userLevel_is_entrant(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'entrant@example.com']);
        $this->assertSame(Role::Entrant, $identity->role);
    }
}
```

- [ ] **Step 6: Run — verify it fails, implement, verify it passes**

`src/Security/Identity.php`:

```php
<?php

declare(strict_types=1);

namespace Bcoem\Security;

final class Identity
{
    private function __construct(
        public readonly bool $loggedIn,
        public readonly ?string $username,
        public readonly Role $role,
    ) {
    }

    /** @param array<string, mixed> $session */
    public static function fromSession(array $session): self
    {
        if (!isset($session['loginUsername'])) {
            return new self(false, null, Role::Anonymous);
        }
        return new self(
            true,
            (string)$session['loginUsername'],
            Role::fromUserLevel(isset($session['userLevel']) ? (string)$session['userLevel'] : null)
        );
    }
}
```

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit --filter "RoleTest|IdentityTest"
```

Expected: `OK (10 tests)`.

- [ ] **Step 7: Commit**

```bash
git add src/Security/Role.php src/Security/Identity.php tests/Unit/Security/
git commit -m "Add Role and Identity value objects (deny-by-default groundwork)"
```

---

### Task 3: The central policy map (`config/access_policy.php`) + `AccessPolicy` resolver

This is the centerpiece of Phase 2's success criterion. **Every legacy route, side door, and process action found in the 2026-07-19 route inventory is declared here.** Anything not declared is denied by `AuthorizationMiddleware` (Task 4) — that is the entire point.

**Files:**
- Create: `config/access_policy.php`, `src/Security/AccessPolicy.php`, `tests/Unit/Security/AccessPolicyTest.php`

**Interfaces:**
- Produces: `Bcoem\Security\AccessPolicy::fromFile(string $path): self`, `->requiredRoleFor(string $section, ?string $go, ?string $action): ?Role` (GET/legacy-page lookups), `->requiredRoleForProcessAction(?string $action, ?string $dbTable): ?Role` (POST/process.inc.php lookups), `->requiredRoleForFile(string $filename): ?Role` (side-door lookups). `null` return = **not declared = deny**; this is consumed by `AuthorizationMiddleware` in Task 4.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Security/AccessPolicyTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Security\AccessPolicy;
use Bcoem\Security\Role;

class AccessPolicyTest extends TestCase
{
    private function policy(): AccessPolicy
    {
        return AccessPolicy::fromFile(ROOT . 'config/access_policy.php');
    }

    public function test_admin_section_base_requires_admin(): void
    {
        $this->assertSame(Role::Admin, $this->policy()->requiredRoleFor('admin', null, null));
    }

    public function test_admin_go_styles_requires_super_admin_more_specific_than_base(): void
    {
        $this->assertSame(Role::SuperAdmin, $this->policy()->requiredRoleFor('admin', 'styles', null));
    }

    public function test_account_page_requires_entrant(): void
    {
        $this->assertSame(Role::Entrant, $this->policy()->requiredRoleFor('list', null, null));
    }

    public function test_public_page_requires_anonymous(): void
    {
        $this->assertSame(Role::Anonymous, $this->policy()->requiredRoleFor('contact', null, null));
    }

    public function test_undeclared_section_is_denied(): void
    {
        $this->assertNull($this->policy()->requiredRoleFor('this-section-does-not-exist', null, null));
    }

    public function test_process_login_action_is_anonymous(): void
    {
        $this->assertSame(Role::Anonymous, $this->policy()->requiredRoleForProcessAction('login', null));
    }

    public function test_process_dbtable_users_requires_entrant(): void
    {
        $this->assertSame(Role::Entrant, $this->policy()->requiredRoleForProcessAction(null, 'baseline_users'));
    }

    public function test_undeclared_process_action_is_denied(): void
    {
        $this->assertNull($this->policy()->requiredRoleForProcessAction('no-such-action', null));
    }

    public function test_qr_side_door_is_anonymous(): void
    {
        $this->assertSame(Role::Anonymous, $this->policy()->requiredRoleForFile('qr.php'));
    }

    public function test_ppv_webhook_is_anonymous(): void
    {
        $this->assertSame(Role::Anonymous, $this->policy()->requiredRoleForFile('ppv.php'));
    }

    public function test_undeclared_file_is_denied(): void
    {
        $this->assertNull($this->policy()->requiredRoleForFile('some_new_side_door.php'));
    }
}
```

- [ ] **Step 2: Run — verify it fails**

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit --filter AccessPolicyTest
```

Expected: class not found.

- [ ] **Step 3: Write `config/access_policy.php`**

This is built from the 2026-07-19 route inventory (see the plan's "Cherry-pick reconciliation"-style research notes — reproduced findings below). Keys use three namespaces: `section:` (GET, legacy pages — resolved most-specific-first as `section:{s}|go:{g}|action:{a}` → `section:{s}|go:{g}` → `section:{s}`), `process:action:` / `process:dbTable:` (POST via `includes/process.inc.php`), and `file:` (self-bootstrapping side doors, keyed by basename).

```php
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
    'section:admin' => Role::Admin, // already declared above; listed once, do not duplicate the key

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
```

- [ ] **Step 4: Implement `src/Security/AccessPolicy.php`**

```php
<?php

declare(strict_types=1);

namespace Bcoem\Security;

final class AccessPolicy
{
    /** @param array<string, Role> $map */
    private function __construct(private readonly array $map)
    {
    }

    public static function fromFile(string $path): self
    {
        /** @var array<string, Role> $map */
        $map = require $path;
        return new self($map);
    }

    /** Most-specific-first: section+go+action, then section+go, then section alone. */
    public function requiredRoleFor(string $section, ?string $go, ?string $action): ?Role
    {
        if ($go !== null && $action !== null) {
            $key = "section:{$section}|go:{$go}|action:{$action}";
            if (isset($this->map[$key])) {
                return $this->map[$key];
            }
        }
        if ($go !== null) {
            $key = "section:{$section}|go:{$go}";
            if (isset($this->map[$key])) {
                return $this->map[$key];
            }
        }
        return $this->map["section:{$section}"] ?? null;
    }

    public function requiredRoleForProcessAction(?string $action, ?string $dbTable): ?Role
    {
        if ($action !== null && $action !== 'default') {
            return $this->map["process:action:{$action}"] ?? null;
        }
        if ($dbTable !== null && $dbTable !== 'default') {
            return $this->map["process:dbTable:{$dbTable}"] ?? null;
        }
        return null;
    }

    public function requiredRoleForFile(string $filename): ?Role
    {
        return $this->map["file:{$filename}"] ?? null;
    }

    public function requiredRoleForOutputSection(string $section): ?Role
    {
        return $this->map["output:section:{$section}"] ?? null;
    }
}
```

- [ ] **Step 5: Run — verify tests pass**

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit --filter AccessPolicyTest
```

Expected: `OK (11 tests)`.

- [ ] **Step 6: Commit**

```bash
git add config/access_policy.php src/Security/AccessPolicy.php tests/Unit/Security/AccessPolicyTest.php
git commit -m "Add the central deny-by-default access policy map"
```

---

### Task 3a: Close out the VERIFY entries in the policy map

**Files:**
- Modify: `config/access_policy.php` (remove `// VERIFY` comments as each is confirmed; correct the role if the file's actual check differs from the placeholder `Anonymous` guess)

**Interfaces:**
- Consumes: nothing new.
- Produces: a policy map with zero `// VERIFY` markers remaining — this is a **release gate for this phase**, not optional cleanup.

- [ ] **Step 1: Check each flagged `section:*` value against its rendering file**

For each `// VERIFY` line in `config/access_policy.php`, run:

```bash
grep -n "logged_in\|userLevel\|loginUsername" "sections/<name>.sec.php"
```

(Where `<name>` is the section value — e.g. `sections/judge.sec.php` for `section:judge`. Recall from the route inventory that most of these public-looking sections actually render inline inside `index.pub.php` rather than via a `sections/*.sec.php` include for the non-admin path — check `index.pub.php`'s own per-`$section` blocks first with `grep -n '\$section == "<name>"' index.pub.php`, and fall back to the `sections/*.sec.php` file only if `index.pub.php` doesn't handle it directly.) Update the policy entry's role to match whatever check (if any) currently guards that content, and delete the `// VERIFY` comment.

- [ ] **Step 2: Enumerate and declare `includes/output.inc.php`'s sections**

```bash
sed -n '25,32p' includes/output.inc.php
```

For each value found in `$print_sections`/`$export_sections`/`$label_sections`/`$entry_sections`/`$scoresheet_sections`, confirm its target file's check (`output/print.output.php:9`, `output/export.output.php:50`, `output/labels.output.php:4`, `output/scoresheets.output.php:19` are already known to have one; there may be more) and add one `'output:section:{value}' => Role::X` entry per value.

- [ ] **Step 3: Re-run the AccessPolicy test suite and grep for remaining markers**

```bash
grep -c "VERIFY" config/access_policy.php   # must be 0
docker compose exec web vendor/bin/phpunit --testsuite Unit --filter AccessPolicyTest
```

- [ ] **Step 4: Commit**

```bash
git add config/access_policy.php
git commit -m "Close out VERIFY entries in the access policy map"
```

---

### Task 4: `AuthorizationMiddleware` — the enforcement point

**Files:**
- Create: `src/Kernel/Middleware/AuthorizationMiddleware.php`, `tests/Unit/Kernel/Middleware/AuthorizationMiddlewareTest.php`

**Interfaces:**
- Consumes: `Bcoem\Security\AccessPolicy`, `Bcoem\Security\Identity`, `Bcoem\Security\Role`.
- Produces: a PSR-15 `MiddlewareInterface` that reads `Identity` from the PSR-7 request attribute `identity` (set by a later `AuthenticationMiddleware` — until that exists, tests inject it directly via `->withAttribute('identity', ...)`), resolves the required role via section/go/action or `process:`/`file:` query/route attributes, and either calls the next handler or returns a 403.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Kernel/Middleware/AuthorizationMiddlewareTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Kernel\Middleware\AuthorizationMiddleware;
use Bcoem\Security\AccessPolicy;
use Bcoem\Security\Identity;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

final class StubHandler implements RequestHandlerInterface
{
    public bool $called = false;
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->called = true;
        return (new ResponseFactory())->createResponse(200);
    }
}

class AuthorizationMiddlewareTest extends TestCase
{
    private function policy(): AccessPolicy
    {
        return AccessPolicy::fromFile(ROOT . 'config/access_policy.php');
    }

    public function test_anonymous_may_reach_a_public_section(): void
    {
        $middleware = new AuthorizationMiddleware($this->policy());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/index.php?section=contact')
            ->withQueryParams(['section' => 'contact'])
            ->withAttribute('identity', Identity::fromSession([]));
        $next = new StubHandler();

        $response = $middleware->process($request, $next);

        $this->assertTrue($next->called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_anonymous_is_denied_the_admin_section(): void
    {
        $middleware = new AuthorizationMiddleware($this->policy());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/index.php?section=admin')
            ->withQueryParams(['section' => 'admin'])
            ->withAttribute('identity', Identity::fromSession([]));
        $next = new StubHandler();

        $response = $middleware->process($request, $next);

        $this->assertFalse($next->called);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_entrant_is_denied_a_super_admin_only_go(): void
    {
        $middleware = new AuthorizationMiddleware($this->policy());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/index.php?section=admin&go=styles')
            ->withQueryParams(['section' => 'admin', 'go' => 'styles'])
            ->withAttribute('identity', Identity::fromSession(['loginUsername' => 'e@example.com', 'userLevel' => '3']));
        $next = new StubHandler();

        $response = $middleware->process($request, $next);

        $this->assertFalse($next->called);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_super_admin_may_reach_a_super_admin_only_go(): void
    {
        $middleware = new AuthorizationMiddleware($this->policy());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/index.php?section=admin&go=styles')
            ->withQueryParams(['section' => 'admin', 'go' => 'styles'])
            ->withAttribute('identity', Identity::fromSession(['loginUsername' => 'a@example.com', 'userLevel' => '0']));
        $next = new StubHandler();

        $response = $middleware->process($request, $next);

        $this->assertTrue($next->called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_undeclared_section_is_denied_fail_closed(): void
    {
        $middleware = new AuthorizationMiddleware($this->policy());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/index.php?section=brand-new-undeclared-section')
            ->withQueryParams(['section' => 'brand-new-undeclared-section'])
            ->withAttribute('identity', Identity::fromSession(['loginUsername' => 'a@example.com', 'userLevel' => '0']));
        $next = new StubHandler();

        $response = $middleware->process($request, $next);

        // Even a super-admin is denied - undeclared means denied, no exceptions.
        $this->assertFalse($next->called);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_process_route_checks_process_action_attribute(): void
    {
        $middleware = new AuthorizationMiddleware($this->policy());
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/includes/process.inc.php?action=login')
            ->withQueryParams(['action' => 'login'])
            ->withAttribute('routeType', 'process')
            ->withAttribute('identity', Identity::fromSession([]));
        $next = new StubHandler();

        $response = $middleware->process($request, $next);

        $this->assertTrue($next->called);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_file_route_checks_file_attribute(): void
    {
        $middleware = new AuthorizationMiddleware($this->policy());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/qr.php')
            ->withAttribute('routeType', 'file')
            ->withAttribute('routeFile', 'qr.php')
            ->withAttribute('identity', Identity::fromSession([]));
        $next = new StubHandler();

        $response = $middleware->process($request, $next);

        $this->assertTrue($next->called);
        $this->assertSame(200, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Run — verify it fails**

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit --filter AuthorizationMiddlewareTest
```

Expected: class not found.

- [ ] **Step 3: Implement**

`src/Kernel/Middleware/AuthorizationMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace Bcoem\Kernel\Middleware;

use Bcoem\Security\AccessPolicy;
use Bcoem\Security\Identity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

final class AuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AccessPolicy $policy)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Identity $identity */
        $identity = $request->getAttribute('identity');
        $routeType = $request->getAttribute('routeType', 'section');

        $required = match ($routeType) {
            'process' => $this->policy->requiredRoleForProcessAction(
                $request->getQueryParams()['action'] ?? null,
                $request->getQueryParams()['dbTable'] ?? null,
            ),
            'file' => $this->policy->requiredRoleForFile((string)$request->getAttribute('routeFile')),
            'output' => $this->policy->requiredRoleForOutputSection(
                (string)($request->getQueryParams()['section'] ?? '')
            ),
            default => $this->policy->requiredRoleFor(
                (string)($request->getQueryParams()['section'] ?? 'default'),
                $request->getQueryParams()['go'] ?? null,
                $request->getQueryParams()['action'] ?? null,
            ),
        };

        if ($required === null || !$identity->role->satisfies($required)) {
            $response = (new ResponseFactory())->createResponse(403);
            $response->getBody()->write('Forbidden');
            return $response;
        }

        return $handler->handle($request);
    }
}
```

- [ ] **Step 4: Run — verify it passes**

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit --filter AuthorizationMiddlewareTest
```

Expected: `OK (7 tests)`.

- [ ] **Step 5: Commit**

```bash
git add src/Kernel/Middleware/AuthorizationMiddleware.php tests/Unit/Kernel/Middleware/
git commit -m "Add AuthorizationMiddleware: the central deny-by-default enforcement point"
```

---

### Task 5: `SessionMiddleware` + `AuthenticationMiddleware` — wire real legacy session state in

**Files:**
- Create: `src/Kernel/Middleware/SessionMiddleware.php`, `src/Kernel/Middleware/AuthenticationMiddleware.php`, `tests/Unit/Kernel/Middleware/AuthenticationMiddlewareTest.php`
- Modify: `src/Kernel/app.php` (register the middleware pipeline in order)

**Interfaces:**
- Produces: `SessionMiddleware` starts the legacy-named PHP session (matching `paths.php`'s `session_name($prefix_session)` exactly) before anything else runs. `AuthenticationMiddleware` reads `$_SESSION` (real superglobal - legacy code and this middleware share the same session by design, unlike a from-scratch Slim session handler) and attaches an `Identity` to the request via `->withAttribute('identity', ...)`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Kernel/Middleware/AuthenticationMiddlewareTest.php`:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Kernel\Middleware\AuthenticationMiddleware;
use Bcoem\Security\Role;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class AuthenticationMiddlewareTest extends TestCase
{
    public function test_attaches_identity_from_session_superglobal(): void
    {
        $_SESSION = ['loginUsername' => 'admin@example.com', 'userLevel' => '1'];
        $middleware = new AuthenticationMiddleware();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/index.php');

        $captured = null;
        $next = new class($captured) implements RequestHandlerInterface {
            public function __construct(public mixed &$captured) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request->getAttribute('identity');
                return (new ResponseFactory())->createResponse(200);
            }
        };

        $middleware->process($request, $next);

        $this->assertTrue($next->captured->loggedIn);
        $this->assertSame(Role::Admin, $next->captured->role);
    }
}
```

- [ ] **Step 2: Run — verify it fails**

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit --filter AuthenticationMiddlewareTest
```

- [ ] **Step 3: Implement both middlewares**

`src/Kernel/Middleware/SessionMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace Bcoem\Kernel\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Starts the SAME named session legacy code expects (paths.php:222,239 uses
 * md5($installation_id) or md5(__FILE__) as the session name) BEFORE any
 * legacy bridge runs, so $_SESSION is populated identically to today. Legacy
 * code's own session_start() calls become no-ops (PHP_SESSION_ACTIVE guard
 * already present throughout the codebase).
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Mirror paths.php:222-223's exact branching: empty() (not ??)
            // treats '' the same as unset, and the fallback is __FILE__ (this
            // middleware's own file, standing in for paths.php's __FILE__),
            // not __DIR__. site/config.php's shipped default sets
            // $installation_id = '' - since '' is set but empty, a bare ??
            // would compute md5('') here while paths.php computes
            // md5(__FILE__), starting a DIFFERENT session than legacy code.
            $installationId = $GLOBALS['installation_id'] ?? '';
            if (empty($installationId)) $installationId = __FILE__;
            session_name(md5($installationId));
            session_start();
        }
        return $handler->handle($request);
    }
}
```

`src/Kernel/Middleware/AuthenticationMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace Bcoem\Kernel\Middleware;

use Bcoem\Security\Identity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthenticationMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identity = Identity::fromSession($_SESSION ?? []);
        return $handler->handle($request->withAttribute('identity', $identity));
    }
}
```

- [ ] **Step 4: Wire the pipeline order into `src/Kernel/app.php`**

Add, after `buildApp()`'s container/app creation and before the `__kernel_hello` route:

```php
    $app->add(new \Bcoem\Kernel\Middleware\AuthorizationMiddleware(
        \Bcoem\Security\AccessPolicy::fromFile(__DIR__ . '/../../config/access_policy.php')
    ));
    $app->add(new \Bcoem\Kernel\Middleware\AuthenticationMiddleware());
    $app->add(new \Bcoem\Kernel\Middleware\SessionMiddleware());
```

(Slim executes `add()`-registered middleware in **reverse** registration order for the request phase — outermost-added runs first. Registering Session → Authentication → Authorization in this order means Authorization's `process()` body runs *last* going in, i.e. Session starts first, then Authentication attaches identity, then Authorization enforces - exactly the pipeline order the design spec specifies.)

- [ ] **Step 5: Run — verify it passes**

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit --filter AuthenticationMiddlewareTest
```

Expected: `OK (1 test)`.

- [ ] **Step 6: Commit**

```bash
git add src/Kernel/Middleware/SessionMiddleware.php src/Kernel/Middleware/AuthenticationMiddleware.php src/Kernel/app.php tests/Unit/Kernel/Middleware/AuthenticationMiddlewareTest.php
git commit -m "Add Session and Authentication middleware; wire the pipeline order"
```

---

### Task 6: `LegacyPageHandler` — bridge the GET (`index.php?section=...`) flow

**Files:**
- Create: `src/Legacy/LegacyBootstrap.php`, `src/Legacy/LegacyPageHandler.php`, `tests/Integration/LegacyPageHandlerTest.php`
- Modify: `paths.php`, `lib/update.lib.php`, `tests/bootstrap.php`, `site/bootstrap.php` (Step 0 — a prerequisite fix, discovered while implementing this task, not anticipated when this plan was written)

**Interfaces:**
- Consumes: `AuthorizationMiddleware` has already run (denied requests never reach this handler).
- Produces: `Bcoem\Legacy\LegacyPageHandler::__invoke($request, $response, $args)` — a Slim route callable that `require`s the real `index.php` from within the app root, letting it run exactly as it does today (its own `header()`/`exit()` calls take effect on the real SAPI, unmodified).

- [ ] **Step 0: Fix a test-bootstrap/legacy-code function collision (prerequisite)**

`tests/bootstrap.php` (PHPUnit's shared bootstrap, loaded once before any test) stubs `is_https()` and `sterilize()` behind `function_exists()` guards, so narrow Unit-tier tests can load `common.lib.php` without the full legacy bootstrap. `paths.php` declares both of those same functions **unconditionally**. Every test up to this point only ever loaded `common.lib.php` directly (never `paths.php`), so this never collided — but this task's Integration test is the first to load the *full* `index.php` → `paths.php` chain, and PHP fatals with `Cannot redeclare is_https()` the moment it does, since `tests/bootstrap.php`'s stub already claimed the name first.

Fix by mirroring `tests/bootstrap.php`'s own guard pattern in `paths.php` — wrap both declarations:

```php
if (!function_exists('is_https')) {
function is_https() {
    if (((!empty($_SERVER['HTTPS'])) && (strtolower($_SERVER['HTTPS']) !== "off")) || ((isset($_SERVER['SERVER_PORT'])) && ($_SERVER['SERVER_PORT'] === "443"))) return TRUE;
    elseif (((!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) && (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == "https")) || ((!empty($_SERVER['HTTP_X_FORWARDED_SSL'])) && (strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) == "on"))) return TRUE;
    else return FALSE;
}
}
```

and

```php
if (!function_exists('sterilize')) {
function sterilize($sterilize = NULL) {
    if ($sterilize == NULL) return NULL;
    elseif (empty($sterilize)) return $sterilize;
    else {
        $sterilize = trim($sterilize);
        if (is_numeric($sterilize)) {
            if (is_float($sterilize)) $sterilize = filter_var($sterilize,FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION);
            if (is_int($sterilize)) {
                if ($sterilize == 0) $sterilize = 0;
                else $sterilize = filter_var($sterilize,FILTER_SANITIZE_NUMBER_INT);
            }            
        }
        else $sterilize = filter_var($sterilize,FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $sterilize = strip_tags($sterilize);
        $sterilize = stripcslashes($sterilize);
        $sterilize = stripslashes($sterilize);
        $sterilize = addslashes($sterilize);
        return $sterilize;
    }
}
}
```

`tests/bootstrap.php` stubs exactly three functions total (confirmed by grepping it for every `function_exists()`-guarded declaration): `sterilize`, `is_https` (both above, in `paths.php`), and **`check_setup`** — one more hop down the same `index.php` chain (`index.php` → `site/bootstrap.php` → `preflight.lib.php` → `lib/update.lib.php`, which declares `check_setup()` unconditionally). Fix that one too, in `lib/update.lib.php`:

```php
<?php
if (!function_exists('check_setup')) {
function check_setup($tablename, $database) {
	
	require(CONFIG.'config.php');
	mysqli_select_db($connection,$database);
	
	$query_log = sprintf("SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = '%s' AND table_name = '%s'", $database, $tablename);
	$log = mysqli_query($connection,$query_log) or die (mysqli_error($connection));
	$row_log = mysqli_fetch_assoc($log);

	if ($row_log['count'] == 0) return FALSE;
	else return TRUE;

}
}
```

(Only the `check_setup` declaration itself gets wrapped — everything else already in `lib/update.lib.php`, including the sibling `check_update()` function right after it, is untouched.) This closes every *redeclaration* collision between `tests/bootstrap.php`'s stubs and the real legacy files — but `check_setup()` surfaces a second, different problem once the guard is in place: the stub unconditionally `return true`s regardless of which table is asked about, while the real `lib/preflight.lib.php` calls it for **two different table names** — the legacy `{prefix}system` (pre-rename) and the current `{prefix}bcoem_sys` — expecting a real, table-specific answer so it can pick the right code branch. This baseline schema only has `bcoem_sys` (`sql/bcoem_baseline_3.0.X.sql` — `baseline_system` doesn't exist, only `baseline_bcoem_sys` does). The blind `true` stub lies to `preflight.lib.php`, which then queries the (non-existent) legacy `system` table for real and gets an uncaught `mysqli_sql_exception`. This never mattered for any test before this one, because no prior test loaded the full `index.php` → `preflight.lib.php` chain where the two-table distinction is actually exercised.

Fix by making the stub delegate to a real schema check whenever a real DB connection is available (exactly the connection `IntegrationTestCase::setUp()` already exposes via `$GLOBALS['connection']` for narrow library-function tests), falling back to the old blind `true` only when there's no connection at all (pure Unit tier, which never touches a DB and doesn't care about the real answer). In `tests/bootstrap.php`, replace:

```php
if (!function_exists('check_setup')) {
    function check_setup($table, $database) {
        return true; // Stub: assume tables exist in test context
    }
}
```

with:

```php
if (!function_exists('check_setup')) {
    function check_setup($table, $database) {
        if ((isset($GLOBALS['connection'])) && ($GLOBALS['connection'] instanceof mysqli)) {
            $conn = $GLOBALS['connection'];
            $tableEscaped = $conn->real_escape_string($table);
            $dbEscaped = $conn->real_escape_string($database);
            $result = $conn->query("SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = '{$dbEscaped}' AND table_name = '{$tableEscaped}'");
            $row = $result->fetch_assoc();
            return $row['count'] > 0;
        }
        return true; // Stub: no DB connection available (pure Unit tier) - assume tables exist.
    }
}
```

This preserves the existing behavior for every Unit-tier test (no `$GLOBALS['connection']` ⇒ still blindly `true`, unchanged) while giving Integration-tier tests — which DO have a real connection — the actual, correct, per-table answer `preflight.lib.php` needs.

**One more, different-in-kind fix, found once the three stub/redeclaration issues above were resolved:** `tests/bootstrap.php:108` does `require_once LIB.'common.lib.php';` so Unit tests get every function in it without loading the rest of the app — but `site/bootstrap.php:60` re-loads the same file with a plain `require (LIB.'common.lib.php');` (not `require_once`). In a real single-request production process this is harmless (the file is never actually loaded twice), but in PHPUnit's single shared process it means `site/bootstrap.php` tries to redeclare every function in `common.lib.php` (`csrf_token_generate()`, etc.) the moment an Integration test loads the full `index.php` chain a second time in the same run. Fix in `site/bootstrap.php`:

```php
	require_once (LIB.'common.lib.php');
```

(Change only that one line — line 60 — from `require` to `require_once`. Leave the other `require` calls in this file as-is; they don't collide with anything `tests/bootstrap.php` preloads.)

This is the complete set of fixes needed for this chain — all three of `tests/bootstrap.php`'s stubs now either have a matching guard at their real declaration site (`is_https`, `sterilize`) or correct, connection-aware behavior (`check_setup`), and the one file `tests/bootstrap.php` itself preloads (`common.lib.php`) is now safe to re-encounter later in the same chain. No further surprise of this kind should surface in Task 6 — but Task 7 loads a *different* legacy entry point (`includes/process.inc.php`) with its own independent require chain that ALSO plain-`include`s `common.lib.php` and `update.lib.php`; that task's own Step 0 addresses it (same root cause, same fix pattern, called out there rather than here since Task 6 doesn't touch `process.inc.php` at all).

This is purely additive (the guard only matters when the function is already declared, which happens only under the PHPUnit bootstrap; normal production requests never hit the `false` branch) and does not change any function's behavior. It also unblocks Task 7's `LegacyProcessHandlerTest`, which loads the same `paths.php`/`update.lib.php` chain via `includes/process.inc.php` and would hit the identical collisions.

Run the full Unit suite afterward to confirm the guard doesn't change anything for the existing tests that rely on the stub:

```bash
docker compose exec web vendor/bin/phpunit --testsuite Unit
```

Expected: same pass count as before this change (the stub still wins when it runs first, exactly as today).

- [ ] **Step 1: Write `LegacyBootstrap`**

`src/Legacy/LegacyBootstrap.php`:

```php
<?php

declare(strict_types=1);

namespace Bcoem\Legacy;

/**
 * Legacy pages assume they're being run FROM the repo root with paths.php
 * already required and $_GET populated. This class's only job is to make
 * that assumption true when a Slim route (not a direct file hit) is what's
 * actually serving the request. Throwaway - deleted page by page as Phase 3
 * migrates each workflow into src/Domain/.
 */
final class LegacyBootstrap
{
    public static function requireRootFile(string $filename): void
    {
        chdir(ROOT);
        require ROOT . $filename;
    }
}
```

- [ ] **Step 2: Write `LegacyPageHandler`**

`src/Legacy/LegacyPageHandler.php`:

```php
<?php

declare(strict_types=1);

namespace Bcoem\Legacy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Bridges GET requests to the existing index.php flow. index.php ends with
 * its own header()/exit()-equivalent (mysqli_close + falls off the end,
 * echoing HTML directly) - this handler does NOT try to capture that into a
 * PSR-7 response body; it lets index.php write directly to the output
 * buffer PHP's SAPI already manages, and returns Slim's response unmodified
 * (Slim's own emitter no-ops on top of output that's already been sent).
 * Anything that MUST run even if index.php calls exit() mid-script
 * (AuditMiddleware's post-processing, once Phase 3 needs it) is registered
 * via register_shutdown_function(), never relied on to run "after" this
 * method returns.
 */
final class LegacyPageHandler
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        foreach ($request->getQueryParams() as $key => $value) {
            $_GET[$key] = $value;
        }
        LegacyBootstrap::requireRootFile('index.php');
        return $response;
    }
}
```

- [ ] **Step 3: Write the integration test**

`tests/Integration/LegacyPageHandlerTest.php` (needs the real DB, since `index.php` touches it):

```php
<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration;

use Bcoem\Legacy\LegacyPageHandler;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class LegacyPageHandlerTest extends IntegrationTestCase
{
    public function test_contact_section_renders_via_legacy_bridge(): void
    {
        $_GET = ['section' => 'contact'];
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/index.php?section=contact')
            ->withQueryParams(['section' => 'contact']);
        $response = (new ResponseFactory())->createResponse(200);

        ob_start();
        $handler = new LegacyPageHandler();
        $handler($request, $response);
        $output = ob_get_clean();

        $this->assertStringContainsString('Brew Competition Online Entry', $output);
    }
}
```

- [ ] **Step 4: Run — this test exercises real legacy code end-to-end, so failure modes vary**

```bash
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Integration --filter LegacyPageHandlerTest
```

If it fails with a header-already-sent warning or similar, that's the "headers already sent" risk called out in the design spec's Risks section — investigate whether `index.php`'s own headers (sent before this test's PHPUnit process would have sent any) are the cause; PHPUnit's CLI SAPI doesn't enforce header-already-sent the way a real web request does, so this is expected to pass in the test environment even though the *production* path (Task 6a below) needs its own manual verification.

- [ ] **Step 5: Manually verify against the real running stack (headers matter here, unlike the PHPUnit CLI context)**

This can't be fully proven by an automated test until Task 9 rewrites `.htaccess` and Task 10 runs the full e2e gate — defer full confidence to those tasks, but sanity-check now:

```bash
docker compose exec web php -r '
define("ROOT", getcwd() . "/");
require "src/Legacy/LegacyBootstrap.php";
$_GET = ["section" => "contact"];
$_SERVER["REQUEST_METHOD"] = "GET";
Bcoem\Legacy\LegacyBootstrap::requireRootFile("index.php");
' 2>&1 | head -20
```

**Do not pre-`require "paths.php"` at the top level of this snippet** (an earlier draft of this step did, and it's wrong): `LegacyBootstrap::requireRootFile()` is a static method, and PHP's `require` executes in the scope of wherever it's textually called — so if `paths.php` were required at the *top level* of this one-liner first, all the variables it sets (`$connection`, `$prefix`, etc.) would live in the script's global scope, not inside `requireRootFile()`'s method scope. Then when `index.php` (running *inside* that method) does its own `require_once('paths.php')`, `require_once` would see the file is already loaded and skip it entirely — silently leaving `$connection`/`$prefix`/etc. undefined in the scope `index.php` is actually running in. Defining only the bare `ROOT` constant (constants are scope-independent in PHP, unlike variables) and letting `index.php`'s own `require_once('paths.php')` be the one-and-only real execution of `paths.php` — inside `requireRootFile()`'s scope, exactly like the real `LegacyPageHandler` flow — avoids the mismatch entirely.

Expected: HTML output, no PHP fatal errors.

- [ ] **Step 6: Commit**

```bash
git add src/Legacy/LegacyBootstrap.php src/Legacy/LegacyPageHandler.php tests/Integration/LegacyPageHandlerTest.php
git commit -m "Add LegacyPageHandler: bridge GET requests to the existing index.php flow"
```

---

### Task 7: `LegacyProcessHandler` — bridge the POST (`includes/process.inc.php`) flow

**Files:**
- Create: `src/Legacy/LegacyProcessHandler.php`, `tests/Integration/LegacyProcessHandlerTest.php`
- Modify: `includes/process.inc.php` (Step 0 — same root cause as Task 6's Step 0, predicted in advance rather than rediscovered)

**Interfaces:**
- Consumes: `AuthorizationMiddleware`'s `process:` route type (Task 4).
- Produces: `Bcoem\Legacy\LegacyProcessHandler::__invoke($request, $response)`.

- [ ] **Step 0: Fix the same test-bootstrap/legacy-code collision Task 6 hit, before it recurs here**

`includes/process.inc.php` has its own independent require chain (it does NOT go through `site/bootstrap.php`) and does:

```php
include (LIB.'common.lib.php');
include (LIB.'update.lib.php');
```

Both plain `include` (not `include_once`). `tests/bootstrap.php` preloads `common.lib.php` via `require_once` for every Unit test, and — once Task 6 lands — `update.lib.php` is also routinely loaded during the same PHPUnit process via the `index.php` chain. PHPUnit runs the whole suite in one shared PHP process (no `--process-isolation`), so by the time `LegacyProcessHandlerTest` runs, both files are highly likely to already be loaded, and a plain `include` of an already-loaded file that declares functions unconditionally is a fatal "cannot redeclare" error — the exact same failure mode Task 6 hit three times over, just via a different entry point. Fix pre-emptively:

```php
include_once (LIB.'common.lib.php');
include_once (LIB.'update.lib.php');
```

(Change only these two lines. This has zero effect on real production behavior — a real request only ever loads each file once regardless of `include` vs `include_once` — it only matters for the shared-process PHPUnit scenario.)

Run the full Unit suite afterward to confirm nothing changes: `docker compose exec web vendor/bin/phpunit --testsuite Unit` (same pass count as before).

- [ ] **Step 1: Write `LegacyProcessHandler`**

`src/Legacy/LegacyProcessHandler.php`:

```php
<?php

declare(strict_types=1);

namespace Bcoem\Legacy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Bridges POSTs to includes/process.inc.php. That file does
 * require('../paths.php') - a path relative to includes/ - so it must
 * actually run from within includes/ for its own relative require to
 * resolve; requireRootFile() chdir()s to ROOT first, so this bridges via
 * the includes/-relative path instead.
 */
final class LegacyProcessHandler
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        foreach ($request->getQueryParams() as $key => $value) {
            $_GET[$key] = $value;
        }
        foreach ((array)$request->getParsedBody() as $key => $value) {
            $_POST[$key] = $value;
        }
        chdir(ROOT . 'includes');
        require ROOT . 'includes/process.inc.php';
        return $response;
    }
}
```

- [ ] **Step 2: Write the integration test**

`tests/Integration/LegacyProcessHandlerTest.php`:

```php
<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration;

use Bcoem\Legacy\LegacyProcessHandler;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class LegacyProcessHandlerTest extends IntegrationTestCase
{
    public function test_logout_action_clears_session_via_legacy_bridge(): void
    {
        $_SESSION['loginUsername'] = 'someone@example.com';
        $_GET = ['action' => 'logout'];
        $_POST = [];
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/includes/process.inc.php?action=logout')
            ->withQueryParams(['action' => 'logout'])
            ->withParsedBody([]);
        $response = (new ResponseFactory())->createResponse(200);

        $handler = new LegacyProcessHandler();
        try {
            $handler($request, $response);
        } catch (\Throwable $e) {
            // process.inc.php calls exit() - under PHPUnit this may surface
            // as a risky-test warning rather than a clean return; assert on
            // session state regardless of how control returned.
        }

        $this->assertArrayNotHasKey('loginUsername', $_SESSION);
    }
}
```

- [ ] **Step 3: Run**

```bash
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit --testsuite Integration --filter LegacyProcessHandlerTest
```

`process.inc.php` ends with `exit()` unconditionally (`includes/process.inc.php:446`) — if this terminates the PHPUnit process instead of being caught, this is exactly the "exit() in page-scripts" risk from the design spec. If so, don't fight it in-process: convert this test to a Playwright e2e assertion instead (logout already has e2e coverage via existing specs) and note in this task's commit message that `LegacyProcessHandler`'s in-process testability is limited to actions that don't reach the trailing `exit()` (rare) - this is expected and matches how legacy behavior has always worked, not a new problem introduced by the bridge.

- [ ] **Step 4: Commit**

```bash
git add src/Legacy/LegacyProcessHandler.php tests/Integration/LegacyProcessHandlerTest.php
git commit -m "Add LegacyProcessHandler: bridge POSTs to includes/process.inc.php"
```

---

### Task 8: Wrap every side door behind the pipeline

**Files:**
- Create: `src/Legacy/LegacyFileHandler.php` (generalizes `LegacyPageHandler`'s pattern for any root-relative file)
- Modify: `src/Kernel/app.php` (register one route per side door from the inventory)

**Interfaces:**
- Consumes: `AuthorizationMiddleware`'s `file:` route type.
- Produces: every entry in `config/access_policy.php`'s `file:` namespace becomes a registered Slim route.

- [ ] **Step 1: Write `LegacyFileHandler`**

`src/Legacy/LegacyFileHandler.php`:

```php
<?php

declare(strict_types=1);

namespace Bcoem\Legacy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LegacyFileHandler
{
    public function __construct(private readonly string $relativePath)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        foreach ($request->getQueryParams() as $key => $value) {
            $_GET[$key] = $value;
        }
        foreach ((array)$request->getParsedBody() as $key => $value) {
            $_POST[$key] = $value;
        }
        chdir(ROOT . dirname($this->relativePath));
        require ROOT . $this->relativePath;
        return $response;
    }
}
```

- [ ] **Step 2: Register every side door route in `src/Kernel/app.php`**

Add, alongside the middleware registration from Task 5 (each route needs `->add(fn ($request, $handler) => $handler->handle($request->withAttribute('routeType', 'file')->withAttribute('routeFile', '<key>')))` — or simpler, set both attributes via Slim's route-level middleware; shown here as an inline closure per route for clarity):

```php
    $fileRoutes = [
        'qr.php', 'handle.php', 'ppv.php', 'awards.php', 'maintenance.php',
        'setup.php', 'update.php', '400.php', '401.php', '403.php', '404.php', '500.php',
        'admin/send_test_email.admin.php', 'output/maps.output.php',
        'ajax/account_checks.ajax.php', 'ajax/count_records.ajax.php',
        'ajax/custom_style.ajax.php', 'ajax/import_scores.ajax.php',
        'ajax/practice_session.ajax.php', 'ajax/purge.ajax.php',
        'ajax/regenerate.ajax.php', 'ajax/save.ajax.php',
        'ajax/tables_mode.ajax.php', 'ajax/username.ajax.php',
        'ajax/valid_email.ajax.php',
    ];

    foreach ($fileRoutes as $file) {
        $webPath = '/' . $file;
        $routeAttr = function ($request, $handler) use ($file) {
            return $handler->handle(
                $request->withAttribute('routeType', 'file')->withAttribute('routeFile', $file)
            );
        };
        $app->map(['GET', 'POST'], $webPath, new \Bcoem\Legacy\LegacyFileHandler($file))
            ->add($routeAttr);
    }
```

- [ ] **Step 3: Write the equivalence test**

Extend `tests/Integration/LegacyPageHandlerTest.php` (or a new `LegacyFileRoutesTest.php`) with one assertion per side door that it's reachable through the new route with a 200 (or its normal redirect) instead of a 403 — reusing the `AccessPolicy` test fixtures from Task 4 for the identity setup per file's declared role.

- [ ] **Step 4: Run the full Playwright suite against the routes that now go through this bridge**

```bash
cd e2e && npx playwright test
```

Every existing spec that hits `handle.php`, `qr.php`, or `ajax/save.ajax.php` (the security-invariant tests from Phase 0/1) must still pass unchanged — this is the first real slice of the Phase 2 equivalence gate.

- [ ] **Step 5: Commit**

```bash
git add src/Legacy/LegacyFileHandler.php src/Kernel/app.php tests/Integration/
git commit -m "Wrap every self-bootstrapping side door behind the authorization pipeline"
```

---

### Task 9: New front controller + `.htaccess` rewrite + PHPStan legacy-isolation rules

**Files:**
- Modify: `index.php` (replace with the ~10-line bootstrap; the CURRENT `index.php` content moves to `src/Legacy/` as the file `LegacyPageHandler` requires — see Step 1), `.htaccess`, `phpstan.neon`
- Create: `legacy/index.php` (the old `index.php`'s full content, unchanged, relocated so the new thin `index.php` doesn't collide with it)

**Interfaces:**
- Produces: `GET /` and every previously-direct URL now resolves through the Slim app; static assets (css/js/images) are still served directly by Apache, unrouted.

- [ ] **Step 1: Relocate the legacy front controller**

```bash
mkdir -p legacy
git mv index.php legacy/index.php
```

Update `LegacyPageHandler` (Task 6) and any side-door handler that assumed `index.php` lived at `ROOT` to instead require `ROOT.'legacy/index.php'`. Grep for any other file that references `index.php` by relative path expecting it at root (e.g. `header("Location: ...index.php...")` calls are fine - those are URLs, not filesystem requires) - only filesystem `require`/`include` statements matter here; confirm none exist via:

```bash
grep -rn "require.*'index\.php'\|include.*'index\.php'" --include="*.php" . | grep -v "\.git\|legacy/index.php"
```

Expected: no matches (nothing else `include`s `index.php` as a file; it was only ever hit directly as a URL).

- [ ] **Step 2: Write the new thin `index.php`**

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/paths.php'; // legacy bootstrap constants (ROOT, LIB, etc.) - still needed by every bridged file

$app = require __DIR__ . '/src/Kernel/app.php';
$app->run();
```

Wait — `src/Kernel/app.php` currently `return`s a `function buildApp()` definition, not an app instance directly (Task 1). Adjust: change the last line of `src/Kernel/app.php` to call and return the built app for this direct-execution context, OR (cleaner) have `index.php` call `buildApp()` explicitly:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/src/Kernel/app.php';

buildApp()->run();
```

- [ ] **Step 3: Add the catch-all legacy GET route**

In `src/Kernel/app.php`, alongside the side-door routes from Task 8:

```php
    $app->get('/index.php', new \Bcoem\Legacy\LegacyPageHandler())
        ->add(function ($request, $handler) {
            return $handler->handle($request->withAttribute('routeType', 'section'));
        });
    $app->post('/includes/process.inc.php', new \Bcoem\Legacy\LegacyProcessHandler())
        ->add(function ($request, $handler) {
            return $handler->handle($request->withAttribute('routeType', 'process'));
        });
    $app->get('/', new \Bcoem\Legacy\LegacyPageHandler())
        ->add(function ($request, $handler) {
            return $handler->handle($request->withAttribute('routeType', 'section'));
        });
```

- [ ] **Step 4: Rewrite `.htaccess` for a single front controller with a static-file passthrough**

**Do not use the standard `RewriteCond %{REQUEST_FILENAME} !-f` front-controller pattern here.** That pattern excludes any request matching a *real file on disk* from being routed — which is exactly wrong for this app: `qr.php`, `handle.php`, `ppv.php`, every `ajax/*.php`, and the other side doors wrapped in Task 8 are real files that must still funnel through the front controller (so `AuthorizationMiddleware` runs) rather than being served directly by Apache/mod_php as they are today. Excluding by *directory* (the genuinely static asset trees) instead of by file-existence gets this right:

```apache
RewriteEngine on
RewriteCond %{HTTPS} off
RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]

# Only these directories are truly static (css/js/images/user uploads) -
# everything else, including every *.php file that exists on disk today
# (qr.php, handle.php, ajax/*.php, admin/*.php, ...), must still funnel
# through the front controller so AuthorizationMiddleware runs first.
RewriteCond %{REQUEST_URI} !^/(css|js_includes|js_source|images|user_images|user_docs|user_temp)/ [NC]
RewriteRule ^(.*)$ index.php [QSA,L]

RewriteRule ^.*user_docs/.*\.pdf$ - [F,NC,L]
```

- [ ] **Step 4a: Verify the fix — a wrapped side door must NOT be reachable as a bare file hit anymore**

```bash
# Static asset: served directly, unrouted (200, real file content)
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/css/common.min.css
# Wrapped side door: now funnels through index.php -> Slim -> LegacyFileHandler,
# NOT served as a bare file. Confirm by checking it still works AND that the
# access-policy gate now actually applies (Task 10 Step 2 checks the gate
# itself; this step just confirms routing, not yet authorization).
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/qr.php
```

Both should return `200`; the meaningful difference (proven in Task 10) is that the second request now passes through `AuthorizationMiddleware` on its way there, where before Task 9 it did not.

Keep the SEF path-segment behavior by pre-parsing it: since everything now funnels through `index.php` regardless of path, add the old SEF-to-query-param translation as the very first thing `buildApp()`'s route resolution does, OR (simpler, since Slim can route on the path directly) register explicit Slim routes matching the old SEF shapes:

```php
    $app->get('/{section}[/{go}[/{action}[/{id}]]]', new \Bcoem\Legacy\LegacyPageHandler())
        ->add(function ($request, $handler) {
            $args = $request->getAttribute('__route__')?->getArguments() ?? [];
            foreach (['section', 'go', 'action', 'id'] as $key) {
                if (isset($args[$key])) {
                    $_GET[$key] = $args[$key];
                    $request = $request->withQueryParams([...$request->getQueryParams(), $key => $args[$key]]);
                }
            }
            return $handler->handle($request->withAttribute('routeType', 'section'));
        });
```

Place this route registration **after** the explicit `/index.php`, `/includes/process.inc.php`, and side-door routes (Slim matches routes in registration order for overlapping patterns) so those explicit paths are never swallowed by the generic SEF pattern.

- [ ] **Step 5: Add the two PHPStan custom rules from the design spec's Section 2 contract**

`phpstan.neon` already has a `parameters:` block (`level: 0`, `tmpDir`, `reportUnmatchedIgnoredErrors: false`, `paths` — extended with `src` in Task 1 Step 6a — and `excludePaths`). **Do not add a second `parameters:` key** — a NEON/YAML file with two top-level `parameters:` blocks either errors or silently drops one, depending on the parser. `rules:` is a distinct top-level key that doesn't exist yet in this file, so it's safe to append as a new sibling to the existing `parameters:` block:

```yaml
rules:
    - Bcoem\PHPStan\NoMysqliOutsideConnectionRule
    - Bcoem\PHPStan\NoLegacyReferenceOutsideLegacyRule
```

Rule classes go under a new `src/PHPStan/`.

These two rule classes have no implementation yet (they gate the *Connection* class and Domain-layer purity, both Phase 3 concerns) — register them now as a placeholder-free stub pair that currently always passes (e.g. `return []` from `processNode()`), so the config wiring is proven correct before Phase 3 needs the rules to actually do something. Do not skip writing the classes entirely — an empty rule that's registered and tested to run without error is meaningfully different from "TODO, add later," since it proves the PHPStan custom-rule wiring itself works.

```php
<?php
// src/PHPStan/NoMysqliOutsideConnectionRule.php
declare(strict_types=1);

namespace Bcoem\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/** Phase 3 will make this actually flag mysqli_* calls outside src/Database/Connection.php. */
final class NoMysqliOutsideConnectionRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        return [];
    }
}
```

(Same shape for `NoLegacyReferenceOutsideLegacyRule.php`.)

- [ ] **Step 6: Run PHPStan and the full test suite**

```bash
docker compose exec web vendor/bin/phpstan analyse
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit
```

- [ ] **Step 7: Commit**

```bash
git add index.php legacy/index.php .htaccess phpstan.neon src/PHPStan/ src/Kernel/app.php
git commit -m "Route everything through the Slim front controller; add PHPStan legacy-isolation rule stubs"
```

---

### Task 10: The equivalence gate

**Files:**
- None expected — this task only runs and fixes regressions surfaced by Tasks 1-9's changes.

**Interfaces:**
- Produces: proof that the shell changed nothing observable.

- [ ] **Step 1: Full local verification, twice for idempotency**

```bash
docker compose down -v && docker compose up -d --build
docker compose exec web vendor/bin/phpstan analyse
docker compose exec -e BCOEM_DB_HOST=db web vendor/bin/phpunit
cd e2e && npx playwright test
cd e2e && npx playwright test
```

Expected: identical pass counts to the pre-Phase-2 baseline (PHPStan clean; 346 PHPUnit tests, 0 failures; 18 Playwright tests, both runs green) — **plus** the new Kernel/Security/Legacy Unit and Integration tests from Tasks 1-9.

- [ ] **Step 2: Manual smoke check of a representative sample from each route category**

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/                                  # front controller root
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/index.php?section=contact          # legacy GET
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/qr.php                              # side door
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/css/common.min.css                  # static asset, unrouted
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/index.php?section=admin             # should redirect (anonymous denied)
```

Expected: `200, 200, 200, 200, 302` (the admin one redirects via legacy code's own `msg=0` handling, unchanged - AuthorizationMiddleware and the legacy page's own inline check now BOTH deny it, redundantly, exactly as the design spec's "harmless redundancy during transition" describes).

- [ ] **Step 3: Push and verify CI**

```bash
git push origin docker-baseline-db
gh run watch --exit-status || gh run view --log-failed
```

- [ ] **Step 4: Commit if any fixes were needed in Step 1-2**

```bash
git add -A
git commit -m "Fix regressions surfaced by the Phase 2 equivalence gate"
```

---

## Task Group B — Operability (error handling, observability, deployment)

These are lower-risk than Group A (standard patterns, not novel to this codebase) and less detailed here — write the granular TDD steps at execution time following the same discipline as Group A, using this section as the design brief.

### Task 11: Error handling overhaul

- **`mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`** as one line in `src/Kernel/container.php` or a new `src/Kernel/bootstrap_errors.php` required by the new `index.php` before `paths.php` — PHP 8.2 already defaults to this mode, so this line is about making the *intent* explicit and future-proof (a PHP version downgrade or ini override shouldn't silently reopen the `or die()` behavior), not changing current runtime behavior.
- **Slim's built-in `ErrorMiddleware`**, customized: production mode renders a branded page with a short error-reference ID (`bin2hex(random_bytes(4))`), logs full trace + request context under that ID via Monolog; debug mode (env `APP_DEBUG=1`) shows the full trace. AJAX/JSON requests (detect via `Accept` header or the `file:ajax/*` route type already tagged by `AuthorizationMiddleware`) get `{error, reference_id}` JSON instead of an HTML page.
- **Monolog** (`monolog/monolog`, add to composer): channels `app`, `security` (every `AuthorizationMiddleware` 403, every login success/failure), `legacy` (kernel `set_error_handler` routes legacy warnings/notices here — a live inventory of latent defects, not build-breaking). Constructor-injected via the PHP-DI container wherever new code needs it; legacy code keeps using PHP's native warning/notice mechanism, captured by the handler rather than modified at each of its hundreds of call sites.
- **Retire the 13 remaining `or die(mysqli_error())` instances** in `includes/db/admin_common.db.php` and `scores_bestbrewer.db.php` — with `mysqli_report` throwing exceptions on failure, these `or die()` clauses are already unreachable dead code (the exception fires before `mysqli_query()` can return `false`); delete them as a pure cleanup once `ErrorMiddleware` is confirmed to catch the resulting `mysqli_sql_exception` cleanly (write one Integration test that forces a real mysqli error — e.g. query a nonexistent table — and asserts the app returns a generic 500 rather than a raw stack trace or blank page).

**Definition of done:** a forced mysqli error returns a clean, branded 500 (or JSON envelope for AJAX routes) with a reference ID, never a raw `mysqli_error()` string in the response body — this closes P2-SEC-007 (info disclosure) as a side effect.

### Task 12: OpenTelemetry

- `TracingMiddleware`, outermost in the pipeline (added last so it's outermost per Slim's reverse-registration order — i.e., register it *after* Session/Authentication/Authorization in Task 5's list, since `add()` order is LIFO for execution): opens a root span per request tagged with route/section, resolved `Identity` (username + role, never the password), final status code, and any exception caught by `ErrorMiddleware`.
- `docker-compose.yml` gains an `otel-collector` service plus Jaeger (or Grafana Tempo) for local trace viewing; the `web` Dockerfile adds the OpenTelemetry PHP auto-instrumentation extension, which — as a side benefit — also captures spans for legacy `mysqli_query()` calls and Slim's own routing with zero code changes in `sections/`/`admin/`/`lib/`.
- Monolog gets an OTel processor/handler so log lines carry the active trace ID, letting a single request's logs and trace be correlated in the Jaeger/Grafana UI.
- Shared-hosting path: the same env-driven config (`OTEL_SDK_DISABLED=true` or the extension simply absent from that deployment's PHP build) makes every one of these bindings a no-op — verified by the Task 13 shared-hosting smoke job, not by anything here.

**Definition of done:** a request through the Docker stack produces one trace in the local Jaeger UI, spanning from `TracingMiddleware`'s root span down through at least one legacy `mysqli_query()` child span, with the acting user's role visible on the root span's attributes.

### Task 13: Deployment pipeline groundwork

- **Phinx** (`robmorgan/phinx`, add to composer): first migration creates `audit_log(id, user_id, action, entity, entity_id, before_json, after_json, ip, created_at)` (additive-only, per the design spec's forward-only rule during the strangler period) plus whatever indexes Phase 3's repositories will need on high-churn tables (`baseline_brewing.brewBrewerID`, `baseline_judging_scores.bid`, at minimum — confirm against actual query patterns in `lib/common.lib.php`/`includes/db/*.db.php` before finalizing the index list, don't guess). Docker: the `web` entrypoint runs `vendor/bin/phinx migrate` before Apache starts (modify `docker/*.sh` entrypoint or the Dockerfile's `CMD`). Shared hosting: an auth-gated browser-triggered runner mirroring today's `update.php` pattern, restricted to `Role::SuperAdmin` in the policy map (`file:phinx-migrate.php` or similar new thin wrapper).
- **Env-var config shim**: `site/config.php` gains `getenv('DB_HOST') ?: <existing hardcoded default>` fallbacks for every deploy-varying value (DB creds, `APP_DEBUG`, OTel endpoint, base URL) — additive fallback chains, so shared-hosting installs that still hand-edit `config.php` are unaffected; Docker's `docker-compose.yml` passes these as real environment variables instead of relying on the bind-mounted `docker/config.php` override Phase 0 used.
- **CI build/package steps**: extend `.github/workflows/ci.yml` with a `build` job (runs only on tags/releases, not every PR) producing two artifacts — a Docker image (`docker build` + push to GHCR, tagged with the git SHA and, on a tag push, the version) and a zip/tarball (`composer install --no-dev --optimize-autoloader`, excluding `tests/`, `e2e/`, dev-only config) attached to the GitHub Release. Add a `shared-hosting-smoke` CI job that runs the zip artifact under plain `php -S` (no OTel extension) and hits a health-check URL, proving the no-op observability path actually no-ops rather than fatal-erroring on a missing extension.

**Definition of done:** a tagged push produces both artifacts in GitHub Actions, and the shared-hosting smoke job passes without the OTel extension present.

---

## Self-review notes (already applied)

- **Spec coverage:** design spec Section 1 (strangler shell, middleware order, legacy bridge) → Tasks 1, 5, 6, 7, 8, 9. Section 1's central-authorization goal (success criterion 1) → Tasks 2, 3, 3a, 4 — the actual centerpiece, given full bite-sized TDD treatment. Section 2 (directory layout, PHPStan rules, throwaway `Legacy/`) → Tasks 1, 6, 9. Section 4 (error handling/observability) → Task 11-12 (lighter detail, explicitly by design — see the Group B preamble). Section 6 (deployment pipeline) → Task 13 (lighter detail). The equivalence gate the spec calls out explicitly ("the suite must pass identically before and after the Slim shell lands") → Task 10, plus incremental checks in Tasks 6 and 8.
- **Known judgment calls:** (a) the legacy bridge does NOT attempt to convert `header()`/`exit()`-calling legacy scripts into clean PSR-7 responses — documented as a deliberate architecture decision in the plan's own **Architecture** section, not a gap; the alternative (intercepting `header()`) isn't reliably possible in userland PHP. (b) `config/access_policy.php`'s public-page (`section:*`) entries are split into "confirmed" (admin dispatch, account pages, ajax, side doors — all read directly from source in the 2026-07-19 route inventory) and "VERIFY" (public pages whose *individual* `sections/*.sec.php` or `index.pub.php` inline block wasn't read line-by-line during planning) — Task 3a is a mandatory, not optional, closeout of every VERIFY marker before this phase is considered shippable; this is the honest alternative to fabricating unverified role assignments in a security-critical policy map. (c) Task Group B is intentionally lower-detail than Group A because it's operationally standard (Monolog, Phinx, OTel are well-trodden integration patterns) and lower-risk than the authorization core, which gets the full TDD treatment Group A represents.
- **Type consistency:** `Role`, `Identity`, `AccessPolicy` signatures are used identically across Tasks 2-9 (`requiredRoleFor`, `requiredRoleForProcessAction`, `requiredRoleForFile`, `requiredRoleForOutputSection` — all four consumed by `AuthorizationMiddleware` in Task 4, matching the `AccessPolicy` class defined in Task 3).
