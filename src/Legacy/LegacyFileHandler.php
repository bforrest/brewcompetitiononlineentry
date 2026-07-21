<?php

declare(strict_types=1);

namespace Bcoem\Legacy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Generalizes LegacyPageHandler/LegacyProcessHandler's bridging pattern to
 * any root-relative side-door file (qr.php, handle.php, every ajax/*.php,
 * etc.). One instance per registered route (see src/Kernel/app.php), each
 * bound to a single $relativePath derived from config/access_policy.php's
 * file:* keys.
 *
 * chdir()s to the target's own directory (not ROOT) because several of
 * these files - like includes/process.inc.php - do relative requires of
 * their own (e.g. require('../paths.php')) that only resolve when run from
 * within their own directory.
 *
 * $relativePath is constructor-injected so tests can substitute a small
 * inert fixture (see LegacyFileHandlerTest, reusing Task 7's
 * tests/fixtures/legacy_process_fixture.php) rather than pulling in the
 * real, side-effecting production files (sessions, DB, in several cases
 * their own exit()) - same rationale as LegacyPageHandler/LegacyProcessHandler.
 */
final class LegacyFileHandler
{
    public function __construct(private readonly string $relativePath)
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
        chdir(ROOT . dirname($this->relativePath));
        require ROOT . $this->relativePath;
        return $response;
    }
}
