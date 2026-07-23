<?php

declare(strict_types=1);

namespace Bcoem\Domain\Registration\Repository;

use Bcoem\Database\Connection;
use Bcoem\Domain\Registration\Form\LegacyEntrantChoiceSource;
use Bcoem\Domain\Registration\Form\RegistrationFormOptions;

/**
 * Read-side source for the standard entrant registration form.
 *
 * Contest-configured data is read through Connection prepared statements;
 * legacy static country/state choices come from LegacyEntrantChoiceSource.
 */
final class RegistrationOptionsRepository
{
    private string $tablePrefix;
    private LegacyEntrantChoiceSource $choiceSource;

    public function __construct(private Connection $connection, ?LegacyEntrantChoiceSource $choiceSource = null)
    {
        $this->tablePrefix = $GLOBALS['prefix'] ?? 'baseline_';
        $this->choiceSource = $choiceSource ?? new LegacyEntrantChoiceSource();
    }

    public function options(): RegistrationFormOptions
    {
        $contest = $this->connection->selectOne(
            'SELECT contestName, contestRules, contestRegistrationOpen, contestRegistrationDeadline, '
            . 'contestShippingOpen, contestShippingDeadline, contestDropoffOpen, contestDropoffDeadline '
            . 'FROM ' . $this->tablePrefix . 'contest_info WHERE id = 1'
        ) ?? [];
        $preferences = $this->connection->selectOne(
            'SELECT prefsShipping, prefsDropOff FROM ' . $this->tablePrefix . 'preferences WHERE id = 1'
        ) ?? [];

        $shippingAvailable = $this->enabled($preferences['prefsShipping'] ?? null)
            && $this->logisticsWindowAvailable($contest, 'contestShippingOpen', 'contestShippingDeadline');
        $dropOffAvailable = $this->enabled($preferences['prefsDropOff'] ?? null)
            && $this->logisticsWindowAvailable($contest, 'contestDropoffOpen', 'contestDropoffDeadline');

        return new RegistrationFormOptions(
            title: (string) ($contest['contestName'] ?? ''),
            guidance: (string) ($contest['contestRules'] ?? ''),
            countryChoices: $this->choiceSource->countryChoices(),
            stateChoices: $this->choiceSource->stateChoices(),
            dropOffChoices: $dropOffAvailable ? $this->dropOffChoices() : [],
            availability: [
                'shipping' => $shippingAvailable,
                'dropOff' => $dropOffAvailable,
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
            . 'WHERE dropLocationName IS NOT NULL AND dropLocationName <> ? ORDER BY dropLocationName, id',
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

    /**
     * Legacy treats a logistics method as available before or during its
     * configured window, and falls back to available when either date is absent.
     * It only closes after the configured deadline.
     *
     * @param array<string, mixed> $contest
     */
    private function logisticsWindowAvailable(array $contest, string $openColumn, string $deadlineColumn): bool
    {
        $open = $contest[$openColumn] ?? null;
        $deadline = $contest[$deadlineColumn] ?? null;
        if (!is_numeric($open) || !is_numeric($deadline) || (int) $open <= 0 || (int) $deadline <= 0) {
            return true;
        }

        return time() <= (int) $deadline;
    }

    private function enabled(mixed $value): bool
    {
        return (int) $value === 1;
    }
}
