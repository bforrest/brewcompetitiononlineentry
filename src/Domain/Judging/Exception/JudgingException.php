<?php
declare(strict_types=1);

namespace Bcoem\Domain\Judging\Exception;

use Exception;

/**
 * Base exception for all judging domain errors.
 *
 * Subclasses must implement getHttpStatus() and isExpected().
 */
abstract class JudgingException extends Exception
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
     * True: user error (e.g., locked table, invalid score) → log at INFO level
     * False: system error (e.g., DB query failed) → log at ERROR level, page = 500
     *
     * @return bool
     */
    abstract public function isExpected(): bool;
}
