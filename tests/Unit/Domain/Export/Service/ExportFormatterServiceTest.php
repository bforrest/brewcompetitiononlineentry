<?php

declare(strict_types=1);

namespace Bcoem\Tests\Unit\Domain\Export\Service;

use Bcoem\Domain\Export\Service\ExportFormatterService;
use Bcoem\Domain\Export\ValueObject\ExportFilter;
use Bcoem\Domain\Export\ValueObject\ExportFormat;
use Bcoem\Domain\Export\ValueObject\ExportView;
use Bcoem\Domain\Export\ValueObject\ReportData;
use PHPUnit\Framework\TestCase;

class ExportFormatterServiceTest extends TestCase
{
    private ExportFormatterService $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ExportFormatterService();
    }

    public function testFormatCsvProperlyEscapesQuotes(): void
    {
        $rows = [
            ['name' => 'Test Brewery', 'style' => 'IPA'],
            ['name' => 'O\'Reilly\'s Pub', 'style' => 'Stout, Bold'],
        ];

        $report = new ReportData(
            ExportFormat::CSV,
            ExportFilter::ALL,
            ExportView::DEFAULT,
            $rows,
            ['name', 'style'],
            new \DateTime()
        );

        $csv = $this->formatter->format($report);

        $this->assertStringContainsString('Test Brewery', $csv);
        $this->assertStringContainsString("O'Reilly's Pub", $csv);
        $this->assertStringContainsString('"Stout, Bold"', $csv);
    }

    public function testFormatHtmlProperlyEscapesSpecialChars(): void
    {
        $rows = [
            ['name' => '<script>alert("xss")</script>', 'style' => 'IPA'],
        ];

        $report = new ReportData(
            ExportFormat::HTML,
            ExportFilter::ALL,
            ExportView::DEFAULT,
            $rows,
            ['name', 'style'],
            new \DateTime()
        );

        $html = $this->formatter->format($report);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<thead>', $html);
    }

    public function testFormatXmlProperlyEscapesAndWrapsData(): void
    {
        $rows = [
            ['id' => '1', 'name' => 'Test & Co.'],
        ];

        $report = new ReportData(
            ExportFormat::XML,
            ExportFilter::ALL,
            ExportView::DEFAULT,
            $rows,
            ['id', 'name'],
            new \DateTime()
        );

        $xml = $this->formatter->format($report);

        $this->assertStringContainsString('<?xml version="1.0"', $xml);
        $this->assertStringContainsString('<rows>', $xml);
        $this->assertStringContainsString('<row>', $xml);
        $this->assertStringContainsString('&amp;', $xml);
        $this->assertStringNotContainsString('& Co.', $xml);
    }

    public function testFormatPdfFallsBackToCsv(): void
    {
        $rows = [
            ['id' => '1', 'name' => 'Test'],
        ];

        $report = new ReportData(
            ExportFormat::PDF,
            ExportFilter::ALL,
            ExportView::DEFAULT,
            $rows,
            ['id', 'name'],
            new \DateTime()
        );

        $pdf = $this->formatter->format($report);

        $this->assertStringContainsString('PDF generation requires', $pdf);
        $this->assertStringContainsString('CSV', $pdf);
    }

    public function testFormatCsvHandlesEmptyRows(): void
    {
        $report = new ReportData(
            ExportFormat::CSV,
            ExportFilter::ALL,
            ExportView::DEFAULT,
            [],
            ['id', 'name'],
            new \DateTime()
        );

        $csv = $this->formatter->format($report);

        $this->assertStringContainsString('id,name', $csv);
        $this->assertSame("id,name", trim($csv));
    }
}
