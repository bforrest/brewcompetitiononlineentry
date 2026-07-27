<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Service;

final readonly class LegacyContactLinkEncoder implements ContactLinkEncoder
{
    public function __construct(
        private string $databasePassword,
        private string $serverRoot,
    ) {
    }

    public function destinationFor(int $contactId): string
    {
        $key = base64_encode(bin2hex($this->databasePassword));
        $encryptionKey = base64_decode($key, true);
        if ($encryptionKey === false) {
            throw new \RuntimeException('Unable to initialize the contact-link encryption key.');
        }

        $iv = random_bytes(openssl_cipher_iv_length('AES-128-CBC'));
        $encrypted = openssl_encrypt(
            sprintf('%06d', $contactId),
            'AES-128-CBC',
            $encryptionKey,
            0,
            $iv,
        );
        if ($encrypted === false) {
            throw new \RuntimeException('Unable to encrypt the contact destination.');
        }

        $token = base64_encode($encrypted . '::' . $iv);

        return '/includes/output.inc.php?section=contact&action=edit&tb=no-print&token='
            . rawurlencode($token);
    }
}
