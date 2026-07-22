<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Service;

use GuzzleHttp\ClientInterface;

/** Ports process_users_register.inc.php:63-81 (hCaptcha branch) verbatim, over Guzzle. */
final class HCaptchaVerifier implements CaptchaVerifier
{
    public function __construct(
        private ClientInterface $client,
        private string $secretKey,
    ) {
    }

    public function verify(array $postData, string $remoteAddr): bool
    {
        $token = $postData['h-captcha-response'] ?? null;
        if (empty($token)) {
            return false;
        }

        $response = $this->client->request('POST', 'https://hcaptcha.com/siteverify', [
            'form_params' => [
                'secret' => $this->secretKey,
                'response' => $token,
            ],
        ]);

        $data = json_decode((string) $response->getBody());

        return isset($data->success) && $data->success === true;
    }
}
