<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Repository;

use Bcoem\Database\Connection;
use Bcoem\Domain\LandingPage\Model\Archive;
use Bcoem\Domain\LandingPage\Model\CompetitionLimits;
use Bcoem\Domain\LandingPage\Model\CompetitionLocations;
use Bcoem\Domain\LandingPage\Model\CompetitionWindows;
use Bcoem\Domain\LandingPage\Model\Contact;
use Bcoem\Domain\LandingPage\Model\ContestOverview;
use Bcoem\Domain\LandingPage\Model\JudgingProgress;
use Bcoem\Domain\LandingPage\Model\Sponsor;
use Bcoem\Domain\LandingPage\Model\WinnerMethod;
use Bcoem\Domain\LandingPage\Model\WinnerRow;
use Bcoem\Domain\LandingPage\Model\WinnerSummary;

final class LandingPageRepository
{
    private string $tablePrefix;

    public function __construct(
        private Connection $connection,
        ?string $tablePrefix = null,
    ) {
        $this->tablePrefix = $tablePrefix ?? (string) ($GLOBALS['prefix'] ?? 'baseline_');
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
            $this->nullableString($row['contestHostWebsite']),
            $this->nullableString($row['contestHostLocation']),
            $this->nullableString($row['contestLogo']),
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

    public function judgingProgress(): JudgingProgress
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
        foreach ($rows as $row) {
            $startsAt = $this->nullableInt($row['judgingDate']);
            $endsAt = $this->nullableInt($row['judgingDateEnd']);
            if ($startsAt !== null) {
                $timestamps[] = $startsAt;
            }
            if ($endsAt !== null) {
                $timestamps[] = $endsAt;
            }
        }

        $now = time();
        $started = $timestamps !== [] && $now > min($timestamps);
        $ended = $timestamps !== [] && $now > max($timestamps) + 86400;

        return new JudgingProgress(
            $started,
            $ended,
            ($preferences['prefsDisplayWinners'] ?? null) === 'Y',
            (int) ($preferences['prefsWinnerDelay'] ?? 0),
        );
    }

    public function locations(): CompetitionLocations
    {
        $row = $this->connection->selectOne(
            'SELECT contestShippingName, contestShippingAddress, contestAwards, '
            . 'contestAwardsLocName, contestAwardsLocation, contestAwardsLocTime '
            . 'FROM ' . $this->tablePrefix . 'contest_info WHERE id = ?',
            [1],
        );

        return new CompetitionLocations(
            $this->nullableString($row['contestShippingName'] ?? null),
            $this->nullableString($row['contestShippingAddress'] ?? null),
            $this->nullableString($row['contestAwards'] ?? null),
            $this->nullableString($row['contestAwardsLocName'] ?? null),
            $this->nullableString($row['contestAwardsLocation'] ?? null),
            $this->nullableInt($row['contestAwardsLocTime'] ?? null),
        );
    }

    /** @return list<Contact> */
    public function contacts(): array
    {
        $rows = $this->connection->select(
            'SELECT contactFirstName, contactLastName, contactPosition, contactEmail '
            . 'FROM ' . $this->tablePrefix . 'contacts ORDER BY id',
        );

        return array_map(
            static fn (array $row): Contact => new Contact(
                trim((string) $row['contactFirstName']),
                trim((string) $row['contactLastName']),
                trim((string) $row['contactPosition']),
                trim((string) $row['contactEmail']),
            ),
            $rows,
        );
    }

    /** @return list<Sponsor> */
    public function sponsors(): array
    {
        $rows = $this->connection->select(
            'SELECT sponsorName, sponsorURL, sponsorImage, sponsorText, sponsorLocation, sponsorLevel '
            . 'FROM ' . $this->tablePrefix . 'sponsors WHERE sponsorEnable = ? '
            . 'ORDER BY sponsorLevel, sponsorName',
            [1],
        );

        return array_map(
            fn (array $row): Sponsor => new Sponsor(
                trim((string) $row['sponsorName']),
                $this->nullableString($row['sponsorURL']),
                $this->nullableString($row['sponsorImage']),
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

        return array_map(
            static fn (array $row): Archive => new Archive(
                trim((string) $row['archiveSuffix']),
                (int) $row['archiveWinnerMethod'],
                trim((string) $row['archiveStyleSet']),
            ),
            $rows,
        );
    }

    public function winnerSummary(): WinnerSummary
    {
        $preferences = $this->connection->selectOne(
            'SELECT prefsWinnerMethod, prefsStyleSet '
            . 'FROM ' . $this->tablePrefix . 'preferences WHERE id = ?',
            [1],
        );
        if ($preferences === null) {
            return new WinnerSummary(WinnerMethod::Overall, '', []);
        }

        $styleSet = trim((string) $preferences['prefsStyleSet']);
        $method = WinnerMethod::tryFrom((int) $preferences['prefsWinnerMethod']);
        if ($method === null) {
            return new WinnerSummary(WinnerMethod::Overall, $styleSet, []);
        }

        if ($method === WinnerMethod::Overall) {
            $rows = $this->overallWinnerRows();
        } elseif ($method === WinnerMethod::Category) {
            $rows = $this->categoryWinnerRows($styleSet);
        } else {
            $rows = $this->subcategoryWinnerRows($styleSet);
        }

        return new WinnerSummary($method, $styleSet, $this->mapWinnerRows($rows, $styleSet, $method));
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

        $rows = [];
        foreach ($groups as $group => $groupName) {
            $groupRows = $this->connection->select(
                'SELECT ? AS groupName, NULL AS styleCategory, '
                . '(SELECT COUNT(*) FROM '
                . $this->tablePrefix . 'brewing countedEntry '
                . 'WHERE (CASE WHEN ? = ? THEN countedEntry.brewCategory '
                . 'ELSE countedEntry.brewCategorySort END) = ? '
                . 'AND countedEntry.brewReceived = ?) AS entryCount, '
                . $this->winnerColumns()
                . ' FROM ' . $this->tablePrefix . 'judging_scores score '
                . 'INNER JOIN ' . $this->tablePrefix . 'brewing entry ON entry.id = score.eid '
                . 'INNER JOIN ' . $this->tablePrefix . 'brewer brewer '
                . 'ON brewer.uid = entry.brewBrewerID '
                . 'WHERE (CASE WHEN ? = ? THEN entry.brewCategory '
                . 'ELSE entry.brewCategorySort END) = ? '
                . 'AND score.scorePlace > ? ORDER BY score.scorePlace',
                [$groupName, $styleSet, 'BA', $group, 1, $styleSet, 'BA', $group, 0],
            );
            array_push($rows, ...$groupRows);
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

        $rows = [];
        foreach ($activeStyles as $style) {
            $group = trim((string) $style['brewStyleGroup']);
            $subcategory = trim((string) $style['brewStyleNum']);
            $styleName = trim((string) $style['brewStyle']);
            $groupName = $this->subcategoryGroupName($group, $subcategory, $styleName, $styleSet);
            $styleCategory = $this->nullableString($style['brewStyleCategory']);

            $styleRows = $this->connection->select(
                'SELECT ? AS groupName, ? AS styleCategory, '
                . '(SELECT COUNT(*) FROM '
                . $this->tablePrefix . 'brewing countedEntry '
                . 'WHERE (CASE WHEN ? = ? THEN countedEntry.brewCategory '
                . 'ELSE countedEntry.brewCategorySort END) = ? '
                . 'AND countedEntry.brewSubCategory = ? '
                . 'AND (? <> ? OR countedEntry.brewReceived = ?)) AS entryCount, '
                . $this->winnerColumns()
                . ' FROM ' . $this->tablePrefix . 'judging_scores score '
                . 'INNER JOIN ' . $this->tablePrefix . 'brewing entry ON entry.id = score.eid '
                . 'INNER JOIN ' . $this->tablePrefix . 'brewer brewer '
                . 'ON brewer.uid = entry.brewBrewerID '
                . 'WHERE (CASE WHEN ? = ? THEN entry.brewCategory '
                . 'ELSE entry.brewCategorySort END) = ? '
                . 'AND entry.brewSubCategory = ? AND score.scorePlace > ? '
                . 'ORDER BY score.scorePlace',
                [
                    $groupName,
                    $styleCategory,
                    $styleSet, 'BA', $group,
                    $subcategory,
                    $styleSet, 'BA', 1,
                    $styleSet, 'BA', $group,
                    $subcategory,
                    0,
                ],
            );
            array_push($rows, ...$styleRows);
        }

        return $rows;
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
