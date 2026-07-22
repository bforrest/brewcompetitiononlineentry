<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\Registration\Service;

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Registration\Service\GoogleRecaptchaVerifier;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class GoogleRecaptchaVerifierTest extends TestCase
{
    public function test_verify_returns_true_on_success_and_matching_hostname(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'hostname' => 'example.test'])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $verifier = new GoogleRecaptchaVerifier($client, 'secret-key', 'example.test');

        $this->assertTrue($verifier->verify(['g-recaptcha-response' => 'token'], '127.0.0.1'));
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
