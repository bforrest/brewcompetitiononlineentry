<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\ValueObject;

enum ExportFormat: string
{
    case CSV = 'csv';
    case HTML = 'html';
    case PDF = 'pdf';
    case XML = 'xml';

    public function mimeType(): string
    {
        return match ($this) {
            self::CSV => 'text/csv',
            self::HTML => 'text/html',
            self::PDF => 'application/pdf',
            self::XML => 'application/xml',
        };
    }

    public function fileExtension(): string
    {
        return $this->value;
    }
}
