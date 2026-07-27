<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Service;

interface ContactLinkEncoder
{
    public function destinationFor(int $contactId): string;
}
