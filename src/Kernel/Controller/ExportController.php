<?php
declare(strict_types=1);

namespace Bcoem\Kernel\Controller;

use Bcoem\Domain\Export\Command\GenerateExportCommand;
use Bcoem\Domain\Export\Exception\ExportException;
use Bcoem\Domain\Export\Service\ExportService;
use Bcoem\Domain\Export\Service\ExportFormatterService;
use Bcoem\Security\Identity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportController
{
    public function __construct(
        private readonly ExportService $exportService,
        private readonly ExportFormatterService $formatterService,
    ) {
    }

    /**
     * Render export form.
     */
    public function getExportForm(Request $request, Identity $user): Response
    {
        if ($user->userLevel() > 1) {
            return new Response('Unauthorized', 403);
        }

        $html = $this->renderExportForm();

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Generate and stream export file.
     */
    public function postExport(Request $request, Identity $user): Response
    {
        if ($user->userLevel() > 1) {
            return new Response('Unauthorized', 403);
        }

        try {
            $format = $request->request->getString('format', 'csv');
            $filter = $request->request->getString('filter', 'all');
            $view = $request->request->getString('view', 'default');
            $archiveSuffix = $request->request->getString('archive_suffix') ?: null;

            $command = new GenerateExportCommand($format, $filter, $view, $archiveSuffix);
            $report = $this->exportService->execute($command, $user);
            $content = $this->formatterService->format($report);

            $response = new StreamedResponse();
            $response->setCallback(function () use ($content) {
                echo $content;
            });

            $response->headers->set('Content-Type', $report->format()->mimeType());
            $response->headers->set(
                'Content-Disposition',
                'attachment; filename="' . $report->format()->fileExtension() . '"'
            );

            return $response;
        } catch (ExportException $e) {
            return new Response(
                sprintf('Error: %s', $e->getMessage()),
                $e->getHttpStatus(),
                ['Content-Type' => 'text/plain']
            );
        } catch (\Exception $e) {
            return new Response('Internal error', 500);
        }
    }

    /**
     * Preview export without download.
     */
    public function getExportPreview(Request $request, Identity $user): Response
    {
        if ($user->userLevel() > 1) {
            return new Response('Unauthorized', 403);
        }

        try {
            $format = $request->query->getString('format', 'html');
            $filter = $request->query->getString('filter', 'all');
            $view = $request->query->getString('view', 'default');

            $command = new GenerateExportCommand($format, $filter, $view);
            $report = $this->exportService->execute($command, $user);
            $content = $this->formatterService->format($report);

            return new Response($content, 200, ['Content-Type' => $report->format()->mimeType()]);
        } catch (ExportException $e) {
            return new Response(
                sprintf('Error: %s', $e->getMessage()),
                $e->getHttpStatus(),
                ['Content-Type' => 'text/plain']
            );
        }
    }

    private function renderExportForm(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Export Data</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        select { padding: 5px; }
        button { padding: 10px 20px; background-color: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <h1>Export Data</h1>
    <form method="post" action="/export">
        <div class="form-group">
            <label for="format">Export Format:</label>
            <select name="format" id="format" required>
                <option value="csv">CSV</option>
                <option value="html">HTML</option>
                <option value="xml">XML</option>
                <option value="pdf">PDF</option>
            </select>
        </div>
        <div class="form-group">
            <label for="filter">Data Filter:</label>
            <select name="filter" id="filter" required>
                <option value="all">All Entries</option>
                <option value="paid">Paid Entries</option>
                <option value="nopay">Unpaid Entries</option>
                <option value="required">Entries Requiring Info</option>
                <option value="winners">Winners</option>
                <option value="circuit">Circuit Winners</option>
                <option value="judges">Judges</option>
                <option value="stewards">Stewards</option>
                <option value="staff">Staff</option>
            </select>
        </div>
        <div class="form-group">
            <label for="view">View:</label>
            <select name="view" id="view">
                <option value="default">Default</option>
                <option value="all">All</option>
                <option value="not_received">Not Received</option>
            </select>
        </div>
        <button type="submit">Download Export</button>
    </form>
</body>
</html>
HTML;
    }
}
