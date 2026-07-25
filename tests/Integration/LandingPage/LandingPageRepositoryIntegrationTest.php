<?php

declare(strict_types=1);

namespace BCOEM\Tests\Integration\LandingPage;

use BCOEM\Tests\Integration\IntegrationTestCase;
use Bcoem\Database\Connection;
use Bcoem\Domain\LandingPage\Model\Archive;
use Bcoem\Domain\LandingPage\Model\Contact;
use Bcoem\Domain\LandingPage\Model\Sponsor;
use Bcoem\Domain\LandingPage\Model\WinnerMethod;
use Bcoem\Domain\LandingPage\Repository\LandingPageRepository;

final class LandingPageRepositoryIntegrationTest extends IntegrationTestCase
{
    private LandingPageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new LandingPageRepository(new Connection(self::$conn), self::$pfx);
    }

    public function test_reads_contest_windows_limits_progress_and_locations(): void
    {
        $now = time();
        $this->updateSingleton('contest_info', [
            'contestName' => 'Fixture Invitational',
            'contestHost' => 'Fixture Club',
            'contestHostWebsite' => 'https://fixture.example',
            'contestHostLocation' => 'Chicago, IL',
            'contestLogo' => '/images/fixture.svg',
            'contestRegistrationOpen' => 1000,
            'contestRegistrationDeadline' => 2000,
            'contestEntryOpen' => 1100,
            'contestEntryDeadline' => 2100,
            'contestJudgeOpen' => 1200,
            'contestJudgeDeadline' => 2200,
            'contestDropoffOpen' => '',
            'contestDropoffDeadline' => '',
            'contestShippingOpen' => 1300,
            'contestShippingDeadline' => 2300,
            'contestShippingName' => 'Fixture Shipping',
            'contestShippingAddress' => '123 Fixture Street',
            'contestAwards' => '<p>Fixture awards.</p>',
            'contestAwardsLocName' => 'Fixture Hall',
            'contestAwardsLocation' => '456 Ceremony Avenue',
            'contestAwardsLocTime' => 2400,
        ]);
        $this->updateSingleton('preferences', [
            'prefsEntryLimit' => 10,
            'prefsEntryLimitPaid' => 5,
            'prefsDisplayWinners' => 'Y',
            'prefsWinnerDelay' => $now + 7200,
        ]);
        $this->insert('brewing', [
            'brewName' => 'Paid Fixture',
            'brewPaid' => 1,
        ]);
        $this->insert('brewing', [
            'brewName' => 'Unpaid Fixture',
            'brewPaid' => 0,
        ]);
        $this->insert('judging_locations', [
            'judgingLocType' => 0,
            'judgingDate' => $now - 3600,
            'judgingDateEnd' => $now + 3600,
            'judgingLocName' => 'Real Fixture Session',
        ]);
        $this->insert('judging_locations', [
            'judgingLocType' => 2,
            'judgingDate' => $now - 172800,
            'judgingDateEnd' => $now - 172800,
            'judgingLocName' => 'Non-Judging Fixture Session',
        ]);

        $overview = $this->repository->contestOverview();
        self::assertNotNull($overview);
        self::assertSame('Fixture Invitational', $overview->name);
        self::assertSame('Fixture Club', $overview->hostName);
        self::assertSame('https://fixture.example', $overview->hostWebsite);

        $windows = $this->repository->competitionWindows();
        self::assertNotNull($windows);
        self::assertSame(2000, $windows->registrationClosesAt);
        self::assertNull($windows->dropoffOpensAt);
        self::assertNull($windows->dropoffClosesAt);
        self::assertSame(1300, $windows->shippingOpensAt);
        self::assertSame(2300, $windows->shippingClosesAt);

        $limits = $this->repository->competitionLimits();
        self::assertSame(2, $limits->entryCount);
        self::assertSame(1, $limits->paidEntryCount);
        self::assertSame(10, $limits->entryLimit);
        self::assertSame(5, $limits->paidEntryLimit);
        self::assertSame(9, $limits->nearLimitThreshold);

        $judging = $this->repository->judgingProgress();
        self::assertTrue($judging->started);
        self::assertFalse($judging->ended);
        self::assertTrue($judging->displayWinners);
        self::assertSame($now + 7200, $judging->winnerReleaseAt);

        $locations = $this->repository->locations();
        self::assertSame('Fixture Shipping', $locations->shippingName);
        self::assertSame('123 Fixture Street', $locations->shippingAddress);
        self::assertSame('<p>Fixture awards.</p>', $locations->awardsDetails);
        self::assertSame('Fixture Hall', $locations->awardsLocationName);
        self::assertSame('456 Ceremony Avenue', $locations->awardsLocation);
        self::assertSame(2400, $locations->awardsAt);
    }

    public function test_returns_null_when_required_singleton_rows_are_absent(): void
    {
        self::$conn->query('DELETE FROM `' . self::$pfx . 'contest_info`');

        self::assertNull($this->repository->contestOverview());
        self::assertNull($this->repository->competitionWindows());
    }

    public function test_maps_contacts_enabled_sponsors_and_visible_archives(): void
    {
        self::$conn->query('DELETE FROM `' . self::$pfx . 'contacts`');
        self::$conn->query('DELETE FROM `' . self::$pfx . 'sponsors`');
        self::$conn->query('DELETE FROM `' . self::$pfx . 'archive`');

        $this->insert('contacts', [
            'contactFirstName' => ' Ada ',
            'contactLastName' => ' Brewer ',
            'contactPosition' => ' Organizer ',
            'contactEmail' => ' ada@example.test ',
        ]);
        $this->insert('sponsors', [
            'sponsorName' => 'Hidden Sponsor',
            'sponsorURL' => 'https://hidden.example',
            'sponsorLevel' => 1,
            'sponsorEnable' => 0,
        ]);
        $this->insert('sponsors', [
            'sponsorName' => 'Visible Sponsor',
            'sponsorURL' => 'https://sponsor.example',
            'sponsorImage' => '/images/sponsor.png',
            'sponsorText' => 'Fixture supporter',
            'sponsorLocation' => 'Chicago',
            'sponsorLevel' => 2,
            'sponsorEnable' => 1,
        ]);
        $this->insert('archive', [
            'archiveSuffix' => '2024',
            'archiveWinnerMethod' => 0,
            'archiveStyleSet' => 'BJCP2021',
            'archiveDisplayWinners' => 'N',
        ]);
        $this->insert('archive', [
            'archiveSuffix' => '2025',
            'archiveWinnerMethod' => 1,
            'archiveStyleSet' => 'BJCP2021',
            'archiveDisplayWinners' => 'Y',
        ]);

        $contacts = $this->repository->contacts();
        self::assertContainsOnlyInstancesOf(Contact::class, $contacts);
        self::assertCount(1, $contacts);
        self::assertSame('Ada', $contacts[0]->firstName);
        self::assertSame('ada@example.test', $contacts[0]->email);

        $sponsors = $this->repository->sponsors();
        self::assertContainsOnlyInstancesOf(Sponsor::class, $sponsors);
        self::assertCount(1, $sponsors);
        self::assertSame('Visible Sponsor', $sponsors[0]->name);
        self::assertSame('https://sponsor.example', $sponsors[0]->websiteUrl);
        self::assertSame(2, $sponsors[0]->level);

        $archives = $this->repository->visibleArchives();
        self::assertContainsOnlyInstancesOf(Archive::class, $archives);
        self::assertCount(1, $archives);
        self::assertSame('2025', $archives[0]->suffix);
        self::assertSame(1, $archives[0]->winnerMethod);
    }

    public function test_optional_collections_are_empty_when_rows_are_absent(): void
    {
        self::$conn->query('DELETE FROM `' . self::$pfx . 'contacts`');
        self::$conn->query('DELETE FROM `' . self::$pfx . 'sponsors`');
        self::$conn->query('DELETE FROM `' . self::$pfx . 'archive`');

        self::assertSame([], $this->repository->contacts());
        self::assertSame([], $this->repository->sponsors());
        self::assertSame([], $this->repository->visibleArchives());
    }

    /**
     * @dataProvider winnerMethods
     */
    public function test_maps_current_winners_for_each_configured_grouping(
        int $configuredMethod,
        WinnerMethod $expectedMethod,
        string $styleSet,
        string $styleGroup,
        string $styleNumber,
        string $categorySort,
        bool $duplicateStyle,
        string $styleActive,
        int $expectedRowCount,
        string $expectedGroup,
        string $expectedStyle,
        int $expectedEntryCount,
    ): void {
        self::$conn->query('DELETE FROM `' . self::$pfx . 'judging_scores`');
        self::$conn->query('DELETE FROM `' . self::$pfx . 'judging_tables`');
        self::$conn->query('DELETE FROM `' . self::$pfx . 'brewing`');

        $styleId = $this->insert('styles', [
            'brewStyleGroup' => $styleGroup,
            'brewStyleNum' => $styleNumber,
            'brewStyle' => 'Fixture Style',
            'brewStyleCategory' => 'Fixture Category',
            'brewStyleVersion' => $styleSet,
            'brewStyleActive' => $styleActive,
        ]);
        if ($duplicateStyle) {
            $this->insert('styles', [
                'brewStyleGroup' => $styleGroup,
                'brewStyleNum' => $styleNumber,
                'brewStyle' => 'Duplicate Fixture Style',
                'brewStyleCategory' => 'Duplicate Fixture Category',
                'brewStyleVersion' => $styleSet,
                'brewStyleActive' => $styleActive,
            ]);
        }
        $tableId = $this->insert('judging_tables', [
            'tableName' => 'Fixture Table',
            'tableStyles' => (string) $styleId,
            'tableNumber' => 1,
        ]);
        $entryId = $this->insert('brewing', [
            'brewName' => 'Winning Fixture',
            'brewStyle' => 'Fixture Style',
            'brewCategory' => $styleGroup,
            'brewCategorySort' => $categorySort,
            'brewSubCategory' => $styleNumber,
            'brewInfo' => 'Citrus^notes',
            'brewBrewerID' => 1,
            'brewPaid' => 1,
            'brewReceived' => 1,
            'brewCoBrewer' => 'Grace Brewer',
        ]);
        $this->insert('brewing', [
            'brewName' => 'Unreceived Fixture',
            'brewStyle' => 'Fixture Style',
            'brewCategory' => $styleGroup,
            'brewCategorySort' => $categorySort,
            'brewSubCategory' => $styleNumber,
            'brewBrewerID' => 1,
            'brewPaid' => 0,
            'brewReceived' => 0,
        ]);
        $this->insert('judging_scores', [
            'eid' => $entryId,
            'bid' => 1,
            'scoreTable' => $tableId,
            'scoreEntry' => 42.5,
            'scorePlace' => 1,
        ]);
        $this->updateSingleton('preferences', [
            'prefsWinnerMethod' => $configuredMethod,
            'prefsStyleSet' => $styleSet,
        ]);

        $summary = $this->repository->winnerSummary();

        self::assertSame($expectedMethod, $summary->method);
        self::assertSame($styleSet, $summary->styleSet);
        self::assertCount($expectedRowCount, $summary->rows);
        if ($expectedRowCount === 0) {
            return;
        }
        self::assertSame($expectedGroup, $summary->rows[0]->groupName);
        self::assertSame($expectedEntryCount, $summary->rows[0]->entryCount);
        self::assertSame(1, $summary->rows[0]->place);
        self::assertSame('Default Admin', $summary->rows[0]->brewerName);
        self::assertSame('Grace Brewer', $summary->rows[0]->coBrewerName);
        self::assertSame('Winning Fixture', $summary->rows[0]->entryName);
        self::assertSame($expectedStyle, $summary->rows[0]->style);
        self::assertSame('Citrus^notes', $summary->rows[0]->entryInfo);
        self::assertSame('Homebrewers Club of McDowell and Surrounding Counties', $summary->rows[0]->club);
        self::assertSame(42.5, $summary->rows[0]->score);
    }

    /**
     * @return iterable<string, array{
     *     int, WinnerMethod, string, string, string, string, bool, string, int, string, string, int
     * }>
     */
    public static function winnerMethods(): iterable
    {
        yield 'overall' => [
            0,
            WinnerMethod::Overall,
            'FIXTURE',
            '01',
            'A',
            '01',
            false,
            'Y',
            1,
            'Fixture Table',
            '01A: Fixture Style',
            1,
        ];
        yield 'category' => [
            1,
            WinnerMethod::Category,
            'FIXTURE',
            '01',
            'A',
            '01',
            true,
            'Y',
            1,
            'Fixture Category',
            '01A: Fixture Style',
            1,
        ];
        yield 'subcategory' => [
            2,
            WinnerMethod::Subcategory,
            'FIXTURE',
            '01',
            'A',
            '01',
            false,
            'Y',
            1,
            '01A: Fixture Style',
            '01A: Fixture Style',
            2,
        ];
        yield 'brewers association subcategory' => [
            2,
            WinnerMethod::Subcategory,
            'BA',
            '06',
            '001',
            '99',
            false,
            'Y',
            1,
            'Fixture Style',
            'Fixture Category: Fixture Style',
            1,
        ];
        yield 'inactive subcategory' => [
            2,
            WinnerMethod::Subcategory,
            'FIXTURE',
            '02',
            'B',
            '02',
            false,
            'N',
            0,
            '',
            '',
            0,
        ];
        yield 'aabc subcategory' => [
            2,
            WinnerMethod::Subcategory,
            'AABC',
            '01',
            '04',
            '01',
            false,
            'Y',
            1,
            '1.4: Fixture Style',
            '1.4: Fixture Style',
            2,
        ];
    }

    public function test_rejects_an_unsafe_table_prefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LandingPageRepository(new Connection(self::$conn), 'baseline_; DROP TABLE users');
    }

    public function test_accepts_an_empty_table_prefix_for_unprefixed_installations(): void
    {
        $repository = new LandingPageRepository(new Connection(self::$conn), '');

        self::assertInstanceOf(LandingPageRepository::class, $repository);
    }

    public function test_invalid_winner_method_falls_back_to_an_empty_overall_summary(): void
    {
        $this->updateSingleton('preferences', [
            'prefsWinnerMethod' => 99,
            'prefsStyleSet' => 'FIXTURE',
        ]);

        $summary = $this->repository->winnerSummary();

        self::assertSame(WinnerMethod::Overall, $summary->method);
        self::assertSame('FIXTURE', $summary->styleSet);
        self::assertSame([], $summary->rows);
    }

    /** @param array<string, int|string> $columns */
    private function updateSingleton(string $table, array $columns): void
    {
        $set = implode(', ', array_map(
            fn (string $column): string => "`{$column}` = '"
                . self::$conn->real_escape_string((string) $columns[$column])
                . "'",
            array_keys($columns),
        ));

        self::$conn->query(
            'UPDATE `' . self::$pfx . $table . '` SET ' . $set . ' WHERE id = 1',
        );
    }
}
