<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\Service;

use Bcoem\Domain\Export\Command\GenerateExportCommand;
use Bcoem\Domain\Export\Exception\InvalidExportFilterException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ExportValidationService
{
    public function __construct(
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Validate export command.
     *
     * @throws InvalidExportFilterException if validation fails
     */
    public function validateCommand(GenerateExportCommand $command): void
    {
        $errors = $this->validator->validate($command);

        if ($errors->count() > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = sprintf('%s: %s', $error->getPropertyPath(), $error->getMessage());
            }

            throw new InvalidExportFilterException(
                sprintf('Command validation failed: %s', implode(', ', $messages))
            );
        }
    }
}
