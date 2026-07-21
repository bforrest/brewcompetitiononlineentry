<?php

declare(strict_types=1);

namespace Bcoem\Domain\Entry\Service;

use Bcoem\Database\Connection;
use Bcoem\Security\Identity;

/**
 * Writes audit log entries to the audit_log table.
 * Called from services whenever a domain event (create/update/delete) completes.
 */
final class AuditLogger
{
    private string $tablePrefix;

    public function __construct(private Connection $connection)
    {
        $this->tablePrefix = $GLOBALS['prefix'] ?? 'baseline_';
    }

    /**
     * Record a domain action to the audit log.
     *
     * @param Identity $identity Actor performing the action
     * @param string $action Action type (create, update, delete, etc.)
     * @param string $entity Entity type (entry, brewer, etc.)
     * @param ?int $entityId Primary key of affected entity
     * @param ?string $beforeJson JSON snapshot of entity before change
     * @param ?string $afterJson JSON snapshot of entity after change
     * @param ?string $ipAddress Client IP address
     */
    public function record(
        Identity $identity,
        string $action,
        string $entity,
        ?int $entityId,
        ?string $beforeJson,
        ?string $afterJson,
        ?string $ipAddress = null,
    ): void {
        // Extract user ID from identity
        // TODO: Once Identity has a brewer association, use that
        $userId = null; // Will be fetched from session if available
        if ($identity->loggedIn && isset($_SESSION['user_id'])) {
            $userId = (int) $_SESSION['user_id'];
        }

        $sql = 'INSERT INTO ' . $this->tablePrefix . 'audit_log
                (user_id, action, entity, entity_id, before_json, after_json, ip, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())';

        $this->connection->execute(
            $sql,
            [
                $userId,
                $action,
                $entity,
                $entityId,
                $beforeJson,
                $afterJson,
                $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            ]
        );
    }
}
