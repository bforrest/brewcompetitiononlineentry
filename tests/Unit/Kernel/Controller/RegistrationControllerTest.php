<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Kernel\Controller;

use PHPUnit\Framework\TestCase;
use Bcoem\Kernel\Controller\RegistrationController;
use Bcoem\Domain\Registration\Repository\RegistrationRepository;
use Bcoem\Domain\Registration\Service\CaptchaVerifier;
use Bcoem\Domain\Registration\Service\RegistrationService;
use Bcoem\Domain\Registration\ValueObject\RegistrantId;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * RegistrationService is `final` (Task 7), so PHPUnit's mock generator can't
 * double it directly (ClassIsFinalException - confirmed empirically while
 * writing this test, no bypass-finals-style package is installed in this
 * repo). Instead this constructs a REAL RegistrationService wired with
 * mocked RegistrationRepository/CaptchaVerifier collaborators, exactly like
 * RegistrationServiceTest.php's own setUp() does - this exercises the
 * controller against real service logic (window-open reads, exception
 * mapping) while still keeping the test DB-free.
 */
class RegistrationControllerTest extends TestCase
{
    private RegistrationRepository $repository;
    private CaptchaVerifier $captcha;
    private RegistrationController $controller;

    protected function setUp(): void
    {
        // Defensive reset: $_SESSION is a real superglobal shared by the whole
        // PHPUnit process. AuthenticationMiddlewareTest::setUp() overwrites it
        // wholesale (including userLevel) with no tearDown() to restore it, so
        // depending on suite execution order this test can inherit a stale
        // userLevel from an earlier test and fail
        // assertArrayNotHasKey('userLevel', $_SESSION) below for a reason that
        // has nothing to do with this controller's own (correct) behavior.
        unset($_SESSION['userLevel']);

        $this->repository = $this->createMock(RegistrationRepository::class);
        $this->captcha = $this->createMock(CaptchaVerifier::class);

        // Wide-open registration/judge windows and no judging session
        // started, so postRegister()'s isRegistrationOpen()/isJudgeWindowOpen()
        // reads (Task 9's session-key fix) don't themselves block these tests -
        // each test below is about what happens AFTER that gate passes.
        $this->repository->method('contestDates')->willReturn([
            'contestRegistrationOpen' => time() - 3600,
            'contestRegistrationDeadline' => time() + 3600,
            'contestJudgeOpen' => time() - 3600,
            'contestJudgeDeadline' => time() + 3600,
        ]);
        $this->repository->method('anyJudgingSessionStarted')->willReturn(false);

        $service = new RegistrationService($this->repository, $this->captcha);
        $this->controller = new RegistrationController($service);
    }

    private function formPostBody(): array
    {
        return [
            'user_name' => 'entrant@example.com',
            'password' => 'Sup3rSecret!',
            'userQuestion' => 'Favorite hop?',
            'userQuestionAnswer' => 'Citra',
            'brewerFirstName' => 'Jane',
            'brewerLastName' => 'Brewer',
            'brewerAddress' => '1 Test Street',
            'brewerCity' => 'Testville',
            'brewerStateUS' => 'TX',
            'brewerZip' => '75001',
            'brewerCountry' => 'United States',
            'brewerPhone1' => '555-555-0100',
        ];
    }

    public function test_post_register_success_sets_session_and_redirects(): void
    {
        $this->repository->method('emailExists')->willReturn(false);
        $this->captcha->method('verify')->willReturn(true);
        $this->repository->method('staffRowExists')->willReturn(false);
        $this->repository->method('insertUser')->willReturn(RegistrantId::from(1));

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/register')
            ->withParsedBody($this->formPostBody());
        $response = $this->controller->postRegister($request, (new ResponseFactory())->createResponse());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/entries/my', $response->getHeaderLine('Location'));
        $this->assertSame('entrant@example.com', $_SESSION['loginUsername']);
        $this->assertArrayNotHasKey('userLevel', $_SESSION);
    }

    public function test_post_register_duplicate_email_returns_409(): void
    {
        $this->repository->method('emailExists')->willReturn(true);

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/register')
            ->withParsedBody($this->formPostBody());
        $response = $this->controller->postRegister($request, (new ResponseFactory())->createResponse());

        $this->assertSame(409, $response->getStatusCode());
    }

    public function test_post_register_closed_registration_returns_409(): void
    {
        // Overrides setUp()'s wide-open window with a fully-closed one to
        // exercise the RegistrationClosedException path end to end through
        // the controller - this is exactly the path Task 9's session-key
        // fix was about: without isRegistrationOpen()/isJudgeWindowOpen()
        // reading real contest_info state, this could never fire.
        $repository = $this->createMock(RegistrationRepository::class);
        $repository->method('contestDates')->willReturn([
            'contestRegistrationOpen' => time() - 7200,
            'contestRegistrationDeadline' => time() - 3600,
            'contestJudgeOpen' => time() - 7200,
            'contestJudgeDeadline' => time() - 3600,
        ]);
        $repository->method('anyJudgingSessionStarted')->willReturn(false);
        $service = new RegistrationService($repository, $this->captcha);
        $controller = new RegistrationController($service);

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/register')
            ->withParsedBody($this->formPostBody());
        $response = $controller->postRegister($request, (new ResponseFactory())->createResponse());

        $this->assertSame(409, $response->getStatusCode());
    }
}
