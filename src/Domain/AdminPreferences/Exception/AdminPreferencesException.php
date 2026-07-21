<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\Exception;

use Exception;

/**
 * Base exception for all admin preferences domain errors.
 *
 * Subclasses must implement getHttpStatus() and isExpected().
 */
abstract class AdminPreferencesException extends Exception
{
    /**
     * HTTP status code to return when this exception is caught by middleware.
     *
     * @return int HTTP status code (e.g., 404, 409, 422, 500)
     */
    abstract public function getHttpStatus(): int;

    /**
     * Whether this exception represents expected user error vs. system failure.
     *
     * True: user error (e.g., locked competition, invalid constraint) → log at INFO level
     * False: system error (e.g., DB query failed) → log at ERROR level, page = 500
     *
     * @return bool
     */
    abstract public function isExpected(): bool;
}
