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
