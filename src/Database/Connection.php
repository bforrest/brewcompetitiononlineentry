<?php

declare(strict_types=1);

namespace Bcoem\Database;

/**
 * Single point of entry for all database access. This wrapper enforces:
 * 1. Prepared statements ONLY (no string interpolation, no sprintf)
 * 2. Type-safe parameter passing
 * 3. Consistent exception handling (mysqli_sql_exception throws from container.php's mysqli_report)
 *
 * PHPStan rule enforced in phpstan.neon: no mysqli_* function calls outside this class.
 *
 * Design: wraps the legacy $GLOBALS['$connection'] (passed by reference from paths.php).
 * Legacy code continues to use that global; Phase 3+ code uses this wrapper exclusively.
 * This allows the front controller to boot once (index.php) instead of per-request (old model).
 */
class Connection
{
    public function __construct(private \mysqli $mysqli)
    {
    }

    /**
     * Execute a SELECT query with prepared statement.
     *
     * @param string $sql SQL query with ? placeholders for parameters
     * @param array<int|string|float|null> $params Parameters to bind (order must match ? placeholders)
     * @return array<int, array<string, mixed>> Result rows as associative arrays
     * @throws \mysqli_sql_exception on query failure or prepare failure
     */
    public function select(string $sql, array $params = []): array
    {
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            throw new \mysqli_sql_exception($this->mysqli->error, $this->mysqli->errno);
        }

        if (count($params) > 0) {
            $types = $this->inferTypes($params);
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result) {
            throw new \mysqli_sql_exception($stmt->error, $stmt->errno);
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();
        return $rows;
    }

    /**
     * Execute a SELECT query returning a single row.
     *
     * @param string $sql SQL query with ? placeholders
     * @param array<int|string|float|null> $params Parameters to bind
     * @return array<string, mixed>|null Associative array or null if no row found
     * @throws \mysqli_sql_exception on query failure
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $rows = $this->select($sql, $params);
        return $rows[0] ?? null;
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE query.
     *
     * @param string $sql SQL query with ? placeholders (INSERT/UPDATE/DELETE)
     * @param array<int|string|float|null> $params Parameters to bind
     * @return int Number of affected rows
     * @throws \mysqli_sql_exception on query failure
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            throw new \mysqli_sql_exception($this->mysqli->error, $this->mysqli->errno);
        }

        if (count($params) > 0) {
            $types = $this->inferTypes($params);
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    /**
     * Get the ID of the last inserted row.
     *
     * @return int Last insert ID
     */
    public function lastInsertId(): int
    {
        return (int) $this->mysqli->insert_id;
    }

    /**
     * Begin a transaction.
     *
     * @throws \mysqli_sql_exception on failure
     */
    public function beginTransaction(): void
    {
        if (!$this->mysqli->begin_transaction()) {
            throw new \mysqli_sql_exception($this->mysqli->error, $this->mysqli->errno);
        }
    }

    /**
     * Commit the current transaction.
     *
     * @throws \mysqli_sql_exception on failure
     */
    public function commit(): void
    {
        if (!$this->mysqli->commit()) {
            throw new \mysqli_sql_exception($this->mysqli->error, $this->mysqli->errno);
        }
    }

    /**
     * Rollback the current transaction.
     *
     * @throws \mysqli_sql_exception on failure
     */
    public function rollback(): void
    {
        if (!$this->mysqli->rollback()) {
            throw new \mysqli_sql_exception($this->mysqli->error, $this->mysqli->errno);
        }
    }

    /**
     * Infer mysqli parameter types from PHP values.
     * Returns a type string for bind_param: 'i' for int, 'd' for float, 's' for string.
     *
     * @param array<int|string|float|null> $params
     * @return string Type string for bind_param (e.g., 'iss' for int, string, string)
     */
    private function inferTypes(array $params): string
    {
        $types = '';
        foreach ($params as $param) {
            if ($param === null) {
                $types .= 's'; // NULL treated as string
            } elseif (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's'; // string or other (converted to string)
            }
        }
        return $types;
    }
}
