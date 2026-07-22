<?php

declare(strict_types=1);

namespace Bcoem\Kernel\Controller;

use Bcoem\Domain\Registration\Command\RegisterEntrantCommand;
use Bcoem\Domain\Registration\Exception\RegistrationException;
use Bcoem\Domain\Registration\Service\RegistrationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RegistrationController
{
    public function __construct(private RegistrationService $registrationService)
    {
    }

    public function getForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        ob_start();
        require dirname(__DIR__, 3) . '/templates/Registration/register-form.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response->withStatus(200)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function postRegister(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];

        try {
            $cmd = new RegisterEntrantCommand((array) $data);

            // Computed fresh per-request from contest_info (+ the
            // any-judging-session-started override), NOT read from
            // $_SESSION['registration_open']/['judge_window_open'] - those
            // keys don't exist anywhere in this codebase, so reading them
            // would always silently default to "open" and the
            // RegistrationClosedException path below could never fire.
            $registrationOpen = $this->registrationService->isRegistrationOpen();
            $judgeWindowOpen = $this->registrationService->isJudgeWindowOpen();
            $clubAllowlist = $_SESSION['club_array'] ?? [];
            $remoteAddr = $request->getServerParams()['REMOTE_ADDR'] ?? '';

            $this->registrationService->register($cmd, $registrationOpen, $judgeWindowOpen, $clubAllowlist, $remoteAddr);

            unset($_SESSION['user_info']);
            $_SESSION['loginUsername'] = $cmd->userName;
            csrf_token_generate(true);

            return $response->withStatus(302)->withHeader('Location', '/entries/my');
        } catch (RegistrationException $e) {
            $response->getBody()->write((string) json_encode(['error' => $e->getMessage()]));
            return $response->withStatus($e->getHttpStatus())->withHeader('Content-Type', 'application/json');
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write((string) json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }
    }
}
