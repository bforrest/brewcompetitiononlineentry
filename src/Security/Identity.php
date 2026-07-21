<?php

declare(strict_types=1);

namespace Bcoem\Security;

final class Identity
{
    private function __construct(
        public readonly bool $loggedIn,
        public readonly ?string $username,
        public readonly Role $role,
    ) {
    }

    /** @param array<string, mixed> $session */
    public static function fromSession(array $session): self
    {
        if (!isset($session['loginUsername'])) {
            return new self(false, null, Role::Anonymous);
        }
        return new self(
            true,
            (string)$session['loginUsername'],
            Role::fromUserLevel(isset($session['userLevel']) ? (string)$session['userLevel'] : null)
        );
    }

    public function isAdmin(): bool
    {
        return $this->role->value <= Role::Admin->value;
    }

    public function userLevel(): int
    {
        return match ($this->role) {
            Role::Anonymous => 2,
            Role::Entrant => 1,
            Role::Admin => 1,
            Role::SuperAdmin => 0,
        };
    }

    public function competitionId(): ?int
    {
        return isset($GLOBALS['session']['comp_id']) ? (int) $GLOBALS['session']['comp_id'] : null;
    }
}
