<?php
declare(strict_types=1);

namespace Bcoem\Kernel\Controller;

use Bcoem\Domain\Export\Command\GenerateExportCommand;
use Bcoem\Domain\Export\Exception\ExportException;
use Bcoem\Domain\Export\Service\ExportService;
use Bcoem\Domain\Export\Service\ExportFormatterService;
use Bcoem\Security\Identity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
    public function getExportForm(ServerRequestInterface $request, ResponseInterface $response, Identity $user): ResponseInterface
    {
        if ($user->userLevel() > 1) {
            $response->getBody()->write('Unauthorized');
            return $response->withStatus(403);
        }

        $html = $this->renderExportForm();
        $response->getBody()->write($html);
        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Generate and stream export file.
     */
    public function postExport(ServerRequestInterface $request, ResponseInterface $response, Identity $user): ResponseInterface
    {
        if ($user->userLevel() > 1) {
            $response->getBody()->write('Unauthorized');
            return $response->withStatus(403);
        }

        try {
            $parsedBody = (array) ($request->getParsedBody() ?? []);
            $format = $parsedBody['format'] ?? 'csv';
            $filter = $parsedBody['filter'] ?? 'all';
            $view = $parsedBody['view'] ?? 'default';
            $archiveSuffix = $parsedBody['archive_suffix'] ?? null;
            if ($archiveSuffix === '') {
                $archiveSuffix = null;
            }

            $command = new GenerateExportCommand($format, $filter, $view, $archiveSuffix);
            $report = $this->exportService->execute($command, $user);
            $content = $this->formatterService->format($report);

            $response->getBody()->write($content);
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', $report->format()->mimeType())
                ->withHeader(
                    'Content-Disposition',
                    'attachment; filename="export.' . $report->format()->fileExtension() . '"'
                );
        } catch (ExportException $e) {
            $response->getBody()->write(sprintf('Error: %s', $e->getMessage()));
            return $response
                ->withStatus($e->getHttpStatus())
                ->withHeader('Content-Type', 'text/plain');
        } catch (\Exception $e) {
            $response->getBody()->write('Internal error');
            return $response->withStatus(500);
        }
    }

    /**
     * Preview export without download.
     */
    public function getExportPreview(ServerRequestInterface $request, ResponseInterface $response, Identity $user): ResponseInterface
    {
        if ($user->userLevel() > 1) {
            $response->getBody()->write('Unauthorized');
            return $response->withStatus(403);
        }

        try {
            $queryParams = $request->getQueryParams();
            $format = $queryParams['format'] ?? 'html';
            $filter = $queryParams['filter'] ?? 'all';
            $view = $queryParams['view'] ?? 'default';

            $command = new GenerateExportCommand($format, $filter, $view);
            $report = $this->exportService->execute($command, $user);
            $content = $this->formatterService->format($report);

            $response->getBody()->write($content);
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', $report->format()->mimeType());
        } catch (ExportException $e) {
            $response->getBody()->write(sprintf('Error: %s', $e->getMessage()));
            return $response
                ->withStatus($e->getHttpStatus())
                ->withHeader('Content-Type', 'text/plain');
        } catch (\Exception $e) {
            $response->getBody()->write('Internal error');
            return $response->withStatus(500);
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
