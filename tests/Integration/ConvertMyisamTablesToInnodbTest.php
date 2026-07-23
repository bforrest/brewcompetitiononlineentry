<?php
declare(strict_types=1);

namespace BCOEM\Tests\Integration;

require_once LIB . 'update.lib.php';

class ConvertMyisamTablesToInnodbTest extends IntegrationTestCase
{
    public function testConvertsAMyisamTableToInnodb(): void
    {
        $pfx = self::$pfx;
        self::$conn->query("ALTER TABLE `{$pfx}sponsors` ENGINE=MyISAM");
        $failures = convert_myisam_tables_to_innodb(self::$conn, $pfx);
        $this->assertArrayNotHasKey('sponsors', $failures);
        $this->assertSame('InnoDB', $this->engineOf('sponsors'));
    }

    public function testAlreadyInnodbTableIsLeftAloneWithoutError(): void
    {
        $pfx = self::$pfx;
        $this->assertSame('InnoDB', $this->engineOf('style_types'));
        $failures = convert_myisam_tables_to_innodb(self::$conn, $pfx);
        $this->assertArrayNotHasKey('style_types', $failures);
        $this->assertSame('InnoDB', $this->engineOf('style_types'));
    }

    public function testUnconvertibleTablesAreCapturedNotThrown(): void
    {
        $failures = convert_myisam_tables_to_innodb(self::$conn, 'nonexistent_prefix_');
        $this->assertCount(24, $failures);
        $this->assertArrayHasKey('users', $failures);
        $this->assertNotSame('', $failures['users']);
    }

    private function engineOf(string $table): ?string
    {
        $pfx = self::$pfx;
        $result = self::$conn->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . self::$db . "' AND TABLE_NAME = '{$pfx}{$table}'");
        $row = $result->fetch_assoc();
        return $row['ENGINE'] ?? null;
    }
}
