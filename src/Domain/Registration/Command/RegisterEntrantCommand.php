<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Command;

/**
 * Raw-POST hydration for the entrant self-registration form. Field names
 * match sections/register.sec.php's real <input name="..."> attributes
 * (confirmed against e2e/helpers/auth.ts's registerEntrant() helper, which
 * fills the real public form).
 */
final class RegisterEntrantCommand
{
    public readonly string $userName;
    public readonly string $password;
    public readonly string $userQuestion;
    public readonly string $userQuestionAnswer;
    public readonly string $brewerFirstName;
    public readonly string $brewerLastName;
    public readonly string $brewerAddress;
    public readonly string $brewerCity;
    public readonly string $brewerZip;
    public readonly string $brewerCountry;
    public readonly string $brewerPhone1;
    public readonly string $brewerPhone2;
    public readonly string $brewerStateUS;
    public readonly string $brewerStateCA;
    public readonly string $brewerStateAUS;
    public readonly string $brewerStateNon;
    public readonly string $brewerClubs;
    public readonly string $brewerJudge;
    public readonly string $brewerSteward;
    public readonly string $brewerStaff;
    public readonly array|string|null $brewerJudgeLocation;
    public readonly array|string|null $brewerStewardLocation;
    public readonly string $captchaResponse;
    public readonly string $hCaptchaResponse;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        foreach (['user_name', 'password', 'userQuestion', 'userQuestionAnswer',
                  'brewerFirstName', 'brewerLastName', 'brewerAddress', 'brewerCity',
                  'brewerZip', 'brewerCountry', 'brewerPhone1'] as $required) {
            if (!isset($data[$required]) || $data[$required] === '') {
                throw new \InvalidArgumentException("Missing required field: {$required}");
            }
        }

        $this->userName = (string) $data['user_name'];
        $this->password = (string) $data['password'];
        $this->userQuestion = (string) $data['userQuestion'];
        $this->userQuestionAnswer = (string) $data['userQuestionAnswer'];
        $this->brewerFirstName = (string) $data['brewerFirstName'];
        $this->brewerLastName = (string) $data['brewerLastName'];
        $this->brewerAddress = (string) $data['brewerAddress'];
        $this->brewerCity = (string) $data['brewerCity'];
        $this->brewerZip = (string) $data['brewerZip'];
        $this->brewerCountry = (string) $data['brewerCountry'];
        $this->brewerPhone1 = (string) $data['brewerPhone1'];
        $this->brewerPhone2 = (string) ($data['brewerPhone2'] ?? '');
        $this->brewerStateUS = (string) ($data['brewerStateUS'] ?? '');
        $this->brewerStateCA = (string) ($data['brewerStateCA'] ?? '');
        $this->brewerStateAUS = (string) ($data['brewerStateAUS'] ?? '');
        $this->brewerStateNon = (string) ($data['brewerStateNon'] ?? '');
        $this->brewerClubs = (string) ($data['brewerClubs'] ?? '');
        $this->brewerJudge = (string) ($data['brewerJudge'] ?? 'N');
        $this->brewerSteward = (string) ($data['brewerSteward'] ?? 'N');
        $this->brewerStaff = (string) ($data['brewerStaff'] ?? '');
        $this->brewerJudgeLocation = $data['brewerJudgeLocation'] ?? null;
        $this->brewerStewardLocation = $data['brewerStewardLocation'] ?? null;
        $this->captchaResponse = (string) ($data['g-recaptcha-response'] ?? '');
        $this->hCaptchaResponse = (string) ($data['h-captcha-response'] ?? '');
    }
}
