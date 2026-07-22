<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\Registration\Service;

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Registration\Command\RegisterEntrantCommand;
use Bcoem\Domain\Registration\Exception\CaptchaVerificationFailedException;
use Bcoem\Domain\Registration\Exception\DuplicateEmailException;
use Bcoem\Domain\Registration\Exception\RegistrationClosedException;
use Bcoem\Domain\Registration\Repository\RegistrationRepository;
use Bcoem\Domain\Registration\Service\CaptchaVerifier;
use Bcoem\Domain\Registration\Service\RegistrationService;
use Bcoem\Domain\Registration\ValueObject\RegistrantId;

class RegistrationServiceTest extends TestCase
{
    private RegistrationRepository $repository;
    private CaptchaVerifier $captcha;
    private RegistrationService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RegistrationRepository::class);
        $this->captcha = $this->createMock(CaptchaVerifier::class);
        $this->service = new RegistrationService($this->repository, $this->captcha);

        // Matches legacy's own default when no non-default language is set.
        $_SESSION['prefsLanguageFolder'] = 'en';
    }

    private function baseCommand(array $overrides = []): RegisterEntrantCommand
    {
        return new RegisterEntrantCommand($overrides + [
            'user_name' => 'entrant@example.com',
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
    }

    public function test_register_throws_when_registration_closed(): void
    {
        $this->expectException(RegistrationClosedException::class);
        $this->service->register($this->baseCommand(), false, false, [], '127.0.0.1');
    }

    public function test_register_throws_on_duplicate_email(): void
    {
        $this->repository->method('emailExists')->willReturn(true);

        $this->expectException(DuplicateEmailException::class);
        $this->service->register($this->baseCommand(), true, true, [], '127.0.0.1');
    }

    public function test_register_throws_on_captcha_failure(): void
    {
        $this->repository->method('emailExists')->willReturn(false);
        $this->captcha->method('verify')->willReturn(false);

        $this->expectException(CaptchaVerificationFailedException::class);
        $this->service->register($this->baseCommand(), true, true, [], '127.0.0.1');
    }

    public function test_register_creates_user_brewer_and_staff_rows(): void
    {
        $this->repository->method('emailExists')->willReturn(false);
        $this->captcha->method('verify')->willReturn(true);
        $this->repository->method('staffRowExists')->willReturn(false);

        $this->repository->expects($this->once())->method('insertUser')
            ->with($this->callback(function (array $row) {
                return $row['user_name'] === 'entrant@example.com'
                    && $row['userLevel'] === '2'
                    && $row['userAdminObfuscate'] === 1
                    && password_verify('Sup3rSecret!', $row['password']);
            }))
            ->willReturn(RegistrantId::from(101));

        $this->repository->expects($this->once())->method('insertBrewerProfile')
            ->with($this->callback(fn (array $row) => $row['uid'] === 101 && $row['brewerFirstName'] === 'Jane'));

        $this->repository->expects($this->once())->method('insertStaffRow')
            ->with($this->callback(fn (array $row) => $row['uid'] === 101 && $row['staff_judge'] === 0 && $row['staff_steward'] === 0 && $row['staff_staff'] === 0));

        $id = $this->service->register($this->baseCommand(), true, true, [], '127.0.0.1');

        $this->assertSame(101, $id->value());
    }

    public function test_register_updates_existing_staff_row_when_present(): void
    {
        $this->repository->method('emailExists')->willReturn(false);
        $this->captcha->method('verify')->willReturn(true);
        $this->repository->method('staffRowExists')->willReturn(true);
        $this->repository->method('insertUser')->willReturn(RegistrantId::from(202));

        $this->repository->expects($this->never())->method('insertStaffRow');
        $this->repository->expects($this->once())->method('updateStaffRow')
            ->with(202, $this->anything());

        $this->service->register($this->baseCommand(['brewerSteward' => 'Y']), true, true, [], '127.0.0.1');
    }

    public function test_staff_row_stays_all_zero_regardless_of_opt_in_checkboxes(): void
    {
        // Legacy's staff-table derivation (process_users_register.inc.php:241-250) only
        // sets staff_judge/staff_steward when $go == "judge"/"steward" - routes this
        // entrant registration form never uses - and never sets staff_staff outside the
        // admin-add-user path. The opt-in checkboxes are still recorded on the brewer
        // table, but must NOT grant staff-table privileges through this route.
        $this->repository->method('emailExists')->willReturn(false);
        $this->captcha->method('verify')->willReturn(true);
        $this->repository->method('staffRowExists')->willReturn(false);
        $this->repository->method('insertUser')->willReturn(RegistrantId::from(707));

        $this->repository->expects($this->once())->method('insertStaffRow')
            ->with($this->callback(fn (array $row) => $row['uid'] === 707
                && $row['staff_judge'] === 0
                && $row['staff_steward'] === 0
                && $row['staff_staff'] === 0));

        $cmd = $this->baseCommand([
            'brewerJudge' => 'Y',
            'brewerSteward' => 'Y',
            'brewerStaff' => 'Y',
        ]);
        $this->service->register($cmd, true, true, [], '127.0.0.1');
    }

    public function test_judge_location_preference_encoded_as_submitted(): void
    {
        $this->repository->method('emailExists')->willReturn(false);
        $this->captcha->method('verify')->willReturn(true);
        $this->repository->method('staffRowExists')->willReturn(false);
        $this->repository->method('insertUser')->willReturn(RegistrantId::from(303));

        $this->repository->expects($this->once())->method('insertBrewerProfile')
            ->with($this->callback(fn (array $row) => $row['brewerJudgeLocation'] === 'Y-5,N-6'));

        $cmd = $this->baseCommand([
            'brewerJudge' => 'Y',
            'brewerJudgeLocation' => ['Y-5', 'N-6'],
        ]);
        $this->service->register($cmd, true, true, [], '127.0.0.1');
    }

    public function test_steward_location_preference_forced_no_for_non_type_2_locations(): void
    {
        $this->repository->method('emailExists')->willReturn(false);
        $this->captcha->method('verify')->willReturn(true);
        $this->repository->method('staffRowExists')->willReturn(false);
        $this->repository->method('insertUser')->willReturn(RegistrantId::from(404));
        $this->repository->method('judgingLocationType')->willReturn('0'); // not type '2'

        $this->repository->expects($this->once())->method('insertBrewerProfile')
            ->with($this->callback(fn (array $row) => $row['brewerStewardLocation'] === 'N-7'));

        $cmd = $this->baseCommand([
            'brewerSteward' => 'Y',
            'brewerStewardLocation' => 'Y-7',
        ]);
        $this->service->register($cmd, true, true, [], '127.0.0.1');
    }

    public function test_club_not_in_allowlist_is_blanked(): void
    {
        $this->repository->method('emailExists')->willReturn(false);
        $this->captcha->method('verify')->willReturn(true);
        $this->repository->method('staffRowExists')->willReturn(false);
        $this->repository->method('insertUser')->willReturn(RegistrantId::from(505));

        $this->repository->expects($this->once())->method('insertBrewerProfile')
            ->with($this->callback(fn (array $row) => $row['brewerClubs'] === null));

        $cmd = $this->baseCommand(['brewerClubs' => 'Some Unlisted Club']);
        $this->service->register($cmd, true, true, ['Homebrew Club A', 'Homebrew Club B'], '127.0.0.1');
    }

    public function test_club_in_allowlist_is_kept(): void
    {
        $this->repository->method('emailExists')->willReturn(false);
        $this->captcha->method('verify')->willReturn(true);
        $this->repository->method('staffRowExists')->willReturn(false);
        $this->repository->method('insertUser')->willReturn(RegistrantId::from(606));

        $this->repository->expects($this->once())->method('insertBrewerProfile')
            ->with($this->callback(fn (array $row) => $row['brewerClubs'] === 'Homebrew Club A'));

        $cmd = $this->baseCommand(['brewerClubs' => 'Homebrew Club A']);
        $this->service->register($cmd, true, true, ['Homebrew Club A', 'Homebrew Club B'], '127.0.0.1');
    }
}
