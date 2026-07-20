<?php

declare(strict_types=1);

namespace Bcoem\Legacy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Bridges POSTs to includes/process.inc.php. That file does
 * require('../paths.php') - a path relative to includes/ - so it must
 * actually run from within includes/ for its own relative require to
 * resolve; chdir()s to the target file's own directory rather than ROOT.
 *
 * $targetFile defaults to the real process.inc.php for production use;
 * tests substitute a small inert fixture (see LegacyProcessHandlerTest) -
 * same rationale as LegacyPageHandler's $targetFile parameter.
 */
final class LegacyProcessHandler
{
    public function __construct(private readonly string $targetFile = 'includes/process.inc.php')
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        foreach ($request->getQueryParams() as $key => $value) {
            $_GET[$key] = $value;
        }
        foreach ((array)$request->getParsedBody() as $key => $value) {
            $_POST[$key] = $value;
        }
        chdir(ROOT . dirname($this->targetFile));
        require ROOT . $this->targetFile;
        return $response;
    }
}
