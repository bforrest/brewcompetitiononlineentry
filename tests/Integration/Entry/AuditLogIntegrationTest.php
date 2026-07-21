<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration;

use Bcoem\Domain\Entry\Service\AuditLogger;
use Bcoem\Database\Connection;
use Bcoem\Security\Identity;

class AuditLogIntegrationTest extends IntegrationTestCase
{
    private AuditLogger $auditLogger;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new Connection(self::$conn);
        $this->auditLogger = new AuditLogger($this->connection);
    }

    public function test_record_audit_log_entry(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'user@test.example']);

        $this->auditLogger->record(
            identity: $identity,
            action: 'create',
            entity: 'Entry',
            entityId: 123,
            beforeJson: json_encode([]),
            afterJson: json_encode(['name' => 'Test Beer']),
            ipAddress: '127.0.0.1'
        );

        $auditRows = $this->select('audit_log', 'entity = "Entry" AND entity_id = 123');
        $this->assertCount(1, $auditRows);
        $this->assertSame('create', $auditRows[0]['action']);
    }

    public function test_audit_log_stores_before_and_after_json(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'user@test.example']);
        $before = ['name' => 'Old Name'];
        $after = ['name' => 'New Name'];

        $this->auditLogger->record(
            identity: $identity,
            action: 'update',
            entity: 'Entry',
            entityId: 456,
            beforeJson: json_encode($before),
            afterJson: json_encode($after),
            ipAddress: '127.0.0.1'
        );

        $auditRows = $this->select('audit_log', 'entity = "Entry" AND entity_id = 456');
        $this->assertCount(1, $auditRows);

        $stored = $auditRows[0];
        $this->assertJsonStringEqualsJsonString(json_encode($before), $stored['before_json']);
        $this->assertJsonStringEqualsJsonString(json_encode($after), $stored['after_json']);
    }

    public function test_audit_log_stores_ip_address(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'user@test.example']);

        $this->auditLogger->record(
            identity: $identity,
            action: 'delete',
            entity: 'Entry',
            entityId: 789,
            beforeJson: json_encode(['name' => 'To Delete']),
            afterJson: json_encode([]),
            ipAddress: '192.168.1.1'
        );

        $auditRows = $this->select('audit_log', 'entity = "Entry" AND entity_id = 789');
        $this->assertCount(1, $auditRows);
        $this->assertSame('192.168.1.1', $auditRows[0]['ip']);
    }

    public function test_audit_log_stores_timestamp(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'user@test.example']);
        $beforeRecord = date('Y-m-d H:i:s');

        $this->auditLogger->record(
            identity: $identity,
            action: 'create',
            entity: 'Entry',
            entityId: 999,
            beforeJson: json_encode([]),
            afterJson: json_encode(['id' => 999]),
            ipAddress: '127.0.0.1'
        );

        $afterRecord = date('Y-m-d H:i:s');

        $auditRows = $this->select('audit_log', 'entity = "Entry" AND entity_id = 999');
        $this->assertCount(1, $auditRows);

        $recordedTime = strtotime($auditRows[0]['created_at']);
        $this->assertGreaterThanOrEqual(strtotime($beforeRecord), $recordedTime);
        $this->assertLessThanOrEqual(strtotime($afterRecord), $recordedTime);
    }
}
