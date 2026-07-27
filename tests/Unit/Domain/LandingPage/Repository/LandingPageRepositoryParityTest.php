<?php

declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\LandingPage\Repository;

use Bcoem\Database\Connection;
use Bcoem\Domain\LandingPage\Model\ContactMode;
use Bcoem\Domain\LandingPage\Model\WinnerMethod;
use Bcoem\Domain\LandingPage\Repository\LandingPageRepository;
use PHPUnit\Framework\TestCase;

final class LandingPageRepositoryParityTest extends TestCase
{
    public function test_normalizes_uploaded_images_and_degrades_invalid_optional_urls(): void
    {
        $connection = new LandingPageScriptedConnection(
            static function (string $sql): array {
                if (str_contains($sql, 'FROM baseline_contest_info')) {
                    return [
                        'contestName' => 'Fixture Competition',
                        'contestHost' => 'Fixture Host',
                        'contestHostWebsite' => 'javascript:alert(1)',
                        'contestHostLocation' => 'Chicago',
                        'contestLogo' => ' fixture logo.png ',
                    ];
                }
                if (str_contains($sql, 'SELECT prefsSponsors')) {
                    return ['prefsSponsors' => 'Y', 'prefsSponsorLogos' => 'Y'];
                }
                if (str_contains($sql, 'FROM baseline_sponsors')) {
                    return [[
                        'sponsorName' => 'Fixture Sponsor',
                        'sponsorURL' => 'data:text/html,bad',
                        'sponsorImage' => 'sponsor.png',
                        'sponsorText' => 'Supporter',
                        'sponsorLocation' => 'Chicago',
                        'sponsorLevel' => 1,
                    ]];
                }

                return [];
            },
        );
        $repository = new LandingPageRepository($connection, 'baseline_');

        $overview = $repository->contestOverview();
        self::assertNotNull($overview);
        self::assertNull($overview->hostWebsite);
        self::assertSame('/user_images/fixture%20logo.png', $overview->logoPath);

        $sponsors = $repository->sponsors();
        self::assertCount(1, $sponsors);
        self::assertNull($sponsors[0]->websiteUrl);
        self::assertSame('/user_images/sponsor.png', $sponsors[0]->imagePath);
    }

    public function test_maps_rules_dropoffs_contact_mode_and_contact_ids_without_email(): void
    {
        $connection = new LandingPageScriptedConnection(
            static function (string $sql): array {
                if (str_contains($sql, 'contestRules')) {
                    return [
                        'contestRules' => json_encode([
                            'competition_rules' => '<p>Competition rules.</p>',
                        ], JSON_THROW_ON_ERROR),
                        'contestBottles' => '<p>Two plain bottles.</p>',
                    ];
                }
                if (str_contains($sql, 'contestShippingName')) {
                    return [
                        'contestShippingName' => 'Shipping',
                        'contestShippingAddress' => '123 Main',
                        'contestAwards' => null,
                        'contestAwardsLocName' => null,
                        'contestAwardsLocation' => null,
                        'contestAwardsLocTime' => null,
                        'prefsShipping' => 'Y',
                    ];
                }
                if (str_contains($sql, 'FROM baseline_drop_off')) {
                    return [[
                        'dropLocationName' => 'Local Shop',
                        'dropLocation' => '456 Oak',
                        'dropLocationPhone' => '555-0100',
                        'dropLocationWebsite' => 'javascript:alert(1)',
                        'dropLocationNotes' => 'Rear entrance',
                    ]];
                }
                if (str_contains($sql, 'prefsContact')) {
                    return [
                        'prefsContact' => 'Y',
                        'prefsEmailSMTP' => 'Y',
                        'prefsEmailUsername' => 'mailer',
                        'prefsEmailPassword' => 'secret',
                        'prefsEmailHost' => 'smtp.example.test',
                        'prefsEmailPort' => '587',
                    ];
                }
                if (str_contains($sql, 'FROM baseline_contacts')) {
                    return [[
                        'id' => 7,
                        'contactFirstName' => ' Ada ',
                        'contactLastName' => ' Brewer ',
                        'contactPosition' => ' Organizer ',
                    ]];
                }

                return [];
            },
        );
        $repository = new LandingPageRepository($connection, 'baseline_');

        $rules = $repository->competitionRules();
        self::assertSame('Competition rules.', $rules->competitionRules);
        self::assertSame('Two plain bottles.', $rules->entryAcceptanceRules);

        $locations = $repository->locations();
        self::assertTrue($locations->shippingEnabled);
        self::assertCount(1, $locations->dropoffLocations);
        self::assertSame('Local Shop', $locations->dropoffLocations[0]->name);
        self::assertNull($locations->dropoffLocations[0]->websiteUrl);

        self::assertSame(ContactMode::Form, $repository->contactMode());
        $contacts = $repository->contacts();
        self::assertSame(7, $contacts[0]->id);
        self::assertObjectNotHasProperty('email', $contacts[0]);
    }

    public function test_results_stage_begins_only_after_all_judging_starts_are_past(): void
    {
        $connection = new LandingPageScriptedConnection(
            static function (string $sql): array {
                if (str_contains($sql, 'judging_locations')) {
                    return [
                        ['judgingDate' => 1000, 'judgingDateEnd' => 1200],
                        ['judgingDate' => 2000, 'judgingDateEnd' => 2200],
                    ];
                }
                if (str_contains($sql, 'prefsDisplayWinners')) {
                    return ['prefsDisplayWinners' => 'Y', 'prefsWinnerDelay' => 2500];
                }

                return [];
            },
        );
        $repository = new LandingPageRepository($connection, 'baseline_');

        self::assertFalse($repository->judgingProgress(2000)->resultsStage);
        self::assertTrue($repository->judgingProgress(2001)->resultsStage);
    }

    public function test_archive_requires_enabled_style_set_existing_populated_score_table(): void
    {
        $connection = new LandingPageScriptedConnection(
            static function (string $sql, array $params): array {
                if (str_contains($sql, 'FROM baseline_archive')) {
                    return [
                        [
                            'archiveSuffix' => 'empty',
                            'archiveWinnerMethod' => 0,
                            'archiveStyleSet' => 'BJCP2021',
                        ],
                        [
                            'archiveSuffix' => 'nostyle',
                            'archiveWinnerMethod' => 0,
                            'archiveStyleSet' => '',
                        ],
                        [
                            'archiveSuffix' => '../unsafe',
                            'archiveWinnerMethod' => 0,
                            'archiveStyleSet' => 'BJCP2021',
                        ],
                        [
                            'archiveSuffix' => '2025',
                            'archiveWinnerMethod' => 1,
                            'archiveStyleSet' => 'BJCP2021',
                        ],
                    ];
                }
                if (str_contains($sql, 'information_schema.tables')) {
                    return ['tableCount' => in_array($params[0] ?? '', [
                        'baseline_judging_scores_empty',
                        'baseline_judging_scores_2025',
                    ], true) ? 1 : 0];
                }
                if (str_contains($sql, 'baseline_judging_scores_empty')) {
                    return ['winnerCount' => 0];
                }
                if (str_contains($sql, 'baseline_judging_scores_2025')) {
                    return ['winnerCount' => 3];
                }

                return [];
            },
        );
        $repository = new LandingPageRepository($connection, 'baseline_');

        $archives = $repository->visibleArchives();
        self::assertCount(1, $archives);
        self::assertSame('2025', $archives[0]->suffix);
    }

    public function test_best_of_show_maps_enabled_groups_in_two_set_queries(): void
    {
        $connection = new LandingPageScriptedConnection(
            static function (string $sql): array {
                if (str_contains($sql, 'FROM baseline_style_types')) {
                    return [
                        ['id' => 1, 'styleTypeName' => 'Beer'],
                        ['id' => 4, 'styleTypeName' => 'Mead/Cider'],
                    ];
                }
                if (str_contains($sql, 'FROM baseline_judging_scores_bos')) {
                    return [[
                        'scoreType' => 3,
                        'scorePlace' => '1',
                        'brewerFirstName' => 'Ada',
                        'brewerLastName' => 'Brewer',
                        'brewerClubs' => 'Fixture Club',
                        'brewCoBrewer' => null,
                        'brewName' => 'Winning Mead',
                        'brewStyle' => 'Dry Mead',
                        'brewCategory' => 'M1',
                        'brewSubCategory' => 'A',
                    ]];
                }
                if (str_contains($sql, 'prefsStyleSet')) {
                    return ['prefsStyleSet' => 'BJCP2021'];
                }

                return [];
            },
        );
        $repository = new LandingPageRepository($connection, 'baseline_');

        $summary = $repository->bestOfShow();
        self::assertSame(3, $connection->queryCount);
        self::assertCount(1, $summary->rows);
        self::assertSame('Mead/Cider', $summary->rows[0]->groupName);
        self::assertSame('M1A: Dry Mead', $summary->rows[0]->style);
    }

    public function test_category_winners_use_a_bounded_number_of_set_queries(): void
    {
        $styles = [];
        for ($index = 1; $index <= 40; ++$index) {
            $styles[] = [
                'brewStyleGroup' => str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'brewStyleNum' => 'A',
                'brewStyle' => 'Style ' . $index,
                'brewStyleCategory' => 'Category ' . $index,
            ];
        }

        $connection = new LandingPageScriptedConnection(
            static function (string $sql) use ($styles): array {
                if (str_contains($sql, 'prefsWinnerMethod')) {
                    return ['prefsWinnerMethod' => 1, 'prefsStyleSet' => 'FIXTURE'];
                }
                if (str_contains($sql, 'FROM baseline_styles')) {
                    return $styles;
                }
                if (str_contains($sql, 'FROM baseline_judging_scores score')) {
                    return [];
                }
                if (str_contains($sql, 'FROM baseline_brewing')) {
                    return [];
                }

                return [];
            },
        );
        $repository = new LandingPageRepository($connection, 'baseline_');

        $summary = $repository->winnerSummary();
        self::assertSame(WinnerMethod::Category, $summary->method);
        self::assertLessThanOrEqual(4, $connection->queryCount);
    }
}

final class LandingPageScriptedConnection extends Connection
{
    public int $queryCount = 0;

    /** @param \Closure(string, array<int|string|float|null>): array<mixed> $responder */
    public function __construct(private \Closure $responder)
    {
    }

    public function select(string $sql, array $params = []): array
    {
        ++$this->queryCount;
        $result = ($this->responder)($sql, $params);

        return array_is_list($result) ? $result : [];
    }

    public function selectOne(string $sql, array $params = []): ?array
    {
        ++$this->queryCount;
        $result = ($this->responder)($sql, $params);

        return array_is_list($result) ? null : $result;
    }
}
