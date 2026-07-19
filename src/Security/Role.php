<?php

declare(strict_types=1);

namespace Bcoem\Security;

enum Role: int
{
    case SuperAdmin = 0;
    case Admin = 1;
    case Judge = 2;
    case Entrant = 3;
    case Anonymous = 100;

    public static function fromUserLevel(?string $userLevel): self
    {
        // Fail safe to Entrant for anything that isn't a clean non-negative
        // integer string - PHP's (int) cast turns '', 'abc', etc. into 0,
        // which would otherwise silently escalate a malformed session value
        // to SuperAdmin. Deny-by-default groundwork must not have this hole.
        if ($userLevel === null || !ctype_digit($userLevel)) {
            return self::Entrant;
        }
        return match ((int)$userLevel) {
            0 => self::SuperAdmin,
            1 => self::Admin,
            2 => self::Judge,
            default => self::Entrant,
        };
    }

    /**
     * True if this role grants at least as much privilege as $required,
     * using the app's existing numeric userLevel convention: lower value
     * = more privileged. Anonymous only satisfies an Anonymous requirement.
     */
    public function satisfies(Role $required): bool
    {
        if ($required === self::Anonymous) {
            return true;
        }
        if ($this === self::Anonymous) {
            return false;
        }
        return $this->value <= $required->value;
    }
}
