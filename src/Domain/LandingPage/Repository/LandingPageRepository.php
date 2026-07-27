<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Repository;

use Bcoem\Database\Connection;
use Bcoem\Domain\LandingPage\Model\Archive;
use Bcoem\Domain\LandingPage\Model\BestOfShowSummary;
use Bcoem\Domain\LandingPage\Model\BestOfShowWinner;
use Bcoem\Domain\LandingPage\Model\CompetitionLimits;
use Bcoem\Domain\LandingPage\Model\CompetitionLocations;
use Bcoem\Domain\LandingPage\Model\CompetitionRules;
use Bcoem\Domain\LandingPage\Model\CompetitionWindows;
use Bcoem\Domain\LandingPage\Model\Contact;
use Bcoem\Domain\LandingPage\Model\ContactMode;
use Bcoem\Domain\LandingPage\Model\ContestOverview;
use Bcoem\Domain\LandingPage\Model\DropoffLocation;
use Bcoem\Domain\LandingPage\Model\JudgingProgress;
use Bcoem\Domain\LandingPage\Model\Sponsor;
use Bcoem\Domain\LandingPage\Model\WinnerMethod;
use Bcoem\Domain\LandingPage\Model\WinnerRow;
use Bcoem\Domain\LandingPage\Model\WinnerSummary;
use Bcoem\Domain\LandingPage\Validation\SafeUrl;

final class LandingPageRepository implements LandingPageReadRepository
{
    private string $tablePrefix;
    private ?WinnerSummary $winnerSummaryCache = null;

    public function __construct(
        private Connection $connection,
        string $tablePrefix,
    ) {
        $this->tablePrefix = $tablePrefix;
        if (!preg_match('/^[A-Za-z0-9_]*$/', $this->tablePrefix)) {
            throw new \InvalidArgumentException('Unsafe table prefix.');
        }
    }

    public function contestOverview(): ?ContestOverview
    {
        $row = $this->connection->selectOne(
            'SELECT contestName, contestHost, contestHostWebsite, contestHostLocation, contestLogo '
            . 'FROM ' . $this->tablePrefix . 'contest_info WHERE id = ?',
            [1],
        );
        if ($row === null) {
            return null;
        }

        return new ContestOverview(
            trim((string) $row['contestName']),
            trim((string) $row['contestHost']),
            $this->optionalUrl($row['contestHostWebsite']),
            $this->nullableString($row['contestHostLocation']),
            $this->uploadedImageUrl($row['contestLogo']),
        );
    }

    public function competitionWindows(): ?CompetitionWindows
    {
        $row = $this->connection->selectOne(
            'SELECT contestRegistrationOpen, contestRegistrationDeadline, '
            . 'contestEntryOpen, contestEntryDeadline, contestJudgeOpen, contestJudgeDeadline, '
            . 'contestDropoffOpen, contestDropoffDeadline, contestShippingOpen, contestShippingDeadline '
            . 'FROM ' . $this->tablePrefix . 'contest_info WHERE id = ?',
            [1],
        );
        if ($row === null) {
            return null;
        }

        return new CompetitionWindows(
            (int) $row['contestRegistrationOpen'],
            (int) $row['contestRegistrationDeadline'],
            (int) $row['contestEntryOpen'],
            (int) $row['contestEntryDeadline'],
            (int) $row['contestJudgeOpen'],
            (int) $row['contestJudgeDeadline'],
            $this->nullableInt($row['contestDropoffOpen']),
            $this->nullableInt($row['contestDropoffDeadline']),
            $this->nullableInt($row['contestShippingOpen']),
            $this->nullableInt($row['contestShippingDeadline']),
        );
    }

    public function competitionLimits(): CompetitionLimits
    {
        $preferences = $this->connection->selectOne(
            'SELECT prefsEntryLimit, prefsEntryLimitPaid '
            . 'FROM ' . $this->tablePrefix . 'preferences WHERE id = ?',
            [1],
        );
        $counts = $this->connection->selectOne(
            'SELECT COUNT(*) AS entryCount, '
            . 'SUM(CASE WHEN brewPaid = ? THEN 1 ELSE 0 END) AS paidEntryCount '
            . 'FROM ' . $this->tablePrefix . 'brewing',
            [1],
        );

        $entryLimit = $this->nullableInt($preferences['prefsEntryLimit'] ?? null);
        $paidEntryLimit = $this->nullableInt($preferences['prefsEntryLimitPaid'] ?? null);

        return new CompetitionLimits(
            (int) ($counts['entryCount'] ?? 0),
            (int) ($counts['paidEntryCount'] ?? 0),
            $entryLimit,
            $paidEntryLimit,
            $entryLimit === null ? 0 : (int) floor($entryLimit * 0.9),
        );
    }

    public function judgingProgress(int $now): JudgingProgress
    {
        $rows = $this->connection->select(
            'SELECT judgingDate, judgingDateEnd FROM '
            . $this->tablePrefix . 'judging_locations WHERE judgingLocType < ?',
            [2],
        );
        $preferences = $this->connection->selectOne(
            'SELECT prefsDisplayWinners, prefsWinnerDelay '
            . 'FROM ' . $this->tablePrefix . 'preferences WHERE id = ?',
            [1],
        );

        $timestamps = [];
        $futureJudgingStarts = 0;
        foreach ($rows as $row) {
            $startsAt = $this->nullableInt($row['judgingDate']);
            $endsAt = $this->nullableInt($row['judgingDateEnd']);
            if ($startsAt !== null) {
                $timestamps[] = $startsAt;
                if ($startsAt >= $now) {
                    ++$futureJudgingStarts;
                }
            }
            if ($endsAt !== null) {
                $timestamps[] = $endsAt;
            }
        }

        $started = $timestamps !== [] && $now > min($timestamps);
        $ended = $timestamps !== [] && $now > max($timestamps);

        return new JudgingProgress(
            $started,
            $ended,
            ($preferences['prefsDisplayWinners'] ?? null) === 'Y',
            (int) ($preferences['prefsWinnerDelay'] ?? 0),
            $rows !== [] && $futureJudgingStarts === 0,
        );
    }

    public function locations(): CompetitionLocations
    {
        $row = $this->connection->selectOne(
            'SELECT contestShippingName, contestShippingAddress, contestAwards, '
            . 'contestAwardsLocName, contestAwardsLocation, contestAwardsLocTime, '
            . 'preferences.prefsShipping '
            . 'FROM ' . $this->tablePrefix . 'contest_info contest '
            . 'INNER JOIN ' . $this->tablePrefix . 'preferences preferences ON preferences.id = ? '
            . 'WHERE contest.id = ?',
            [1, 1],
        );
        $dropoffRows = $this->connection->select(
            'SELECT dropLocationName, dropLocation, dropLocationPhone, '
            . 'dropLocationWebsite, dropLocationNotes '
            . 'FROM ' . $this->tablePrefix . 'drop_off '
            . 'WHERE dropLocationName IS NOT NULL AND dropLocationName <> ? '
            . 'ORDER BY dropLocationName, id',
            [''],
        );
        $dropoffs = array_map(
            fn (array $dropoff): DropoffLocation => new DropoffLocation(
                trim((string) $dropoff['dropLocationName']),
                trim((string) $dropoff['dropLocation']),
                $this->nullableString($dropoff['dropLocationPhone']),
                $this->optionalUrl($dropoff['dropLocationWebsite']),
                $this->nullableString($dropoff['dropLocationNotes']),
            ),
            $dropoffRows,
        );

        return new CompetitionLocations(
            $this->nullableString($row['contestShippingName'] ?? null),
            $this->nullableString($row['contestShippingAddress'] ?? null),
            $this->nullableString($row['contestAwards'] ?? null),
            $this->nullableString($row['contestAwardsLocName'] ?? null),
            $this->nullableString($row['contestAwardsLocation'] ?? null),
            $this->nullableInt($row['contestAwardsLocTime'] ?? null),
            ($row['prefsShipping'] ?? null) === 'Y',
            $dropoffs,
        );
    }

    public function competitionRules(): CompetitionRules
    {
        $row = $this->connection->selectOne(
            'SELECT contestRules, contestBottles FROM '
            . $this->tablePrefix . 'contest_info WHERE id = ?',
            [1],
        );
        $encodedRules = $this->nullableString($row['contestRules'] ?? null);
        $rules = '';
        if ($encodedRules !== null) {
            $decoded = json_decode($encodedRules, true);
            if (is_array($decoded) && is_string($decoded['competition_rules'] ?? null)) {
                $rules = $this->plainText((string) $decoded['competition_rules']);
            } elseif (!is_array($decoded)) {
                $rules = $this->plainText($encodedRules);
            }
        }

        return new CompetitionRules(
            $rules,
            $this->plainTextOrNull($row['contestBottles'] ?? null),
        );
    }

    public function contactMode(): ContactMode
    {
        $row = $this->connection->selectOne(
            'SELECT prefsContact, prefsEmailSMTP, prefsEmailUsername, prefsEmailPassword, '
            . 'prefsEmailHost, prefsEmailPort FROM '
            . $this->tablePrefix . 'preferences WHERE id = ?',
            [1],
        );
        $preference = strtoupper(trim((string) ($row['prefsContact'] ?? 'N')));
        if ($preference === 'X') {
            return ContactMode::Hidden;
        }
        if ($preference !== 'Y') {
            return ContactMode::Directory;
        }

        $smtpEnabled = in_array(
            strtoupper(trim((string) ($row['prefsEmailSMTP'] ?? ''))),
            ['1', 'Y', 'TRUE'],
            true,
        );
        $smtpConfigured = $smtpEnabled
            && $this->nullableString($row['prefsEmailUsername'] ?? null) !== null
            && $this->nullableString($row['prefsEmailPassword'] ?? null) !== null
            && $this->nullableString($row['prefsEmailHost'] ?? null) !== null
            && ($this->nullableInt($row['prefsEmailPort'] ?? null) ?? 0) > 0;

        return $smtpConfigured ? ContactMode::Form : ContactMode::Directory;
    }

    /** @return list<Contact> */
    public function contacts(): array
    {
        $rows = $this->connection->select(
            'SELECT id, contactFirstName, contactLastName, contactPosition '
            . 'FROM ' . $this->tablePrefix . 'contacts ORDER BY id',
        );

        return array_map(
            static fn (array $row): Contact => new Contact(
                (int) $row['id'],
                trim((string) $row['contactFirstName']),
                trim((string) $row['contactLastName']),
                trim((string) $row['contactPosition']),
            ),
            $rows,
        );
    }

    /** @return list<Sponsor> */
    public function sponsors(): array
    {
        $preferences = $this->connection->selectOne(
            'SELECT prefsSponsors, prefsSponsorLogos FROM '
            . $this->tablePrefix . 'preferences WHERE id = ?',
            [1],
        );
        if (($preferences['prefsSponsors'] ?? null) !== 'Y') {
            return [];
        }

        $rows = $this->connection->select(
            'SELECT sponsorName, sponsorURL, sponsorImage, sponsorText, sponsorLocation, sponsorLevel '
            . 'FROM ' . $this->tablePrefix . 'sponsors WHERE sponsorEnable = ? '
            . 'ORDER BY sponsorLevel, sponsorName',
            [1],
        );

        return array_map(
            fn (array $row): Sponsor => new Sponsor(
                trim((string) $row['sponsorName']),
                $this->optionalUrl($row['sponsorURL']),
                ($preferences['prefsSponsorLogos'] ?? null) === 'Y'
                    ? $this->uploadedImageUrl($row['sponsorImage'])
                    : null,
                $this->nullableString($row['sponsorText']),
                $this->nullableString($row['sponsorLocation']),
                (int) $row['sponsorLevel'],
            ),
            $rows,
        );
    }

    /** @return list<Archive> */
    public function visibleArchives(): array
    {
        $rows = $this->connection->select(
            'SELECT archiveSuffix, archiveWinnerMethod, archiveStyleSet '
            . 'FROM ' . $this->tablePrefix . 'archive WHERE archiveDisplayWinners = ? '
            . 'ORDER BY archiveSuffix DESC',
            ['Y'],
        );

        $archives = [];
        foreach ($rows as $row) {
            $suffix = trim((string) $row['archiveSuffix']);
            $styleSet = trim((string) $row['archiveStyleSet']);
            if ($styleSet === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $suffix) !== 1) {
                continue;
            }

            $scoreTable = $this->tablePrefix . 'judging_scores_' . $suffix;
            $exists = $this->connection->selectOne(
                'SELECT COUNT(*) AS tableCount FROM information_schema.tables '
                . 'WHERE table_schema = DATABASE() AND table_name = ?',
                [$scoreTable],
            );
            if ((int) ($exists['tableCount'] ?? 0) < 1) {
                continue;
            }
            $scoreCount = $this->connection->selectOne(
                'SELECT COUNT(*) AS winnerCount FROM ' . $scoreTable
                . ' WHERE scorePlace IN (?, ?, ?, ?, ?)',
                ['1', '2', '3', '4', '5'],
            );
            if ((int) ($scoreCount['winnerCount'] ?? 0) < 1) {
                continue;
            }

            $archives[] = new Archive(
                $suffix,
                (int) $row['archiveWinnerMethod'],
                $styleSet,
            );
        }

        return $archives;
    }

    public function winnerSummary(): WinnerSummary
    {
        if ($this->winnerSummaryCache !== null) {
            return $this->winnerSummaryCache;
        }
        $preferences = $this->connection->selectOne(
            'SELECT prefsWinnerMethod, prefsStyleSet '
            . 'FROM ' . $this->tablePrefix . 'preferences WHERE id = ?',
            [1],
        );
        if ($preferences === null) {
            return $this->winnerSummaryCache = new WinnerSummary(WinnerMethod::Overall, '', []);
        }

        $styleSet = trim((string) $preferences['prefsStyleSet']);
        $method = WinnerMethod::tryFrom((int) $preferences['prefsWinnerMethod']);
        if ($method === null) {
            return $this->winnerSummaryCache = new WinnerSummary(WinnerMethod::Overall, $styleSet, []);
        }

        if ($method === WinnerMethod::Overall) {
            $rows = $this->overallWinnerRows();
        } elseif ($method === WinnerMethod::Category) {
            $rows = $this->categoryWinnerRows($styleSet);
        } else {
            $rows = $this->subcategoryWinnerRows($styleSet);
        }

        return $this->winnerSummaryCache = new WinnerSummary(
            $method,
            $styleSet,
            $this->mapWinnerRows($rows, $styleSet, $method),
        );
    }

    public function bestOfShow(): BestOfShowSummary
    {
        $styleTypes = $this->connection->select(
            'SELECT id, styleTypeName FROM ' . $this->tablePrefix . 'style_types '
            . 'WHERE styleTypeBOS = ? ORDER BY id',
            ['Y'],
        );
        $scoreRows = $this->connection->select(
            'SELECT score.scoreType, score.scorePlace, '
            . 'brewer.brewerFirstName, brewer.brewerLastName, brewer.brewerClubs, '
            . 'entry.brewCoBrewer, entry.brewName, entry.brewStyle, '
            . 'entry.brewCategory, entry.brewSubCategory '
            . 'FROM ' . $this->tablePrefix . 'judging_scores_bos score '
            . 'INNER JOIN ' . $this->tablePrefix . 'brewing entry ON entry.id = score.eid '
            . 'INNER JOIN ' . $this->tablePrefix . 'brewer brewer ON brewer.uid = entry.brewBrewerID '
            . 'WHERE score.scorePlace IS NOT NULL ORDER BY score.scoreType, score.scorePlace',
        );
        $preferences = $this->connection->selectOne(
            'SELECT prefsStyleSet FROM ' . $this->tablePrefix . 'preferences WHERE id = ?',
            [1],
        );
        $styleSet = trim((string) ($preferences['prefsStyleSet'] ?? ''));
        $winners = [];
        foreach ($styleTypes as $styleType) {
            $typeId = (int) $styleType['id'];
            foreach ($scoreRows as $row) {
                $scoreType = (int) $row['scoreType'];
                if ($scoreType !== $typeId
                    && !($typeId === 4 && in_array($scoreType, [2, 3, 4], true))) {
                    continue;
                }
                $place = (int) $row['scorePlace'];
                if ($place < 1) {
                    continue;
                }
                $winners[] = new BestOfShowWinner(
                    trim((string) $styleType['styleTypeName']),
                    $place,
                    trim((string) $row['brewerFirstName'] . ' ' . (string) $row['brewerLastName']),
                    $this->nullableString($row['brewCoBrewer']),
                    trim((string) $row['brewName']),
                    $this->winnerStyle($row, $styleSet, WinnerMethod::Overall),
                    $this->nullableString($row['brewerClubs']),
                );
            }
        }

        return new BestOfShowSummary($winners);
    }

    /** @return array<int, array<string, mixed>> */
    private function overallWinnerRows(): array
    {
        return $this->connection->select(
            'SELECT judgingTable.tableName AS groupName, '
            . '(SELECT COUNT(*) FROM '
            . $this->tablePrefix . 'brewing countedEntry '
            . 'WHERE countedEntry.brewReceived = ? AND EXISTS ('
            . 'SELECT 1 FROM '
            . $this->tablePrefix . 'styles countedStyle '
            . 'WHERE FIND_IN_SET(CAST(countedStyle.id AS CHAR), judgingTable.tableStyles) > ? '
            . 'AND countedStyle.brewStyleGroup = countedEntry.brewCategorySort '
            . 'AND countedStyle.brewStyleNum = countedEntry.brewSubCategory'
            . ')) AS entryCount, '
            . $this->winnerColumns()
            . ' FROM ' . $this->tablePrefix . 'judging_scores score '
            . 'INNER JOIN ' . $this->tablePrefix . 'brewing entry ON entry.id = score.eid '
            . 'INNER JOIN ' . $this->tablePrefix . 'brewer brewer ON brewer.uid = entry.brewBrewerID '
            . 'INNER JOIN ' . $this->tablePrefix . 'judging_tables judgingTable '
            . 'ON judgingTable.id = score.scoreTable '
            . 'WHERE score.scorePlace > ? '
            . 'ORDER BY judgingTable.tableNumber, score.scorePlace',
            [1, 0, 0],
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function categoryWinnerRows(string $styleSet): array
    {
        $groups = [];
        foreach ($this->activeStyles($styleSet) as $style) {
            $group = trim((string) $style['brewStyleGroup']);
            if (!isset($groups[$group])) {
                $groups[$group] = $this->nullableString($style['brewStyleCategory']) ?? $group;
            }
        }

        $entryCounts = [];
        foreach ($this->winnerEntryCountRows() as $entry) {
            if ((int) $entry['brewReceived'] !== 1) {
                continue;
            }
            $group = $styleSet === 'BA'
                ? trim((string) $entry['brewCategory'])
                : trim((string) $entry['brewCategorySort']);
            $entryCounts[$group] = ($entryCounts[$group] ?? 0) + 1;
        }

        $rows = [];
        foreach ($this->groupedWinnerSourceRows() as $row) {
            $group = $styleSet === 'BA'
                ? trim((string) $row['brewCategory'])
                : trim((string) $row['brewCategorySort']);
            if (!isset($groups[$group])) {
                continue;
            }
            $row['groupName'] = $groups[$group];
            $row['styleCategory'] = null;
            $row['entryCount'] = $entryCounts[$group] ?? 0;
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function subcategoryWinnerRows(string $styleSet): array
    {
        $activeStyles = [];
        foreach ($this->activeStyles($styleSet) as $style) {
            $key = (string) $style['brewStyleGroup'] . '^' . (string) $style['brewStyleNum'];
            $activeStyles[$key] ??= $style;
        }

        $entryCounts = [];
        foreach ($this->winnerEntryCountRows() as $entry) {
            if ($styleSet === 'BA' && (int) $entry['brewReceived'] !== 1) {
                continue;
            }
            $group = $styleSet === 'BA'
                ? trim((string) $entry['brewCategory'])
                : trim((string) $entry['brewCategorySort']);
            $key = $group . '^' . trim((string) $entry['brewSubCategory']);
            $entryCounts[$key] = ($entryCounts[$key] ?? 0) + 1;
        }

        $rows = [];
        foreach ($this->groupedWinnerSourceRows() as $row) {
            $group = $styleSet === 'BA'
                ? trim((string) $row['brewCategory'])
                : trim((string) $row['brewCategorySort']);
            $subcategory = trim((string) $row['brewSubCategory']);
            $key = $group . '^' . $subcategory;
            if (!isset($activeStyles[$key])) {
                continue;
            }
            $style = $activeStyles[$key];
            $styleName = trim((string) $style['brewStyle']);
            $row['groupName'] = $this->subcategoryGroupName(
                $group,
                $subcategory,
                $styleName,
                $styleSet,
            );
            $row['styleCategory'] = $this->nullableString($style['brewStyleCategory']);
            $row['entryCount'] = $entryCounts[$key] ?? 0;
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function groupedWinnerSourceRows(): array
    {
        return $this->connection->select(
            'SELECT entry.brewCategorySort, ' . $this->winnerColumns()
            . ' FROM ' . $this->tablePrefix . 'judging_scores score '
            . 'INNER JOIN ' . $this->tablePrefix . 'brewing entry ON entry.id = score.eid '
            . 'INNER JOIN ' . $this->tablePrefix . 'brewer brewer '
            . 'ON brewer.uid = entry.brewBrewerID '
            . 'WHERE score.scorePlace > ? '
            . 'ORDER BY entry.brewCategorySort, entry.brewCategory, '
            . 'entry.brewSubCategory, score.scorePlace',
            [0],
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function winnerEntryCountRows(): array
    {
        return $this->connection->select(
            'SELECT brewCategory, brewCategorySort, brewSubCategory, brewReceived '
            . 'FROM ' . $this->tablePrefix . 'brewing',
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function activeStyles(string $styleSet): array
    {
        return $this->connection->select(
            'SELECT brewStyleGroup, brewStyleNum, brewStyle, brewStyleCategory '
            . 'FROM ' . $this->tablePrefix . 'styles WHERE brewStyleActive = ? AND ('
            . '(? = ? AND ('
            . '(brewStyleVersion = ? AND brewStyleType = ?) OR '
            . '(brewStyleVersion = ? AND brewStyleType <> ?) OR brewStyleOwn = ?'
            . ')) OR '
            . '(? <> ? AND (brewStyleVersion = ? OR brewStyleOwn = ?))'
            . ') ORDER BY brewStyleGroup, brewStyleNum, id',
            [
                'Y',
                $styleSet, 'BJCP2025',
                'BJCP2025', 2,
                'BJCP2021', 2,
                'custom',
                $styleSet, 'BJCP2025',
                $styleSet, 'custom',
            ],
        );
    }

    private function winnerColumns(): string
    {
        return 'score.scorePlace, score.scoreEntry, '
            . 'brewer.brewerFirstName, brewer.brewerLastName, brewer.brewerClubs, '
            . 'entry.brewCoBrewer, entry.brewName, entry.brewStyle, entry.brewCategory, '
            . 'entry.brewSubCategory, entry.brewInfo';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<WinnerRow>
     */
    private function mapWinnerRows(
        array $rows,
        string $styleSet,
        WinnerMethod $method,
    ): array
    {
        return array_map(
            fn (array $row): WinnerRow => new WinnerRow(
                trim((string) $row['groupName']),
                (int) $row['entryCount'],
                (int) $row['scorePlace'],
                trim((string) $row['brewerFirstName'] . ' ' . (string) $row['brewerLastName']),
                $this->nullableString($row['brewCoBrewer']),
                trim((string) $row['brewName']),
                $this->winnerStyle($row, $styleSet, $method),
                $this->nullableString($row['brewInfo']),
                $this->nullableString($row['brewerClubs']),
                $this->nullableFloat($row['scoreEntry']),
            ),
            $rows,
        );
    }

    /** @param array<string, mixed> $row */
    private function winnerStyle(array $row, string $styleSet, WinnerMethod $method): string
    {
        $style = trim((string) $row['brewStyle']);
        if ($styleSet === 'BA') {
            $category = $this->nullableString($row['styleCategory'] ?? null);
            if ($method === WinnerMethod::Subcategory && $category !== null) {
                return $category . ': ' . $style;
            }

            return $style;
        }

        $category = trim((string) $row['brewCategory']);
        $subcategory = trim((string) $row['brewSubCategory']);
        if ($styleSet === 'AABC') {
            $category = ltrim($category, '0');
            $subcategory = ltrim($subcategory, '0');

            return $category . '.' . $subcategory . ': ' . $style;
        }

        return $category . $subcategory . ': ' . $style;
    }

    private function subcategoryGroupName(
        string $group,
        string $subcategory,
        string $styleName,
        string $styleSet,
    ): string {
        if ($styleSet === 'BA') {
            return $styleName;
        }
        if ($styleSet === 'AABC') {
            return ltrim($group, '0') . '.' . ltrim($subcategory, '0') . ': ' . $styleName;
        }

        return $group . $subcategory . ': ' . $styleName;
    }

    private function uploadedImageUrl(mixed $value): ?string
    {
        $filename = $this->nullableString($value);
        if ($filename === null
            || $filename !== basename($filename)
            || $filename === '.'
            || $filename === '..'
            || str_starts_with($filename, '.')
            || str_contains($filename, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $filename) === 1) {
            return null;
        }

        return '/user_images/' . rawurlencode($filename);
    }

    private function optionalUrl(mixed $value): ?string
    {
        $url = $this->nullableString($value);
        try {
            SafeUrl::assert($url);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $url;
    }

    private function plainTextOrNull(mixed $value): ?string
    {
        $text = $this->nullableString($value);

        return $text === null ? null : $this->plainText($text);
    }

    private function plainText(string $value): string
    {
        $withBreaks = preg_replace(
            '/<(?:br\s*\/?|\/p|\/li|\/div|\/h[1-6])>/i',
            "\n",
            $value,
        ) ?? $value;
        $plain = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[ \t]*\n[ \t]*/', "\n", $plain));
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (float) $value;
    }
}
