<?php

declare(strict_types=1);

namespace BCOEM\Tests\Unit\Kernel\Controller;

use Bcoem\Domain\LandingPage\Model\CompetitionLimits;
use Bcoem\Domain\LandingPage\Model\CompetitionLocations;
use Bcoem\Domain\LandingPage\Model\CompetitionWindows;
use Bcoem\Domain\LandingPage\Model\ContestOverview;
use Bcoem\Domain\LandingPage\Model\JudgingProgress;
use Bcoem\Domain\LandingPage\Model\WinnerMethod;
use Bcoem\Domain\LandingPage\Model\WinnerSummary;
use Bcoem\Domain\LandingPage\Repository\LandingPageReadRepository;
use Bcoem\Domain\LandingPage\Service\LandingPageCopyAdapter;
use Bcoem\Domain\LandingPage\Service\LandingPageService;
use Bcoem\Kernel\Controller\LandingPageController;
use Bcoem\Kernel\View\LayoutRenderer;
use Bcoem\Security\Identity;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class LandingPageControllerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalSession;

    protected function setUp(): void
    {
        $this->originalSession = isset($_SESSION) && is_array($_SESSION) ? $_SESSION : [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
    }

    public function test_anonymous_request_renders_typed_landing_html(): void
    {
        $_SESSION['prefsLanguage'] = 'en-US';
        $_SESSION['prefsSelectedStyles'] = '[]';
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/')
            ->withAttribute('identity', Identity::fromSession([]));

        $response = $this->controller()->show(
            $request,
            (new ResponseFactory())->createResponse(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<main id="main-content"', (string) $response->getBody());
    }

    public function test_authenticated_request_renders_greeting_and_account_link(): void
    {
        $_SESSION['brewerFirstName'] = '  Ada  ';
        $identity = Identity::fromSession([
            'loginUsername' => 'entrant@example.test',
            'userLevel' => '2',
        ]);
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/')
            ->withAttribute('identity', $identity);

        $response = $this->controller()->show(
            $request,
            (new ResponseFactory())->createResponse(),
        );
        $html = (string) $response->getBody();

        self::assertStringContainsString('Hello, Ada', $html);
        self::assertStringContainsString('href="/index.php?section=list">Account</a>', $html);
        self::assertStringNotContainsString('href="/register">Register</a>', $html);
    }

    public function test_malformed_session_context_falls_back_to_supported_defaults(): void
    {
        $_SESSION = [
            'loginUsername' => 'session-user@example.test',
            'userLevel' => '2',
            'brewerFirstName' => ['not', 'a', 'name'],
            'prefsLanguage' => 'fr-FR',
            'prefsSelectedStyles' => '{not-json',
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/')
            ->withAttribute('identity', 'not-an-identity');

        $response = $this->controller()->show(
            $request,
            (new ResponseFactory())->createResponse(),
        );
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Hello, session-user@example.test', $html);
        self::assertStringContainsString(
            'src="/images/misc-cropped-bottles_3000x500.jpg"',
            $html,
        );
    }

    public function test_valid_selected_style_uses_its_matching_hero_image(): void
    {
        $_SESSION['prefsSelectedStyles'] = '[{"brewStyleType":"1"}]';
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/')
            ->withAttribute('identity', Identity::fromSession([]));

        $response = $this->controller()->show(
            $request,
            (new ResponseFactory())->createResponse(),
        );

        self::assertStringContainsString(
            'src="/images/beer-barley-malt_3000x500.jpg"',
            (string) $response->getBody(),
        );
    }

    public function test_array_or_object_session_identity_fields_are_anonymous(): void
    {
        $_SESSION = [
            'loginUsername' => ['not', 'a', 'username'],
            'userLevel' => new \stdClass(),
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/')
            ->withAttribute('identity', 'not-an-identity');

        $response = $this->controller()->show(
            $request,
            (new ResponseFactory())->createResponse(),
        );
        $html = (string) $response->getBody();

        self::assertStringContainsString('href="/register">Register</a>', $html);
        self::assertStringNotContainsString('Hello,', $html);
    }

    public function test_scalar_session_identity_fields_are_safely_normalized(): void
    {
        $_SESSION = [
            'loginUsername' => 42,
            'userLevel' => 2,
        ];
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/')
            ->withAttribute('identity', 'not-an-identity');

        $response = $this->controller()->show(
            $request,
            (new ResponseFactory())->createResponse(),
        );

        self::assertStringContainsString('Hello, 42', (string) $response->getBody());
    }

    /**
     * @dataProvider invalidStyleTypeValues
     */
    public function test_invalid_selected_style_types_are_ignored_before_valid_selection(mixed $styleType): void
    {
        $_SESSION['prefsSelectedStyles'] = json_encode([
            ['brewStyleType' => $styleType],
            ['brewStyleType' => 3],
        ], JSON_THROW_ON_ERROR);
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/')
            ->withAttribute('identity', Identity::fromSession([]));

        $response = $this->controller()->show(
            $request,
            (new ResponseFactory())->createResponse(),
        );

        self::assertStringContainsString(
            'src="/images/mead-bottles_3000x500.jpg"',
            (string) $response->getBody(),
        );
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidStyleTypeValues(): iterable
    {
        yield 'boolean' => [true];
        yield 'array' => [[1]];
        yield 'trailing text' => ['2junk'];
        yield 'out of range' => [4];
    }

    private function controller(): LandingPageController
    {
        $now = time();
        $repository = $this->createMock(LandingPageReadRepository::class);
        $repository->method('contestOverview')->willReturn(
            new ContestOverview(
                'Fixture Competition',
                'Fixture Host',
                'https://host.example.test',
                'Austin, Texas',
                null,
            ),
        );
        $repository->method('competitionWindows')->willReturn(
            new CompetitionWindows(
                $now - 3600,
                $now + 3600,
                $now - 3600,
                $now + 3600,
                $now - 3600,
                $now + 3600,
                null,
                null,
                null,
                null,
            ),
        );
        $repository->method('competitionLimits')->willReturn(
            new CompetitionLimits(5, 3, 100, 80, 90),
        );
        $repository->method('judgingProgress')->willReturn(
            new JudgingProgress(false, false, false, 0),
        );
        $repository->method('locations')->willReturn(
            new CompetitionLocations(null, null, null, null, null, null),
        );
        $repository->method('contacts')->willReturn([]);
        $repository->method('sponsors')->willReturn([]);
        $repository->method('visibleArchives')->willReturn([]);
        $repository->method('winnerSummary')->willReturn(
            new WinnerSummary(WinnerMethod::Overall, '', []),
        );

        return new LandingPageController(
            new LandingPageService($repository, new LandingPageCopyAdapter()),
            new LayoutRenderer(),
        );
    }
}
