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
            'contestRules' => '<p>Fixture registration guidance.</p>',
        ]);
        $this->updatePreferences([
            'prefsShipping' => 1,
            'prefsDropOff' => 1,
        ]);
        $dropOffId = $this->insert('drop_off', [
            'dropLocationName' => 'Fixture Homebrew Supply',
            'dropLocation' => '100 Fixture Lane',
        ]);

        $options = $this->repository->options();

        self::assertSame('Fixture Invitational', $options->title);
        self::assertSame('<p>Fixture registration guidance.</p>', $options->guidance);
        self::assertSame('Fixture Homebrew Supply', $options->dropOffChoices[(string) $dropOffId]);
        self::assertSame(['shipping' => true, 'dropOff' => true], $options->availability);
        self::assertSame([], $options->countryChoices);
        self::assertSame([], $options->stateChoices);
    }

    public function test_options_exclude_drop_off_choices_when_drop_off_is_disabled(): void
    {
        $this->updatePreferences(['prefsShipping' => 1, 'prefsDropOff' => 0]);

        $options = $this->repository->options();

        self::assertSame([], $options->dropOffChoices);
        self::assertSame(['shipping' => true, 'dropOff' => false], $options->availability);
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
