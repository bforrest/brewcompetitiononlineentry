<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\ValueObject;

final class ExportMetadata
{
    public function __construct(
        private readonly ExportFormat $format,
        private readonly ExportFilter $filter,
        private readonly ExportView $view,
        private readonly ?string $archiveSuffix = null,
        private readonly \DateTime $generatedAt = new \DateTime(),
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

    public function archiveSuffix(): ?string
    {
        return $this->archiveSuffix;
    }

    public function generatedAt(): \DateTime
    {
        return $this->generatedAt;
    }

    public function filename(): string
    {
        $baseName = sprintf(
            'export_%s_%s',
            $this->filter->value,
            $this->generatedAt->format('Y-m-d_H-i-s')
        );

        return $baseName . '.' . $this->format->fileExtension();
    }
}
