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

    public function test_from_user_level_empty_string_is_entrant_not_super_admin(): void
    {
        $this->assertSame(Role::Entrant, Role::fromUserLevel(''));
    }

    public function test_from_user_level_non_numeric_string_is_entrant_not_super_admin(): void
    {
        $this->assertSame(Role::Entrant, Role::fromUserLevel('abc'));
    }

    public function test_from_user_level_negative_zero_is_entrant_not_super_admin(): void
    {
        // (int)'-0' === 0 pre-fix would have escalated this to SuperAdmin;
        // ctype_digit('-0') is false (minus sign isn't a digit), so this
        // actually exercises the guard, unlike '-1' which the old code's
        // match `default` arm already handled safely.
        $this->assertSame(Role::Entrant, Role::fromUserLevel('-0'));
    }
}
