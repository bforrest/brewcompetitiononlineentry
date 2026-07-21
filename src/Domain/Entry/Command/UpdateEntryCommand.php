<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\Command;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for updating an entry. Extends CreateEntryCommand with the entry ID.
 */
final class UpdateEntryCommand extends CreateEntryCommand
{
    #[Assert\NotBlank(message: 'Entry ID is required')]
    #[Assert\Type('integer', message: 'Entry ID must be an integer')]
    public int $id = 0;

    public function __construct(array $data = [])
    {
        parent::__construct($data);
        if (isset($data['id'])) {
            $this->id = (int) $data['id'];
        }
    }
}
