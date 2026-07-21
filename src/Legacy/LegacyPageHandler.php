<?php

declare(strict_types=1);

namespace Bcoem\Legacy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Bridges GET requests to the existing index.php flow. index.php ends with
 * its own header()/exit()-equivalent (mysqli_close + falls off the end,
 * echoing HTML directly) - this handler does NOT try to capture that into a
 * PSR-7 response body; it lets index.php write directly to the output
 * buffer PHP's SAPI already manages, and returns Slim's response unmodified
 * (Slim's own emitter no-ops on top of output that's already been sent).
 * Anything that MUST run even if index.php calls exit() mid-script
 * (AuditMiddleware's post-processing, once Phase 3 needs it) is registered
 * via register_shutdown_function(), never relied on to run "after" this
 * method returns.
 *
 * $targetFile defaults to the real legacy/index.php (Task 9 relocated the
 * original root index.php there so the new thin front controller could take
 * the root index.php name) for production use; tests substitute a small
 * inert fixture (see LegacyPageHandlerTest) so the handler's own bridging
 * logic is provable without pulling in index.php's full side-effecting
 * bootstrap chain (DB, sessions, dozens of legacy requires) - that
 * end-to-end behavior is proven manually (Step 4) and via Task 10's
 * Playwright e2e suite once a real route exists (Task 9).
 */
final class LegacyPageHandler
{
    public function __construct(private readonly string $targetFile = 'legacy/index.php')
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        foreach ($request->getQueryParams() as $key => $value) {
            $_GET[$key] = $value;
        }
        LegacyBootstrap::requireRootFile($this->targetFile);
        return $response;
    }
}
