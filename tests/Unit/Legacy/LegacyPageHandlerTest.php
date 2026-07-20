<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Legacy\LegacyPageHandler;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class LegacyPageHandlerTest extends TestCase
{
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
    }

    public function test_copies_query_params_into_get_chdirs_to_root_and_requires_the_target_file(): void
    {
        $_GET = [];
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/index.php?section=contact&go=entrant')
            ->withQueryParams(['section' => 'contact', 'go' => 'entrant']);
        $response = (new ResponseFactory())->createResponse(200);

        ob_start();
        $handler = new LegacyPageHandler('tests/fixtures/legacy_root_fixture.php');
        $result = $handler($request, $response);
        $output = ob_get_clean();

        $this->assertSame('contact', $_GET['section']);
        $this->assertSame('entrant', $_GET['go']);
        $this->assertStringContainsString('FIXTURE_OK section=contact go=entrant', $output);
        $this->assertSame(rtrim(ROOT, '/'), rtrim(getcwd(), '/'));
        $this->assertSame($response, $result);
    }
}
