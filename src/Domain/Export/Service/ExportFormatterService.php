<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\Service;

use Bcoem\Domain\Export\ValueObject\ExportFormat;
use Bcoem\Domain\Export\ValueObject\ReportData;

class ExportFormatterService
{
    /**
     * Format report data for output.
     *
     * @return string Formatted data ready for output/download
     */
    public function format(ReportData $report): string
    {
        return match ($report->format()) {
            ExportFormat::CSV => $this->formatCsv($report),
            ExportFormat::HTML => $this->formatHtml($report),
            ExportFormat::XML => $this->formatXml($report),
            ExportFormat::PDF => $this->formatPdf($report),
        };
    }

    private function formatCsv(ReportData $report): string
    {
        $output = [];

        $headers = $report->columnHeaders();
        $output[] = $this->escapeCsvLine($headers);

        foreach ($report->rows() as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }
            $output[] = $this->escapeCsvLine($line);
        }

        return implode("\r\n", $output);
    }

    private function formatHtml(ReportData $report): string
    {
        $html = '<table border="1" cellpadding="5" cellspacing="0">' . "\n";
        $html .= '<thead><tr>';

        foreach ($report->columnHeaders() as $header) {
            $html .= '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>';
        }

        $html .= '</tr></thead>' . "\n";
        $html .= '<tbody>' . "\n";

        foreach ($report->rows() as $row) {
            $html .= '<tr>';
            foreach ($report->columnHeaders() as $header) {
                $value = $row[$header] ?? '';
                $html .= '<td>' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html .= '</tr>' . "\n";
        }

        $html .= '</tbody>' . "\n";
        $html .= '</table>';

        return $html;
    }

    private function formatXml(ReportData $report): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<export>' . "\n";
        $xml .= '<metadata>' . "\n";
        $xml .= '<filter>' . htmlspecialchars($report->filter()->value, ENT_QUOTES, 'UTF-8') . '</filter>' . "\n";
        $xml .= '<format>' . htmlspecialchars($report->format()->value, ENT_QUOTES, 'UTF-8') . '</format>' . "\n";
        $xml .= '<generated_at>' . $report->generatedAt()->format('c') . '</generated_at>' . "\n";
        $xml .= '<row_count>' . $report->rowCount() . '</row_count>' . "\n";
        $xml .= '</metadata>' . "\n";
        $xml .= '<rows>' . "\n";

        foreach ($report->rows() as $row) {
            $xml .= '<row>' . "\n";
            foreach ($row as $key => $value) {
                $xml .= '<' . $this->sanitizeXmlElementName($key) . '>';
                $xml .= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
                $xml .= '</' . $this->sanitizeXmlElementName($key) . '>' . "\n";
            }
            $xml .= '</row>' . "\n";
        }

        $xml .= '</rows>' . "\n";
        $xml .= '</export>';

        return $xml;
    }

    private function formatPdf(ReportData $report): string
    {
        // PDF generation would typically use a library like FPDF or mPDF.
        // For now, return CSV content wrapped in a note that PDF generation is deferred to Phase 3.5.
        return "PDF generation requires external library (FPDF/mPDF).\nFalling back to CSV:\n\n" . $this->formatCsv($report);
    }

    /**
     * Escape CSV line values.
     *
     * @param array<int, string> $values
     */
    private function escapeCsvLine(array $values): string
    {
        $escaped = [];
        foreach ($values as $value) {
            $value = (string) $value;
            if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
                $value = '"' . str_replace('"', '""', $value) . '"';
            }
            $escaped[] = $value;
        }

        return implode(',', $escaped);
    }

    /**
     * Sanitize element name for XML.
     */
    private function sanitizeXmlElementName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name) ?: 'field';
    }
}
