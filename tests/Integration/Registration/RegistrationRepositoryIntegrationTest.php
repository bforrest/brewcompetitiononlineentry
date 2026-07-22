<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration\Registration;

use BCOEM\Tests\Integration\IntegrationTestCase;
use Bcoem\Database\Connection;
use Bcoem\Domain\Registration\Repository\RegistrationRepository;
use Bcoem\Domain\Registration\ValueObject\Email;

class RegistrationRepositoryIntegrationTest extends IntegrationTestCase
{
    private RegistrationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RegistrationRepository(new Connection(self::$conn));
    }

    public function test_email_exists_is_false_for_unused_email(): void
    {
        $this->assertFalse($this->repository->emailExists(Email::from('brand-new@test.example')));
    }

    public function test_email_exists_is_true_after_insert(): void
    {
        $this->insertTestUser('taken@test.example');
        $this->assertTrue($this->repository->emailExists(Email::from('taken@test.example')));
    }

    public function test_insert_user_returns_new_registrant_id(): void
    {
        $id = $this->repository->insertUser([
            'user_name' => 'newuser@test.example',
            'password' => '$2y$10$abcdefghijklmnopqrstuv',
            'userLevel' => '2',
            'userQuestion' => 'Favorite hop?',
            'userQuestionAnswer' => '$2a$08$hashedanswer',
            'userCreated' => date('Y-m-d H:i:s'),
            'userAdminObfuscate' => 0,
        ]);

        $this->assertGreaterThan(0, $id->value());

        $rows = $this->select('users', "id = {$id->value()}");
        $this->assertSame('newuser@test.example', $rows[0]['user_name']);
        $this->assertSame('2', $rows[0]['userLevel']);
    }

    public function test_insert_brewer_profile(): void
    {
        $userId = $this->repository->insertUser([
            'user_name' => 'brewerprofile@test.example',
            'password' => '$2y$10$abcdefghijklmnopqrstuv',
            'userLevel' => '2',
            'userQuestion' => 'Q',
            'userQuestionAnswer' => 'A',
            'userCreated' => date('Y-m-d H:i:s'),
            'userAdminObfuscate' => 0,
        ]);

        $this->repository->insertBrewerProfile([
            'uid' => $userId->value(),
            'brewerFirstName' => 'Jane',
            'brewerLastName' => 'Brewer',
            'brewerEmail' => 'brewerprofile@test.example',
            'brewerAddress' => null,
        ]);

        $rows = $this->select('brewer', "uid = {$userId->value()}");
        $this->assertCount(1, $rows);
        $this->assertSame('Jane', $rows[0]['brewerFirstName']);
        $this->assertNull($rows[0]['brewerAddress']);
    }

    public function test_staff_row_lifecycle(): void
    {
        $userId = $this->repository->insertUser([
            'user_name' => 'staffcheck@test.example',
            'password' => 'x', 'userLevel' => '2', 'userQuestion' => 'Q',
            'userQuestionAnswer' => 'A', 'userCreated' => date('Y-m-d H:i:s'),
            'userAdminObfuscate' => 0,
        ]);

        $this->assertFalse($this->repository->staffRowExists($userId->value()));

        $this->repository->insertStaffRow([
            'uid' => $userId->value(),
            'staff_judge' => 0, 'staff_judge_bos' => 0,
            'staff_steward' => 1, 'staff_organizer' => 0, 'staff_staff' => 0,
        ]);

        $this->assertTrue($this->repository->staffRowExists($userId->value()));
        $rows = $this->select('staff', "uid = {$userId->value()}");
        $this->assertSame(1, (int) $rows[0]['staff_steward']);

        $this->repository->updateStaffRow($userId->value(), [
            'staff_judge' => 1, 'staff_judge_bos' => 0,
            'staff_steward' => 0, 'staff_organizer' => 0, 'staff_staff' => 0,
        ]);

        $rows = $this->select('staff', "uid = {$userId->value()}");
        $this->assertSame(1, (int) $rows[0]['staff_judge']);
        $this->assertSame(0, (int) $rows[0]['staff_steward']);
    }

    public function test_judging_location_type_returns_null_for_missing_location(): void
    {
        $this->assertNull($this->repository->judgingLocationType(999999));
    }

    public function test_judging_location_type_returns_type(): void
    {
        $locId = $this->insert('judging_locations', [
            'judgingLocName' => 'Main Hall',
            'judgingLocType' => '2',
        ]);

        $this->assertSame('2', $this->repository->judgingLocationType($locId));
    }
}
