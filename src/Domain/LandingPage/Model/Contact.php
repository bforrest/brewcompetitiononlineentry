<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

final readonly class Contact
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $position,
        public string $email,
    ) {
    }
}
