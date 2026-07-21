<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\Command;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * RecordScoreCommand is the input DTO for recording a judge's score.
 *
 * Includes version field for optimistic locking:
 * when a judge fetches an entry to score, they get the current version.
 * When they submit the score, we check that version matches before updating.
 * If another judge already updated it, version will be higher, update fails (ConcurrentModificationException).
 */
final class RecordScoreCommand
{
    #[Assert\NotNull(message: 'Entry ID is required')]
    #[Assert\Type('integer')]
    #[Assert\Positive(message: 'Entry ID must be positive')]
    public int $entryId;

    #[Assert\NotNull(message: 'Table ID is required')]
    #[Assert\Type('integer')]
    #[Assert\Positive(message: 'Table ID must be positive')]
    public int $tableId;

    #[Assert\NotNull(message: 'Score is required')]
    #[Assert\Type('float')]
    #[Assert\Range(min: 0, max: 50, notInRangeMessage: 'Score must be between 0 and 50')]
    public float $score;

    #[Assert\Type('string')]
    #[Assert\Length(max: 3)]
    public ?string $place = null;

    #[Assert\NotNull(message: 'Score type is required')]
    #[Assert\Choice(choices: ['regular', 'mini-bos', 'bos'], message: 'Invalid score type')]
    public string $scoreType = 'regular';

    #[Assert\Type('integer')]
    #[Assert\Choice(choices: [0, 1])]
    public int $miniBos = 0;

    #[Assert\NotNull(message: 'Version is required for optimistic locking')]
    #[Assert\Type('integer')]
    #[Assert\Positive(message: 'Version must be positive')]
    public int $version;

    public function __construct(
        int $entryId,
        int $tableId,
        float $score,
        int $version,
        ?string $place = null,
        string $scoreType = 'regular',
        int $miniBos = 0
    ) {
        $this->entryId = $entryId;
        $this->tableId = $tableId;
        $this->score = $score;
        $this->version = $version;
        $this->place = $place;
        $this->scoreType = $scoreType;
        $this->miniBos = $miniBos;
    }
}
