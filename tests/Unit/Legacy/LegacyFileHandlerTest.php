<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Legacy\LegacyFileHandler;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class LegacyFileHandlerTest extends TestCase
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

    public function test_copies_get_and_post_params_chdirs_to_the_targets_directory_and_requires_it(): void
    {
        $_GET = [];
        $_POST = [];
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/tests/fixtures/legacy_process_fixture.php?action=logout')
            ->withQueryParams(['action' => 'logout'])
            ->withParsedBody(['field' => 'value']);
        $response = (new ResponseFactory())->createResponse(200);

        ob_start();
        $handler = new LegacyFileHandler('tests/fixtures/legacy_process_fixture.php');
        $result = $handler($request, $response);
        $output = ob_get_clean();

        $this->assertSame('logout', $_GET['action']);
        $this->assertSame('value', $_POST['field']);
        $this->assertStringContainsString('FIXTURE_OK action=logout posted=value cwd_basename=fixtures', $output);
        $this->assertSame($response, $result);
    }
}
