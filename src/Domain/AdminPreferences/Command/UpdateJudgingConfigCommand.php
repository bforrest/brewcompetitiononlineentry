<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Command;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * UpdateJudgingConfigCommand is the input DTO for updating judging configuration.
 *
 * Properties:
 * - isQueued: Whether to use queued judging mode
 * - maxFlightEntries: Maximum entries per flight (1-999)
 * - maxBosPerStyle: Maximum BOS places per style (1-999)
 * - maxRounds: Maximum rounds per table (1-999)
 */
final class UpdateJudgingConfigCommand
{
    #[Assert\Type(type: 'boolean')]
    public bool $isQueued;

    #[Assert\Type(type: 'integer')]
    #[Assert\Range(min: 1, max: 999)]
    public int $maxFlightEntries;

    #[Assert\Type(type: 'integer')]
    #[Assert\Range(min: 1, max: 999)]
    public int $maxBosPerStyle;

    #[Assert\Type(type: 'integer')]
    #[Assert\Range(min: 1, max: 999)]
    public int $maxRounds;

    public function __construct(bool $isQueued, int $maxFlightEntries, int $maxBosPerStyle, int $maxRounds)
    {
        $this->isQueued = $isQueued;
        $this->maxFlightEntries = $maxFlightEntries;
        $this->maxBosPerStyle = $maxBosPerStyle;
        $this->maxRounds = $maxRounds;
    }
}
