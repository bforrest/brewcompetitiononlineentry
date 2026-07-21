<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\ValueObject;

enum ExportFilter: string
{
    case PAID = 'paid';
    case NOPAY = 'nopay';
    case REQUIRED = 'required';
    case WINNERS = 'winners';
    case CIRCUIT = 'circuit';
    case ALL = 'all';
    case JUDGES = 'judges';
    case STEWARDS = 'stewards';
    case STAFF = 'staff';
    case AVAIL_JUDGES = 'avail_judges';
    case AVAIL_STEWARDS = 'avail_stewards';
    case JUDGING_SCORES = 'judging_scores';
    case JUDGING_SCORES_BOS = 'judging_scores_bos';

    public function isArchiveSupported(): bool
    {
        return match ($this) {
            self::PAID, self::NOPAY, self::REQUIRED, self::WINNERS, self::CIRCUIT,
            self::JUDGING_SCORES, self::JUDGING_SCORES_BOS => true,
            default => false,
        };
    }

    public function requiresAdminAccess(): bool
    {
        return match ($this) {
            self::JUDGES, self::STEWARDS, self::STAFF,
            self::AVAIL_JUDGES, self::AVAIL_STEWARDS,
            self::JUDGING_SCORES, self::JUDGING_SCORES_BOS => true,
            default => false,
        };
    }
}
