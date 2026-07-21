<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Task 13, Part 1: first Phinx migration. Purely additive - creates one new
 * table for Phase 3's repositories to write to; touches nothing in the
 * existing legacy schema (this app is mid-strangler-migration, so nothing
 * here may alter or drop an existing table - see the design spec's
 * forward-only rule during this period).
 *
 * Table name is NOT prefixed with the app's $prefix in the class body -
 * phinx.php's 'table_prefix' environment option (TablePrefixAdapter)
 * applies $prefix transparently to every $this->table(...) call, so this
 * migration (and every future one) refers to logical table names only,
 * exactly like Phase 3 repository code will.
 */
final class CreateAuditLog extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('audit_log', ['id' => 'id', 'signed' => false]);
        $table
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true, 'comment' => 'FK to {prefix}users.id; null for system-initiated actions'])
            ->addColumn('action', 'string', ['limit' => 50, 'null' => false, 'comment' => 'e.g. create, update, delete'])
            ->addColumn('entity', 'string', ['limit' => 100, 'null' => false, 'comment' => 'logical entity/table name the action was performed on'])
            ->addColumn('entity_id', 'integer', ['signed' => false, 'null' => true, 'comment' => 'primary key of the affected row in $entity, if any'])
            ->addColumn('before_json', 'text', ['null' => true, 'comment' => 'JSON snapshot of the row before the change'])
            ->addColumn('after_json', 'text', ['null' => true, 'comment' => 'JSON snapshot of the row after the change'])
            ->addColumn('ip', 'string', ['limit' => 45, 'null' => true, 'comment' => 'client IP; 45 chars fits a full IPv6 address'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            // Phase 3 repositories will look audit history up by "what
            // happened to this entity" and "what did this user do" - both
            // directions get their own index rather than guessing which
            // one a composite should lead with, since this is a brand new
            // table with no real query traffic yet to measure (unlike the
            // legacy-table indexes in the next migration, which ARE sized
            // to observed query patterns).
            ->addIndex(['entity', 'entity_id'])
            ->addIndex(['user_id'])
            ->addIndex(['created_at'])
            ->create();
    }
}
