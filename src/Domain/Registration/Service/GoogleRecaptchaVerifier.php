<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Service;

use GuzzleHttp\ClientInterface;

/**
 * Ports process_users_register.inc.php:53-60 (reCAPTCHA v2 branch) verbatim,
 * over Guzzle instead of file_get_contents so it's mockable in unit tests.
 */
final class GoogleRecaptchaVerifier implements CaptchaVerifier
{
    public function __construct(
        private ClientInterface $client,
        private string $secretKey,
        private string $expectedHostname,
    ) {
    }

    public function verify(array $postData, string $remoteAddr): bool
    {
        $token = $postData['g-recaptcha-response'] ?? null;
        if (empty($token)) {
            return false;
        }

        $response = $this->client->request('GET', 'https://www.google.com/recaptcha/api/siteverify', [
            'query' => [
                'secret' => $this->secretKey,
                'response' => $token,
            ],
        ]);

        $data = json_decode((string) $response->getBody());

        return isset($data->success, $data->hostname)
            && $data->success === true
            && $data->hostname === $this->expectedHostname;
    }
}
