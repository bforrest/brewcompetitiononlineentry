<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\ValueObject;

final class ReportData
{
    /**
     * @param array<int, array<string, mixed>> $rows Query result rows
     */
    public function __construct(
        private readonly ExportFormat $format,
        private readonly ExportFilter $filter,
        private readonly ExportView $view,
        private readonly array $rows,
        private readonly array $columnHeaders,
        private readonly \DateTime $generatedAt
    ) {
    }

    public function format(): ExportFormat
    {
        return $this->format;
    }

    public function filter(): ExportFilter
    {
        return $this->filter;
    }

    public function view(): ExportView
    {
        return $this->view;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function columnHeaders(): array
    {
        return $this->columnHeaders;
    }

    public function generatedAt(): \DateTime
    {
        return $this->generatedAt;
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }
}
