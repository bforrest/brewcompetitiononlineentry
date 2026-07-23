<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Repository;

use Bcoem\Database\Connection;
use Bcoem\Domain\Registration\Form\RegistrationFormOptions;

/**
 * Read-side source for the standard entrant registration form.
 *
 * The legacy country and state lists are static application data rather than
 * database tables, so this repository intentionally leaves those choices for
 * their dedicated source when it is introduced. All contest-configured data
 * is read through Connection prepared statements.
 */
final class RegistrationOptionsRepository
{
    private string $tablePrefix;

    public function __construct(private Connection $connection)
    {
        $this->tablePrefix = $GLOBALS['prefix'] ?? 'baseline_';
    }

    public function options(): RegistrationFormOptions
    {
        $contest = $this->connection->selectOne(
            'SELECT contestName, contestRules, contestRegistrationOpen, contestRegistrationDeadline '
            . 'FROM ' . $this->tablePrefix . 'contest_info WHERE id = 1'
        ) ?? [];
        $preferences = $this->connection->selectOne(
            'SELECT prefsShipping, prefsDropOff FROM ' . $this->tablePrefix . 'preferences WHERE id = 1'
        ) ?? [];

        $dropOffEnabled = $this->enabled($preferences['prefsDropOff'] ?? null);

        return new RegistrationFormOptions(
            title: (string) ($contest['contestName'] ?? ''),
            guidance: (string) ($contest['contestRules'] ?? ''),
            dropOffChoices: $dropOffEnabled ? $this->dropOffChoices() : [],
            availability: [
                'shipping' => $this->enabled($preferences['prefsShipping'] ?? null),
                'dropOff' => $dropOffEnabled,
            ],
            registrationOpen: $this->registrationOpen($contest),
        );
    }

    /** @return array<string, string> */
    private function dropOffChoices(): array
    {
        $choices = [];
        foreach ($this->connection->select(
            'SELECT id, dropLocationName FROM ' . $this->tablePrefix . 'drop_off '
            . 'WHERE dropLocationName IS NOT NULL AND dropLocationName <> ? ORDER BY id',
            ['']
        ) as $row) {
            $choices[(string) $row['id']] = (string) $row['dropLocationName'];
        }

        return $choices;
    }

    /** @param array<string, mixed> $contest */
    private function registrationOpen(array $contest): bool
    {
        $open = $contest['contestRegistrationOpen'] ?? null;
        $deadline = $contest['contestRegistrationDeadline'] ?? null;
        if (!is_numeric($open) || !is_numeric($deadline)) {
            return false;
        }

        $now = time();
        return (int) $open <= $now && $now < (int) $deadline;
    }

    private function enabled(mixed $value): bool
    {
        return (int) $value === 1;
    }
}
