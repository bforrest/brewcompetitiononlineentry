<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

final readonly class CompetitionLimits
{
    public int $entryCount;
    public int $paidEntryCount;
    public ?int $entryLimit;
    public ?int $paidEntryLimit;
    public int $nearLimitThreshold;

    public function __construct(
        int $entryCount,
        int $paidEntryCount,
        ?int $entryLimit,
        ?int $paidEntryLimit,
        int $nearLimitThreshold,
    ) {
        $this->entryCount = max(0, $entryCount);
        $this->paidEntryCount = max(0, $paidEntryCount);
        $this->entryLimit = $entryLimit !== null && $entryLimit > 0 ? $entryLimit : null;
        $this->paidEntryLimit = $paidEntryLimit !== null && $paidEntryLimit > 0 ? $paidEntryLimit : null;
        $this->nearLimitThreshold = $this->entryLimit === null
            ? 0
            : (int) floor($this->entryLimit * 0.9) + 1;
    }
}
