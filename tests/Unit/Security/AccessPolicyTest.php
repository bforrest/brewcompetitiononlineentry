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

    /**
     * Regression test (Task 10): the central gate's floor for
     * process:dbTable:baseline_users must be Anonymous, not Entrant -
     * includes/process/process_users.inc.php's own dispatch has a genuine
     * anonymous registration sub-case (action=add&section=register, no
     * session check at all) that a required Role::Entrant blocked entirely
     * before that legacy dispatch ever ran (an Anonymous identity never
     * satisfies a required Entrant). The admin-create-user and self-edit
     * sub-cases remain independently gated by that same file's own internal
     * checks, unchanged - see access_policy.php's citation for the exact
     * line numbers.
     */
    public function test_process_dbtable_users_requires_only_anonymous_floor(): void
    {
        $this->assertSame(Role::Anonymous, $this->policy()->requiredRoleForProcessAction(null, 'baseline_users'));
    }

    public function test_undeclared_process_action_is_denied(): void
    {
        $this->assertNull($this->policy()->requiredRoleForProcessAction('no-such-action', null));
    }

    /**
     * Regression test (Task 10): includes/process.inc.php only special-cases
     * a fixed list of $action values (login, logout, delete, ...) before
     * falling through to its generic $dbTable-driven CRUD dispatch -
     * $action=="add"/"edit"/"massupdate"/etc. (the bulk of the app's actual
     * writes, including registration's action=add&dbTable=baseline_users)
     * is dispatched purely on $dbTable. A process:action:{action} entry must
     * only govern when that specific action has its own policy key;
     * anything else must fall through to the dbTable-based check, exactly
     * like the legacy dispatch does. Uses baseline_brewing (entry
     * submission), not baseline_users, to stay independent of that other
     * table's own Anonymous-floor fix above.
     */
    public function test_unmapped_action_falls_through_to_dbtable(): void
    {
        $this->assertSame(
            Role::Entrant,
            $this->policy()->requiredRoleForProcessAction('add', 'baseline_brewing')
        );
    }

    public function test_unmapped_action_with_no_dbtable_match_is_still_denied(): void
    {
        $this->assertNull($this->policy()->requiredRoleForProcessAction('add', 'no-such-table'));
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

    /**
     * Task 13: the Phinx migration runner side door (phinx-migrate.php) is
     * a destructive, schema-altering action on shared hosting installs with
     * no other way to trigger it - it must require the top role, same as
     * the other SuperAdmin-only side doors (practice_session.ajax.php,
     * purge.ajax.php, regenerate.ajax.php).
     */
    public function test_phinx_migrate_side_door_requires_super_admin(): void
    {
        $this->assertSame(Role::SuperAdmin, $this->policy()->requiredRoleForFile('phinx-migrate.php'));
    }
}
