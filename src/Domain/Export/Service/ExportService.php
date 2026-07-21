<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\Service;

use Bcoem\Domain\Export\Command\GenerateExportCommand;
use Bcoem\Domain\Export\Exception\InvalidExportFilterException;
use Bcoem\Domain\Export\Repository\BrewingExportRepository;
use Bcoem\Domain\Export\Repository\ParticipantExportRepository;
use Bcoem\Domain\Export\Repository\JudgingExportRepository;
use Bcoem\Domain\Export\ValueObject\ExportFilter;
use Bcoem\Domain\Export\ValueObject\ExportFormat;
use Bcoem\Domain\Export\ValueObject\ExportView;
use Bcoem\Domain\Export\ValueObject\ReportData;
use Bcoem\Security\Identity;
use DateTime;

final class ExportService
{
    public function __construct(
        private readonly BrewingExportRepository $brewingRepository,
        private readonly ParticipantExportRepository $participantRepository,
        private readonly JudgingExportRepository $judgingRepository,
        private readonly ExportValidationService $validation,
    ) {
    }

    /**
     * Generate export report with validated command.
     *
     * @throws InvalidExportFilterException if command validation fails
     * @throws \Bcoem\Domain\Export\Exception\AccessDeniedException if user lacks permissions
     */
    public function execute(GenerateExportCommand $command, Identity $user): ReportData
    {
        $this->validation->validateCommand($command);

        $format = ExportFormat::from($command->format);
        $filter = ExportFilter::from($command->filter);
        $view = ExportView::from($command->view);

        if ($filter->requiresAdminAccess() && !$user->isAdmin()) {
            throw new \Bcoem\Domain\Export\Exception\AccessDeniedException(
                'Admin access required for this export'
            );
        }

        $rows = match ($filter) {
            ExportFilter::PAID, ExportFilter::NOPAY, ExportFilter::REQUIRED =>
                $this->brewingRepository->getEntriesByFilter(
                    $filter,
                    $view->value,
                    $user->competitionId(),
                    $command->archiveSuffix
                ),
            ExportFilter::WINNERS, ExportFilter::CIRCUIT =>
                $this->brewingRepository->getWinnerData(
                    $command->archiveSuffix,
                    $user->competitionId()
                ),
            ExportFilter::ALL =>
                $this->brewingRepository->getAllEntries(
                    $user->competitionId(),
                    $command->archiveSuffix
                ),
            ExportFilter::JUDGES, ExportFilter::STEWARDS, ExportFilter::STAFF,
            ExportFilter::AVAIL_JUDGES, ExportFilter::AVAIL_STEWARDS =>
                $this->participantRepository->getParticipantsByFilter(
                    $filter,
                    $user->competitionId()
                ),
            ExportFilter::JUDGING_SCORES, ExportFilter::JUDGING_SCORES_BOS =>
                $this->judgingRepository->getJudgingScores(
                    $filter === ExportFilter::JUDGING_SCORES_BOS,
                    $user->competitionId(),
                    $command->archiveSuffix
                ),
        };

        $columnHeaders = $this->extractColumnHeaders($rows);

        return new ReportData(
            $format,
            $filter,
            $view,
            $rows,
            $columnHeaders,
            new DateTime()
        );
    }

    /**
     * Extract column headers from first row.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function extractColumnHeaders(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        return array_keys($rows[0]);
    }
}
