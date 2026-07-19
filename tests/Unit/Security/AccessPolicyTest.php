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
