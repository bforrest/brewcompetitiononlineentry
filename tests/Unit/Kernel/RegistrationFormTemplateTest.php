<?php

declare(strict_types=1);

namespace BCOEM\Tests\Unit\Kernel;

use Bcoem\Domain\Registration\Form\RegistrationFormData;
use Bcoem\Domain\Registration\Form\RegistrationFormOptions;
use PHPUnit\Framework\TestCase;

final class RegistrationFormTemplateTest extends TestCase
{
    public function test_renders_the_complete_content_only_standard_entrant_form(): void
    {
        require_once ROOT . 'templates/helpers.php';

        $form = new RegistrationFormData(
            values: [
                'user_name' => 'entrant@example.test',
                'brewerFirstName' => '<Ada>',
                'brewerLastName' => 'Brewer',
                'brewerCountry' => 'United States',
                'brewerStateUS' => 'TX',
                'brewerDropOff' => '2',
            ],
            fieldErrors: ['user_name' => 'That email address is already registered.'],
            generalErrors: ['Please correct the errors below.'],
        );
        $options = new RegistrationFormOptions(
            title: 'Example Competition',
            guidance: 'Register to enter the competition.',
            countryChoices: ['United States' => 'United States', 'Canada' => 'Canada'],
            stateChoices: ['TX' => 'Texas [TX]'],
            dropOffChoices: ['2' => 'Downtown <Drop-off>'],
            availability: ['judge' => true, 'steward' => true],
        );

        $output = (static function (RegistrationFormData $form, RegistrationFormOptions $options): string {
            ob_start();
            require ROOT . 'templates/Registration/register-form.php';

            return (string) ob_get_clean();
        })($form, $options);

        self::assertStringContainsString('<form', $output);
        self::assertStringContainsString('class="form-horizontal"', $output);
        self::assertStringContainsString('method="post"', $output);
        self::assertStringContainsString('action="/register"', $output);
        self::assertStringContainsString('Account details', $output);
        self::assertStringContainsString('Contact and address', $output);
        self::assertStringContainsString('Entry logistics', $output);
        self::assertStringContainsString('Volunteer preferences', $output);
        self::assertStringContainsString('Waiver', $output);
        self::assertStringContainsString('class="alert alert-danger"', $output);
        self::assertStringContainsString('Please correct the errors below.', $output);
        self::assertStringContainsString('That email address is already registered.', $output);
        self::assertStringContainsString('name="brewerDropOff"', $output);
        self::assertStringContainsString('Downtown &lt;Drop-off&gt;', $output);
        self::assertStringContainsString('name="brewerJudge"', $output);
        self::assertStringContainsString('name="brewerSteward"', $output);
        self::assertStringContainsString('name="brewerJudgeWaiver"', $output);
        self::assertStringContainsString('required', $output);
        self::assertStringContainsString('text-warning', $output);
        self::assertStringContainsString('value="&lt;Ada&gt;"', $output);
        self::assertStringContainsString('The information you provide beyond your first name, last name, and club is strictly for record-keeping and contact purposes.', $output);
        self::assertStringContainsString('To register, create your account by filling out the fields below.', $output);
        self::assertStringNotContainsString('Register to enter the competition.', $output);
        self::assertStringNotContainsString('<!DOCTYPE', $output);
        self::assertStringNotContainsString('<html', $output);
        self::assertStringNotContainsString('<head', $output);
        self::assertStringNotContainsString('<body', $output);
    }
}
