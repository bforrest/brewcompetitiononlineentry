<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\Registration\Service;

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Registration\Service\GoogleRecaptchaVerifier;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

class GoogleRecaptchaVerifierTest extends TestCase
{
    public function test_verify_returns_true_on_success_and_matching_hostname_and_request_is_well_formed(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'hostname' => 'example.test'])),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
        $client = new Client(['handler' => $stack]);
        $verifier = new GoogleRecaptchaVerifier($client, 'secret-key', 'example.test');

        $result = $verifier->verify(['g-recaptcha-response' => 'token'], '127.0.0.1');

        $this->assertTrue($result);

        // Verify the request was well-formed
        $this->assertCount(1, $container);
        $request = $container[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('www.google.com', $request->getUri()->getHost());
        $this->assertSame('/recaptcha/api/siteverify', $request->getUri()->getPath());

        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame('secret-key', $query['secret']);
        $this->assertSame('token', $query['response']);
    }

    public function test_verify_returns_false_when_response_missing(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);
        $verifier = new GoogleRecaptchaVerifier($client, 'secret-key', 'example.test');

        $this->assertFalse($verifier->verify([], '127.0.0.1'));
    }

    public function test_verify_returns_false_on_hostname_mismatch(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'hostname' => 'attacker.test'])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $verifier = new GoogleRecaptchaVerifier($client, 'secret-key', 'example.test');

        $this->assertFalse($verifier->verify(['g-recaptcha-response' => 'token'], '127.0.0.1'));
    }

    public function test_verify_returns_false_when_google_reports_failure(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => false, 'hostname' => 'example.test'])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $verifier = new GoogleRecaptchaVerifier($client, 'secret-key', 'example.test');

        $this->assertFalse($verifier->verify(['g-recaptcha-response' => 'token'], '127.0.0.1'));
    }
}
