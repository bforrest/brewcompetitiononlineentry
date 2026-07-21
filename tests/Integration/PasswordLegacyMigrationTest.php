<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration;

require_once CLASSES.'phpass/PasswordHash.php';

/**
 * P1-SEC-001: locks in the MD5-pre-hash-removal migration path
 * (lib/common.lib.php: password_verify_legacy(), password_needs_legacy_upgrade(),
 * upgrade_legacy_password_hash()) so a future refactor can't silently regress
 * it and strand legacy-scheme accounts (including the seeded baseline admin,
 * whose stored hash predates this fix).
 */
class PasswordLegacyMigrationTest extends IntegrationTestCase
{
    private function legacyHash(string $plaintext): string
    {
        $hasher = new \PasswordHash(8, false);
        return $hasher->HashPassword(md5($plaintext));
    }

    public function test_upgrade_legacy_password_hash_persists_a_current_scheme_hash(): void
    {
        $ids = $this->insertTestUser('upgrade-target@test.example');

        \upgrade_legacy_password_hash(self::$conn, self::$pfx.'users', 'id', $ids['userId'], 'LegacyPass123!');

        $rows = $this->select('users', "id = {$ids['userId']}");
        $persistedHash = $rows[0]['password'];

        $this->assertFalse(\password_needs_legacy_upgrade($persistedHash));
        $this->assertSame(1, \password_verify_legacy('LegacyPass123!', $persistedHash));
    }

    public function test_legacy_account_migrates_end_to_end_on_first_successful_verify(): void
    {
        $legacyHash = $this->legacyHash('LegacyPass123!');
        $ids = $this->insertTestUser('end-to-end-migration@test.example');
        $conn = self::$conn;
        $pfx = self::$pfx;

        // Seed the row with a legacy-scheme hash, mirroring a real pre-fix account.
        $conn->query(sprintf(
            "UPDATE `%susers` SET password = '%s' WHERE id = %d",
            $pfx, $conn->real_escape_string($legacyHash), $ids['userId']
        ));

        // This is exactly the sequence includes/logincheck.inc.php runs after
        // a successful lookup.
        $stored = $this->select('users', "id = {$ids['userId']}")[0]['password'];
        $check = \password_verify_legacy('LegacyPass123!', $stored);
        if (($check == 1) && (\password_needs_legacy_upgrade($stored))) {
            \upgrade_legacy_password_hash($conn, $pfx.'users', 'id', $ids['userId'], 'LegacyPass123!');
        }

        $this->assertSame(1, $check);

        $migratedHash = $this->select('users', "id = {$ids['userId']}")[0]['password'];
        $this->assertNotSame($legacyHash, $migratedHash);
        $this->assertFalse(\password_needs_legacy_upgrade($migratedHash));
        // The account keeps working with the same plaintext password after migration.
        $this->assertSame(1, \password_verify_legacy('LegacyPass123!', $migratedHash));
    }

    public function test_wrong_password_against_legacy_hash_does_not_migrate(): void
    {
        $legacyHash = $this->legacyHash('LegacyPass123!');
        $ids = $this->insertTestUser('no-migrate-on-failure@test.example');
        $conn = self::$conn;
        $pfx = self::$pfx;

        $conn->query(sprintf(
            "UPDATE `%susers` SET password = '%s' WHERE id = %d",
            $pfx, $conn->real_escape_string($legacyHash), $ids['userId']
        ));

        $stored = $this->select('users', "id = {$ids['userId']}")[0]['password'];
        $check = \password_verify_legacy('WrongPassword!', $stored);

        $this->assertSame(0, $check);
        $unchangedHash = $this->select('users', "id = {$ids['userId']}")[0]['password'];
        $this->assertSame($legacyHash, $unchangedHash);
    }
}
