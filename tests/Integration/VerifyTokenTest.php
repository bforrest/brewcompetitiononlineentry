<?php
/**
 * Integration tests for verify_token().
 *
 * verify_token($token, $time) queries the `users` table for a matching
 * userToken and compares times.  Return codes:
 *   0 = valid (token found, not expired)
 *   1 = invalid (token not found)
 *   2 = expired (token found, time past window)
 *
 * These tests are skipped automatically when the integration DB is unreachable.
 */

declare(strict_types=1);

namespace BCOEM\Tests\Integration;

class VerifyTokenTest extends IntegrationTestCase
{
    // The baseline schema uses id=1 for the built-in admin user.
    // We use a high-enough id range for our tokens to avoid collisions.
    private const TOKEN_VALID   = 'test_token_valid_abc123';
    private const TOKEN_EXPIRED = 'test_token_expired_xyz789';

    // ── Token not in DB ────────────────────────────────────────────────────────

    public function testUnknownTokenReturnsOne(): void
    {
        $result = verify_token('no_such_token_ever', time());
        $this->assertSame(1, $result, 'A token that does not exist in the DB should return 1 (invalid).');
    }

    // ── Valid token ────────────────────────────────────────────────────────────

    public function testValidTokenWithinWindowReturnsZero(): void
    {
        $issuedAt = time();   // issued right now

        $this->insert('users', [
            'user_name'      => 'token.valid@test.example',
            'password'       => '$2a$08$placeholder',
            'userLevel'      => '2',
            'userToken'      => self::TOKEN_VALID,
            'userTokenTime'  => $issuedAt,
            'userCreated'    => date('Y-m-d H:i:s'),
        ]);

        // Checking at time-of-issue: $time <= ($issuedAt + 86400) → valid
        $result = verify_token(self::TOKEN_VALID, $issuedAt);
        $this->assertSame(0, $result, 'A token checked within 24 h of issue should return 0 (valid).');
    }

    public function testValidTokenCheckedNearWindowEdgeReturnsZero(): void
    {
        $issuedAt = time() - 80000;   // ~22 h ago — still within the 24 h window

        $this->insert('users', [
            'user_name'      => 'token.edge@test.example',
            'password'       => '$2a$08$placeholder',
            'userLevel'      => '2',
            'userToken'      => 'edge_token_abc',
            'userTokenTime'  => $issuedAt,
            'userCreated'    => date('Y-m-d H:i:s'),
        ]);

        $result = verify_token('edge_token_abc', time());
        $this->assertSame(0, $result, 'A token ~22 h old should still be valid.');
    }

    // ── Expired token ──────────────────────────────────────────────────────────

    public function testExpiredTokenReturnsTwo(): void
    {
        $issuedAt = time() - 90000;   // ~25 h ago — past the 24 h window

        $this->insert('users', [
            'user_name'      => 'token.expired@test.example',
            'password'       => '$2a$08$placeholder',
            'userLevel'      => '2',
            'userToken'      => self::TOKEN_EXPIRED,
            'userTokenTime'  => $issuedAt,
            'userCreated'    => date('Y-m-d H:i:s'),
        ]);

        // $expired_time = $issuedAt + 86400
        // $time (now) > $expired_time  → return 2
        $result = verify_token(self::TOKEN_EXPIRED, time());
        $this->assertSame(2, $result, 'A token older than 24 h should return 2 (expired).');
    }

    // ── Duplicate tokens (edge case) ───────────────────────────────────────────

    /**
     * The function checks mysqli_num_rows == 1 before doing the time comparison.
     * If two users somehow end up with the same token, the function returns 1
     * (invalid) even though both tokens exist — because the row count is 2.
     */
    public function testDuplicateTokenReturnsOne(): void
    {
        $issuedAt = time();
        $dupToken = 'duplicate_token_test_999';

        $this->insert('users', [
            'user_name'     => 'dup1@test.example',
            'password'      => '$2a$08$placeholder',
            'userLevel'     => '2',
            'userToken'     => $dupToken,
            'userTokenTime' => $issuedAt,
            'userCreated'   => date('Y-m-d H:i:s'),
        ]);
        $this->insert('users', [
            'user_name'     => 'dup2@test.example',
            'password'      => '$2a$08$placeholder',
            'userLevel'     => '2',
            'userToken'     => $dupToken,
            'userTokenTime' => $issuedAt,
            'userCreated'   => date('Y-m-d H:i:s'),
        ]);

        // num_rows == 2, not 1, so $return stays at 1 (the initialised default)
        $result = verify_token($dupToken, $issuedAt);
        $this->assertSame(1, $result, 'Duplicate tokens should return 1 (the num_rows != 1 path).');
    }
}
