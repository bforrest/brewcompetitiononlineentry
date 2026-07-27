<?php

declare(strict_types=1);

namespace BCOEM\Tests\Unit\Kernel;

use Bcoem\Database\Connection;
use Bcoem\Domain\LandingPage\Repository\LandingPageRepository;
use PHPUnit\Framework\TestCase;

final class LandingPageContainerTest extends TestCase
{
    public function test_container_injects_its_configured_table_prefix_into_the_repository(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('selectOne')
            ->with(
                self::stringContains('FROM fixture_contest_info'),
                [1],
            )
            ->willReturn(null);

        $container = require ROOT . 'src/Kernel/container.php';
        $container->set(Connection::class, $connection);
        $container->set('database.table_prefix', 'fixture_');

        $repository = $container->get(LandingPageRepository::class);

        self::assertInstanceOf(LandingPageRepository::class, $repository);
        self::assertNull($repository->contestOverview());
    }
}
