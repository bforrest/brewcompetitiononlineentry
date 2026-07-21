<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\Command;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for bulk updating entry status (paid, received, confirmed, etc.).
 */
final class BulkUpdateEntryStatusCommand
{
    #[Assert\NotBlank(message: 'Entry IDs are required')]
    #[Assert\Type('array')]
    public array $entryIds = [];

    #[Assert\NotBlank(message: 'New status is required')]
    #[Assert\Choice(choices: ['paid', 'unpaid', 'received', 'not_received', 'confirmed', 'unconfirmed'])]
    public string $newStatus = '';

    public ?string $reason = null;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}
