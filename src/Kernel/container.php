<?php

declare(strict_types=1);

use DI\ContainerBuilder;

/**
 * PHP-DI container. Legacy globals (mysqli connection, table prefix) are
 * NOT wired here - src/Legacy/ reads them directly from $GLOBALS, exactly
 * as legacy code always has. Only genuinely new (Phase 3+) services get
 * container entries.
 */
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    // Populated starting Phase 3 (Bcoem\Database\Connection, Bcoem\Audit\AuditLogger, ...).
]);

return $containerBuilder->build();
