<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\Registration\Service;

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Registration\Service\HCaptchaVerifier;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

class HCaptchaVerifierTest extends TestCase
{
    public function test_verify_returns_true_on_success(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $verifier = new HCaptchaVerifier($client, 'secret-key');

        $this->assertTrue($verifier->verify(['h-captcha-response' => 'token'], '127.0.0.1'));
    }

    public function test_verify_returns_false_on_failure(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => false])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $verifier = new HCaptchaVerifier($client, 'secret-key');

        $this->assertFalse($verifier->verify(['h-captcha-response' => 'token'], '127.0.0.1'));
    }

    public function test_verify_returns_false_when_response_missing(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);
        $verifier = new HCaptchaVerifier($client, 'secret-key');

        $this->assertFalse($verifier->verify([], '127.0.0.1'));
    }

    public function test_verify_request_is_well_formed(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true])),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
        $client = new Client(['handler' => $stack]);
        $verifier = new HCaptchaVerifier($client, 'secret-key');

        $result = $verifier->verify(['h-captcha-response' => 'token'], '127.0.0.1');

        $this->assertTrue($result);

        // Verify the request was well-formed
        $this->assertCount(1, $container);
        $request = $container[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('hcaptcha.com', $request->getUri()->getHost());
        $this->assertSame('/siteverify', $request->getUri()->getPath());

        // Check form_params were sent correctly
        $body = (string) $request->getBody();
        $this->assertStringContainsString('secret=secret-key', $body);
        $this->assertStringContainsString('response=token', $body);
    }
}
