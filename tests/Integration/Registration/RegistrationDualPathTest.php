<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration\Registration;

use BCOEM\Tests\Integration\IntegrationTestCase;
use Bcoem\Database\Connection;
use Bcoem\Domain\Registration\Command\RegisterEntrantCommand;
use Bcoem\Domain\Registration\Repository\RegistrationRepository;
use Bcoem\Domain\Registration\Service\NullCaptchaVerifier;
use Bcoem\Domain\Registration\Service\RegistrationService;

/**
 * Proves legacy's process_users_register.inc.php path and the new
 * RegistrationService produce equivalent users/brewer/staff rows for
 * identical input. Runs today, no browser needed - the fast CI-friendly
 * gate the trust audit's own recommendation asked for, which Phase 3.2-3.4
 * shipped without.
 */
class RegistrationDualPathTest extends IntegrationTestCase
{
    private RegistrationService $service;
    private RegistrationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RegistrationRepository(new Connection(self::$conn));
        $this->service = new RegistrationService($this->repository, new NullCaptchaVerifier());
        $_SESSION['prefsLanguageFolder'] = 'en';
    }

    /**
     * Minimal, faithful port of process_users_register.inc.php's insert
     * logic for the "default" (entrant self-registration) filter branch.
     *
     * Deliberately includes the same HTMLPurifier::purify() -> sterilize()
     * pipeline that process_brewer_info.inc.php:394-433 applies to
     * first/last name, address, and city (RegistrationService::register()
     * was fixed in Task 7 to match this exactly). It's a no-op for this
     * test's plain-ASCII fixtures ("Jane", "Brewer", "1 Test Street",
     * "Testville" contain no HTML/entities), so it makes no difference to
     * today's assertions - but leaving it out would make this "legacy path"
     * helper diverge from what legacy actually does the moment a fixture
     * ever contains markup, silently narrowing what the dual-path proof
     * covers. Included for genuine fidelity, not because today's fixtures
     * require it.
     */
    private function registerViaLegacyPath(string $email, string $password, string $firstName, string $lastName): array
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        require_once CLASSES . 'phpass/PasswordHash.php';
        $hasher = new \PasswordHash(8, false);
        $questionHash = $hasher->HashPassword(sterilize('Citra'));

        require_once CLASSES . 'htmlpurifier/HTMLPurifier.standalone.php';
        $purifier = new \HTMLPurifier(\HTMLPurifier_Config::createDefault());

        $userId = $this->insert('users', [
            'user_name' => $email,
            'userLevel' => '2',
            'password' => $hash,
            'userQuestion' => sterilize('Favorite hop?'),
            'userQuestionAnswer' => $questionHash,
            'userCreated' => date('Y-m-d H:i:s'),
            'userAdminObfuscate' => 1,
        ]);

        $this->insert('brewer', [
            'uid' => $userId,
            'brewerFirstName' => sterilize($purifier->purify($firstName)),
            'brewerLastName' => sterilize($purifier->purify($lastName)),
            'brewerAddress' => sterilize($purifier->purify('1 Test Street')),
            'brewerCity' => sterilize($purifier->purify('Testville')),
            'brewerState' => 'TX',
            'brewerZip' => sterilize('75001'),
            'brewerCountry' => sterilize('United States'),
            'brewerPhone1' => sterilize('555-555-0100'),
            'brewerEmail' => $email,
        ]);

        $this->insert('staff', [
            'uid' => $userId,
            'staff_judge' => 0, 'staff_judge_bos' => 0,
            'staff_steward' => 0, 'staff_organizer' => 0, 'staff_staff' => 0,
        ]);

        return ['userId' => $userId, 'password' => $password];
    }

    public function test_legacy_and_modern_paths_produce_equivalent_rows(): void
    {
        $legacy = $this->registerViaLegacyPath('legacy-path@test.example', 'Sup3rSecret!', 'Jane', 'Brewer');

        $cmd = new RegisterEntrantCommand([
            'user_name' => 'modern-path@test.example',
            'password' => 'Sup3rSecret!',
            'userQuestion' => 'Favorite hop?',
            'userQuestionAnswer' => 'Citra',
            'brewerFirstName' => 'Jane',
            'brewerLastName' => 'Brewer',
            'brewerAddress' => '1 Test Street',
            'brewerCity' => 'Testville',
            'brewerStateUS' => 'TX',
            'brewerZip' => '75001',
            'brewerCountry' => 'United States',
            'brewerPhone1' => '555-555-0100',
        ]);
        $modernId = $this->service->register($cmd, true, true, [], '127.0.0.1');

        $legacyUser = $this->select('users', "id = {$legacy['userId']}")[0];
        $modernUser = $this->select('users', "id = {$modernId->value()}")[0];

        $this->assertSame($legacyUser['userLevel'], $modernUser['userLevel']);
        $this->assertSame($legacyUser['userAdminObfuscate'], $modernUser['userAdminObfuscate']);
        $this->assertTrue(password_verify('Sup3rSecret!', $modernUser['password']));
        $this->assertTrue(password_verify('Sup3rSecret!', $legacyUser['password']));

        $legacyBrewer = $this->select('brewer', "uid = {$legacy['userId']}")[0];
        $modernBrewer = $this->select('brewer', "uid = {$modernId->value()}")[0];

        foreach (['brewerFirstName', 'brewerLastName', 'brewerAddress', 'brewerCity',
                  'brewerState', 'brewerZip', 'brewerCountry', 'brewerPhone1'] as $field) {
            $this->assertSame($legacyBrewer[$field], $modernBrewer[$field], "Mismatch on {$field}");
        }

        $legacyStaff = $this->select('staff', "uid = {$legacy['userId']}")[0];
        $modernStaff = $this->select('staff', "uid = {$modernId->value()}")[0];

        foreach (['staff_judge', 'staff_steward', 'staff_staff'] as $field) {
            $this->assertSame((int) $legacyStaff[$field], (int) $modernStaff[$field], "Mismatch on {$field}");
        }
    }

    public function test_judge_opt_in_location_preference_matches_across_paths(): void
    {
        $locId = $this->insert('judging_locations', ['judgingLocName' => 'Main Hall', 'judgingLocType' => '0']);

        $cmd = new RegisterEntrantCommand([
            'user_name' => 'judge-opt-in@test.example',
            'password' => 'Sup3rSecret!',
            'userQuestion' => 'Favorite hop?',
            'userQuestionAnswer' => 'Citra',
            'brewerFirstName' => 'Jo',
            'brewerLastName' => 'Judge',
            'brewerAddress' => '1 Test Street',
            'brewerCity' => 'Testville',
            'brewerStateUS' => 'TX',
            'brewerZip' => '75001',
            'brewerCountry' => 'United States',
            'brewerPhone1' => '555-555-0100',
            'brewerJudge' => 'Y',
            'brewerJudgeLocation' => "Y-{$locId}",
        ]);
        $modernId = $this->service->register($cmd, true, true, [], '127.0.0.1');

        $modernBrewer = $this->select('brewer', "uid = {$modernId->value()}")[0];
        // Legacy's own encoding for a single, non-array submission is the bare
        // "Y-{id}" string (no trailing comma) - see process_brewer_info.inc.php:199/225.
        $this->assertSame("Y-{$locId}", $modernBrewer['brewerJudgeLocation']);

        // NOT staff_judge=1: Task 7's fix to RegistrationService::register()
        // established that legacy's real entrant self-registration route
        // (process_users_register.inc.php:241-250) never grants staff
        // privileges from these opt-in checkboxes - only the separate,
        // deferred go=judge/go=steward registration variants do, which are
        // out of this task's scope. This is also covered directly by
        // RegistrationServiceTest::test_staff_row_stays_all_zero_regardless_of_opt_in_checkboxes.
        // brewerJudge='Y' is still recorded on the brewer row itself
        // (checked above via brewerJudgeLocation); it just doesn't flip the
        // staff table through this path.
        $modernStaff = $this->select('staff', "uid = {$modernId->value()}")[0];
        $this->assertSame(0, (int) $modernStaff['staff_judge']);
    }
}
