<?php

declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\Registration\Form;

use Bcoem\Domain\Registration\Form\RegistrationFormFactory;
use Bcoem\Domain\Registration\Form\RegistrationFormOptions;
use PHPUnit\Framework\TestCase;

final class RegistrationFormFactoryTest extends TestCase
{
    public function test_submitted_values_and_supplied_errors_survive_an_error_render(): void
    {
        $input = [
            'user_name' => 'entrant@example.test',
            'brewerFirstName' => 'Ada',
            'brewerLastName' => 'Brewer',
            'brewerCountry' => 'United States',
        ];
        $options = new RegistrationFormOptions(
            title: 'Example Competition',
            guidance: 'Registration guidance',
            countryChoices: ['United States' => 'United States'],
            availability: ['judge' => true],
        );

        $form = (new RegistrationFormFactory())->fromRequest(
            $input,
            $options,
            ['user_name' => 'That email address is already registered.'],
            ['Please correct the errors below.'],
        );

        $this->assertSame($input, $form->values);
        $this->assertSame('That email address is already registered.', $form->fieldErrors['user_name']);
        $this->assertSame(['Please correct the errors below.'], $form->generalErrors);
    }

    public function test_missing_required_names_produce_keyed_errors(): void
    {
        $form = (new RegistrationFormFactory())->fromRequest(
            ['user_name' => '', 'brewerFirstName' => 'Ada'],
            new RegistrationFormOptions('Example Competition', 'Registration guidance'),
        );

        $this->assertSame('This field is required.', $form->fieldErrors['user_name']);
        $this->assertSame('This field is required.', $form->fieldErrors['password']);
        $this->assertSame('This field is required.', $form->fieldErrors['password-confirm']);
        $this->assertSame('This field is required.', $form->fieldErrors['brewerLastName']);
        $this->assertSame('This field is required.', $form->fieldErrors['brewerCountry']);
        $this->assertSame('This field is required.', $form->fieldErrors['brewerDropOff']);
        $this->assertArrayNotHasKey('brewerFirstName', $form->fieldErrors);
    }

    public function test_requires_the_country_appropriate_state_or_province(): void
    {
        $factory = new RegistrationFormFactory();
        $options = new RegistrationFormOptions('Example Competition', 'Registration guidance');

        $us = $factory->fromRequest(['brewerCountry' => 'United States'], $options);
        $canada = $factory->fromRequest(['brewerCountry' => 'Canada'], $options);
        $other = $factory->fromRequest(['brewerCountry' => 'Belgium'], $options);

        $this->assertSame('This field is required.', $us->fieldErrors['brewerStateUS']);
        $this->assertSame('This field is required.', $canada->fieldErrors['brewerStateCA']);
        $this->assertSame('This field is required.', $other->fieldErrors['brewerStateNon']);
    }
}
