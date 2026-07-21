<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export;

use Bcoem\Domain\Export\ValueObject\ExportId;
use Bcoem\Domain\Export\ValueObject\ExportMetadata;

final class Export
{
    public function __construct(
        private readonly ExportId $id,
        private readonly ExportMetadata $metadata,
    ) {
    }

    public function id(): ExportId
    {
        return $this->id;
    }

    public function metadata(): ExportMetadata
    {
        return $this->metadata;
    }
}
