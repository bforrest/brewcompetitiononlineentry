<?php

declare(strict_types=1);

namespace BCOEM\Tests\Integration\Registration;

use BCOEM\Tests\Integration\IntegrationTestCase;
use Bcoem\Database\Connection;
use Bcoem\Domain\Registration\Repository\RegistrationOptionsRepository;

final class RegistrationOptionsRepositoryIntegrationTest extends IntegrationTestCase
{
    private RegistrationOptionsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RegistrationOptionsRepository(new Connection(self::$conn));
    }

    public function test_options_expose_contest_text_enabled_logistics_and_drop_off_choices(): void
    {
        $this->updateContest([
            'contestName' => 'Fixture Invitational',
            'contestRules' => json_encode([
                'competition_rules' => '<p>Fixture registration guidance.</p>',
                'competition_packing_shipping' => '<p>Fixture shipping guidance.</p>',
            ], JSON_THROW_ON_ERROR),
            'contestShippingOpen' => time() - 3600,
            'contestShippingDeadline' => time() + 3600,
            'contestDropoffOpen' => time() - 3600,
            'contestDropoffDeadline' => time() + 3600,
        ]);
        $this->updatePreferences([
            'prefsShipping' => 1,
            'prefsDropOff' => 1,
        ]);
        $firstDropOffId = $this->insert('drop_off', [
            'dropLocationName' => 'Alpha Fixture Drop-off',
            'dropLocation' => '100 Fixture Lane',
        ]);
        $lastDropOffId = $this->insert('drop_off', [
            'dropLocationName' => 'Zulu Fixture Drop-off',
            'dropLocation' => '200 Fixture Lane',
        ]);

        $options = $this->repository->options();

        self::assertSame('Fixture Invitational', $options->title);
        self::assertSame('', $options->guidance);
        self::assertSame('Alpha Fixture Drop-off', $options->dropOffChoices[(string) $firstDropOffId]);
        self::assertSame('Zulu Fixture Drop-off', $options->dropOffChoices[(string) $lastDropOffId]);
        self::assertLessThan(
            array_search('Zulu Fixture Drop-off', array_values($options->dropOffChoices), true),
            array_search('Alpha Fixture Drop-off', array_values($options->dropOffChoices), true),
        );
        self::assertSame(['shipping' => true, 'dropOff' => true], $options->availability);
        self::assertSame('United States', $options->countryChoices['United States']);
        self::assertSame('Texas [TX]', $options->stateChoices['TX']);
    }

    public function test_options_exclude_drop_off_choices_when_drop_off_is_disabled(): void
    {
        $this->updateContest([
            'contestShippingOpen' => time() - 7200,
            'contestShippingDeadline' => time() - 3600,
            'contestDropoffOpen' => time() - 7200,
            'contestDropoffDeadline' => time() - 3600,
        ]);
        $this->updatePreferences(['prefsShipping' => 1, 'prefsDropOff' => 1]);

        $options = $this->repository->options();

        self::assertSame([], $options->dropOffChoices);
        self::assertSame(['shipping' => false, 'dropOff' => false], $options->availability);
    }

    public function test_options_fall_back_to_enabled_logistics_when_date_windows_are_absent(): void
    {
        $this->updateContest([
            'contestShippingOpen' => '',
            'contestShippingDeadline' => '',
            'contestDropoffOpen' => '',
            'contestDropoffDeadline' => '',
        ]);
        $this->updatePreferences(['prefsShipping' => 1, 'prefsDropOff' => 1]);

        $options = $this->repository->options();

        self::assertSame(['shipping' => true, 'dropOff' => true], $options->availability);
    }

    /** @param array<string, int|string> $columns */
    private function updateContest(array $columns): void
    {
        $this->updateSingleton('contest_info', $columns);
    }

    /** @param array<string, int|string> $columns */
    private function updatePreferences(array $columns): void
    {
        $this->updateSingleton('preferences', $columns);
    }

    /** @param array<string, int|string> $columns */
    private function updateSingleton(string $table, array $columns): void
    {
        $set = implode(', ', array_map(
            fn (string $column): string => "`{$column}` = '" . self::$conn->real_escape_string((string) $columns[$column]) . "'",
            array_keys($columns)
        ));
        self::$conn->query('UPDATE `' . self::$pfx . $table . '` SET ' . $set . ' WHERE id = 1');
    }
}
