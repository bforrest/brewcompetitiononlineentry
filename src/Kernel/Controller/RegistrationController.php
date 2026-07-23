<?php

declare(strict_types=1);

namespace Bcoem\Kernel\Controller;

use Bcoem\Domain\Registration\Command\RegisterEntrantCommand;
use Bcoem\Domain\Registration\Exception\RegistrationException;
use Bcoem\Domain\Registration\Exception\DuplicateEmailException;
use Bcoem\Domain\Registration\Form\RegistrationFormFactory;
use Bcoem\Domain\Registration\Repository\RegistrationOptionsRepository;
use Bcoem\Domain\Registration\Service\RegistrationService;
use Bcoem\Kernel\View\LayoutRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RegistrationController
{
    public function __construct(
        private RegistrationService $registrationService,
        private RegistrationOptionsRepository $optionsRepository,
        private RegistrationFormFactory $formFactory,
        private LayoutRenderer $layout,
    ) {
    }

    public function getForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->renderForm($response, [], 200, validate: false);
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
            $fieldErrors = $e instanceof DuplicateEmailException
                ? ['user_name' => 'That email address is already registered.']
                : [];

            return $this->renderForm($response, (array) $data, $e->getHttpStatus(), $fieldErrors, [$e->getMessage()]);
        } catch (\InvalidArgumentException $e) {
            return $this->renderForm($response, (array) $data, 422, [], [$e->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $fieldErrors
     * @param list<string> $generalErrors
     */
    private function renderForm(
        ResponseInterface $response,
        array $input,
        int $status,
        array $fieldErrors = [],
        array $generalErrors = [],
        bool $validate = true,
    ): ResponseInterface {
        $options = $this->optionsRepository->options();
        $form = $this->formFactory->fromRequest($input, $options, $fieldErrors, $generalErrors, $validate);
        $html = $this->layout->public(
            'Register',
            $options->title,
            dirname(__DIR__, 3) . '/templates/Registration/register-form.php',
            ['form' => $form, 'options' => $options],
        );

        $response->getBody()->write($html);
        return $response->withStatus($status)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
