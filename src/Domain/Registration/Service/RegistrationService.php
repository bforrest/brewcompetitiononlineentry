<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Service;

use Bcoem\Domain\Registration\Command\RegisterEntrantCommand;
use Bcoem\Domain\Registration\Exception\CaptchaVerificationFailedException;
use Bcoem\Domain\Registration\Exception\DuplicateEmailException;
use Bcoem\Domain\Registration\Exception\RegistrationClosedException;
use Bcoem\Domain\Registration\Repository\RegistrationRepository;
use Bcoem\Domain\Registration\ValueObject\Email;
use Bcoem\Domain\Registration\ValueObject\RegistrantId;

/**
 * Entrant self-registration orchestration. Ports
 * includes/process/process_users_register.inc.php's "filter=default" branch
 * and includes/process/process_brewer_info.inc.php's field processing.
 *
 * Deliberately reuses legacy helpers directly for the tricky, easy-to-drift
 * behaviors (name parsing, sterilize(), phpass) rather than reimplementing
 * them - true dual-path equivalence means calling the SAME code, not a
 * lookalike copy that can silently diverge.
 */
final class RegistrationService
{
    /**
     * Mirrors lib/process.lib.php:536 exactly. Deliberately hardcoded here
     * rather than re-sourced via require_once: PHP's require_once is a
     * process-wide once-only guard — only the FIRST scope that requires a
     * given file ever sees its top-level variable assignments land in that
     * scope's locals. Every call to register() after the first in the same
     * PHP process (every request after the first on a shared PHP-FPM
     * worker; every test method after the first in one PHPUnit run) would
     * silently see $name_check_langs as undefined otherwise — confirmed
     * empirically during this task's implementation. Keep this list in
     * sync with lib/process.lib.php if it's ever changed there.
     */
    private const NAME_CHECK_LANGS = ['en', 'fr', 'es', 'pt', 'it', 'de', 'nl'];

    /** Mirrors lib/process.lib.php:537 exactly — see NAME_CHECK_LANGS's docblock for why this is duplicated, not re-sourced. */
    private const LAST_NAME_EXCEPTION_LANGS = ['nl', 'es', 'de'];

    public function __construct(
        private RegistrationRepository $repository,
        private CaptchaVerifier $captcha,
    ) {
    }

    /**
     * @param array<int, string> $clubAllowlist Session's $_SESSION['club_array']
     */
    public function register(
        RegisterEntrantCommand $cmd,
        bool $registrationOpen,
        bool $judgeWindowOpen,
        array $clubAllowlist,
        string $remoteAddr,
    ): RegistrantId {
        if (!$registrationOpen && !$judgeWindowOpen) {
            throw new RegistrationClosedException('Registration is closed');
        }

        $email = Email::from($cmd->userName);

        if ($this->repository->emailExists($email)) {
            throw new DuplicateEmailException('Email already registered: ' . $email);
        }

        if (!$this->captcha->verify(
            ['g-recaptcha-response' => $cmd->captchaResponse, 'h-captcha-response' => $cmd->hCaptchaResponse],
            $remoteAddr
        )) {
            throw new CaptchaVerificationFailedException('CAPTCHA verification failed');
        }

        $this->bootstrapLegacyHelpers();

        $userLevel = '2';
        $userAdminObfuscate = $userLevel === '0' ? 0 : 1;

        $passwordHash = password_hash($cmd->password, PASSWORD_BCRYPT);

        require_once CLASSES . 'phpass/PasswordHash.php';
        $hasher = new \PasswordHash(8, false);
        $questionAnswerHash = $hasher->HashPassword(sterilize($cmd->userQuestionAnswer));

        $registrantId = $this->repository->insertUser([
            'user_name' => $email->value(),
            'userLevel' => $userLevel,
            'password' => $passwordHash,
            'userQuestion' => sterilize($cmd->userQuestion),
            'userQuestionAnswer' => $questionAnswerHash,
            'userCreated' => date('Y-m-d H:i:s'),
            'userAdminObfuscate' => $userAdminObfuscate,
        ]);

        $purifier = $this->purifier();

        [$firstName, $lastName] = $this->processName($cmd->brewerFirstName, $cmd->brewerLastName, $purifier);
        $stateProvince = $this->resolveStateProvince($cmd);
        $clubs = $this->resolveClub($cmd->brewerClubs, $cmd->brewerClubsOther, $clubAllowlist, $purifier);
        [$judgeLocation, $stewardLocation] = $this->resolveLocationPreferences($cmd);

        $this->repository->insertBrewerProfile([
            'uid' => $registrantId->value(),
            'brewerFirstName' => blank_to_null($firstName),
            'brewerLastName' => blank_to_null($lastName),
            'brewerAddress' => blank_to_null(sterilize($purifier->purify($cmd->brewerAddress))),
            'brewerCity' => blank_to_null(sterilize($purifier->purify($cmd->brewerCity))),
            'brewerState' => blank_to_null($stateProvince),
            'brewerZip' => blank_to_null(sterilize($cmd->brewerZip)),
            'brewerCountry' => blank_to_null(sterilize($cmd->brewerCountry)),
            'brewerPhone1' => blank_to_null(sterilize($cmd->brewerPhone1)),
            'brewerPhone2' => blank_to_null(sterilize($cmd->brewerPhone2)),
            'brewerClubs' => blank_to_null($clubs),
            'brewerEmail' => blank_to_null($email->value()),
            'brewerDropOff' => blank_to_null(sterilize($cmd->brewerDropOff)),
            'brewerStaff' => blank_to_null($cmd->brewerStaff),
            'brewerSteward' => blank_to_null($cmd->brewerSteward),
            'brewerJudge' => blank_to_null($cmd->brewerJudge),
            'brewerJudgeWaiver' => blank_to_null($cmd->brewerJudgeWaiver),
            'brewerAHA' => blank_to_null(sterilize($cmd->brewerAHA)),
            'brewerMHP' => blank_to_null(sterilize($cmd->brewerMHP)),
            'brewerProAm' => blank_to_null(sterilize($cmd->brewerProAm)),
            'brewerJudgeLocation' => blank_to_null($judgeLocation),
            'brewerStewardLocation' => blank_to_null($stewardLocation),
        ]);

        // Legacy's staff-table derivation (process_users_register.inc.php:241-250) gates
        // staff_judge/staff_steward on $go == "judge"/"steward" - a route this entrant
        // registration form never uses ($go is always "entrant" here) - and never sets
        // staff_staff at all outside the admin-add-user path. So for this scope, the
        // staff table always gets 0/0/0; the brewerJudge/brewerSteward/brewerStaff
        // opt-in answers are still recorded on the brewer table above, they just don't
        // grant staff privileges through this route. Making them do so is a deliberate
        // future improvement, tracked alongside the deferred go=judge/go=steward
        // registration variants (not this task's scope).
        $staffRow = [
            'staff_judge' => 0,
            'staff_judge_bos' => 0,
            'staff_steward' => 0,
            'staff_organizer' => 0,
            'staff_staff' => 0,
        ];

        if ($this->repository->staffRowExists($registrantId->value())) {
            $this->repository->updateStaffRow($registrantId->value(), $staffRow);
        } else {
            $this->repository->insertStaffRow(['uid' => $registrantId->value()] + $staffRow);
        }

        return $registrantId;
    }

    private function purifier(): \HTMLPurifier
    {
        require_once CLASSES . 'htmlpurifier/HTMLPurifier.standalone.php';
        return new \HTMLPurifier(\HTMLPurifier_Config::createDefault());
    }

    /** @return array{0: string, 1: string} */
    private function processName(string $rawFirst, string $rawLast, \HTMLPurifier $purifier): array
    {
        $fname = $purifier->purify($rawFirst);
        $lname = $purifier->purify($rawLast);

        $languageFolder = $_SESSION['prefsLanguageFolder'] ?? 'en';

        if (!in_array($languageFolder, self::NAME_CHECK_LANGS, true)) {
            return [sterilize($fname), sterilize($lname)];
        }

        require_once CLASSES . 'capitalize_name/parser.php';
        $parser = new \FullNameParser();
        $parsed = $parser->parse_name($fname . ' ' . $lname);

        $firstName = '';
        if (!empty($parsed['salutation'])) {
            $firstName .= $parsed['salutation'] . ' ';
        }
        $firstName .= $parsed['fname'];
        if (!empty($parsed['initials'])) {
            $firstName .= ' ' . $parsed['initials'];
        }

        $lastName = in_array($languageFolder, self::LAST_NAME_EXCEPTION_LANGS, true)
            ? standardize_name($parsed['lname'])
            : $parsed['lname'];
        if (!empty($parsed['suffix'])) {
            $lastName .= ' ' . $parsed['suffix'];
        }

        return [$firstName, $lastName];
    }

    private function resolveStateProvince(RegisterEntrantCommand $cmd): string
    {
        $state = match (true) {
            $cmd->brewerStateUS !== '' => $cmd->brewerStateUS,
            $cmd->brewerStateCA !== '' => $cmd->brewerStateCA,
            $cmd->brewerStateAUS !== '' => $cmd->brewerStateAUS,
            $cmd->brewerStateNon !== '' => $cmd->brewerStateNon,
            default => '',
        };
        if (strlen($state) <= 2) {
            $state = strtoupper($state);
        }
        // sterilize('') returns NULL (paths.php's `$sterilize == NULL` loose
        // check treats '' as NULL), which would violate this method's :string
        // contract. blank_to_null() at the call site (register(), below) is
        // what turns a blank state into a NULL DB value - same as every other
        // sterilize()d field on this row - so preserve '' here rather than
        // let sterilize() collapse it early.
        return sterilize($state) ?? '';
    }

    private function resolveClub(string $submitted, string $other, array $allowlist, object $purifier): ?string
    {
        if ($submitted === '' || $submitted === '0') {
            return null;
        }
        if ($submitted === 'Other') {
            return $other === '' ? 'Other' : ucwords($purifier->purify($other));
        }
        if (in_array($submitted, $allowlist, true)) {
            return $submitted;
        }
        return null;
    }

    /** @return array{0: ?string, 1: ?string} [judgeLocation, stewardLocation] */
    private function resolveLocationPreferences(RegisterEntrantCommand $cmd): array
    {
        $judgeLocation = null;
        if ($cmd->brewerJudgeLocation !== null) {
            $values = is_array($cmd->brewerJudgeLocation) ? $cmd->brewerJudgeLocation : [$cmd->brewerJudgeLocation];
            $parts = array_map(fn ($v) => sterilize($v), $values);
            $judgeLocation = implode(',', $parts);
        }

        $stewardLocation = null;
        if ($cmd->brewerStewardLocation !== null) {
            $values = is_array($cmd->brewerStewardLocation) ? $cmd->brewerStewardLocation : [$cmd->brewerStewardLocation];
            $parts = [];
            foreach ($values as $value) {
                [$submittedFlag, $locId] = array_pad(explode('-', $value, 2), 2, '');
                $type = $this->repository->judgingLocationType((int) $locId);
                $flag = $type === '2' ? $submittedFlag : 'N';
                $parts[] = $flag . '-' . $locId;
            }
            $stewardLocation = sterilize(implode(',', $parts));
        }

        return [$judgeLocation, $stewardLocation];
    }

    private function bootstrapLegacyHelpers(): void
    {
        if (!function_exists('sterilize')) {
            require_once ROOT . 'paths.php';
        }
        if (!function_exists('blank_to_null')) {
            require_once LIB . 'process.lib.php';
        }
    }

    public function isRegistrationOpen(): bool
    {
        return $this->windowState()['registrationOpen'];
    }

    public function isJudgeWindowOpen(): bool
    {
        return $this->windowState()['judgeWindowOpen'];
    }

    /** @return array{registrationOpen: bool, judgeWindowOpen: bool} */
    private function windowState(): array
    {
        // open_or_closed() lives in lib/common.lib.php, NOT paths.php -
        // unlike sterilize()/blank_to_null() above, requiring paths.php
        // alone does not define it. common.lib.php's own top-level
        // `include(LIB.'date_time.lib.php')` needs the LIB constant, which
        // only paths.php defines, so paths.php must be loaded first (it
        // already is by the time this runs in a real request - Connection::
        // class's DI factory in container.php requires it before
        // RegistrationRepository/RegistrationService can be built at all -
        // but this guard makes the method correct standalone too, matching
        // the same require-paths.php-then-common.lib.php shape
        // src/Domain/Entry/Adapter/LegacyQueryAdapter.php uses for other
        // common.lib.php-only functions).
        if (!function_exists('open_or_closed')) {
            if (!defined('LIB')) {
                require_once ROOT . 'paths.php';
            }
            require_once LIB . 'common.lib.php';
        }

        $dates = $this->repository->contestDates();
        if ($dates === null) {
            return ['registrationOpen' => true, 'judgeWindowOpen' => true];
        }

        $now = time();
        $registrationOpen = open_or_closed($now, $dates['contestRegistrationOpen'], $dates['contestRegistrationDeadline']) === 1;
        $judgeWindowOpen = open_or_closed($now, $dates['contestJudgeOpen'], $dates['contestJudgeDeadline']) === 1;

        // Mirrors constants.inc.php:262-265: once any judging session has
        // started, legacy force-closes both registration and entry windows
        // regardless of the contest_info dates above, so entries/accounts
        // can't change once judging begins.
        if ($this->repository->anyJudgingSessionStarted()) {
            return ['registrationOpen' => false, 'judgeWindowOpen' => false];
        }

        return ['registrationOpen' => $registrationOpen, 'judgeWindowOpen' => $judgeWindowOpen];
    }
}
