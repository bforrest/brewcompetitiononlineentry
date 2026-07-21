<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\ValueObject;

enum ExportView: string
{
    case DEFAULT = 'default';
    case ALL = 'all';
    case NOT_RECEIVED = 'not_received';

    public function description(): string
    {
        return match ($this) {
            self::DEFAULT => 'Standard view',
            self::ALL => 'All entries',
            self::NOT_RECEIVED => 'Not received',
        };
    }
}
