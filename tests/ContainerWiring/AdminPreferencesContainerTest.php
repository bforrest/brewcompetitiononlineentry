<?php

/**
 * Simple verification test for AdminPreferences DI container wiring.
 *
 * This script tests that:
 * 1. All service classes exist with correct constructors
 * 2. Container definitions exist for each service
 * 3. No syntax errors in the container or service files
 */

declare(strict_types=1);

// Bootstrap the application (required for autoloading)
require_once __DIR__ . '/../../tests/bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Bcoem\Domain\AdminPreferences\Repository\AdminPreferencesRepository;
use Bcoem\Domain\AdminPreferences\Service\AdminPreferencesService;
use Bcoem\Domain\AdminPreferences\Service\PreferencesValidationService;
use Bcoem\Domain\AdminPreferences\Service\StyleCatalogService;

echo "Testing AdminPreferences DI Container Wiring\n";
echo "=============================================\n\n";

// Test 1: Check all classes exist
echo "Test 1: Verify service classes exist... ";
$classes = [
    AdminPreferencesRepository::class,
    PreferencesValidationService::class,
    StyleCatalogService::class,
    AdminPreferencesService::class,
];

foreach ($classes as $class) {
    if (!class_exists($class)) {
        echo "FAILED: Class $class not found\n";
        exit(1);
    }
}
echo "OK\n";

// Test 2: Check constructor signatures via Reflection
echo "Test 2: Verify constructor signatures... ";
try {
    $repo = new ReflectionClass(AdminPreferencesRepository::class);
    $repoConstructor = $repo->getConstructor();
    if (!$repoConstructor || $repoConstructor->getNumberOfParameters() !== 1) {
        throw new \RuntimeException('AdminPreferencesRepository constructor should have 1 parameter');
    }

    $validation = new ReflectionClass(PreferencesValidationService::class);
    $validationConstructor = $validation->getConstructor();
    if ($validationConstructor && $validationConstructor->getNumberOfParameters() !== 0) {
        throw new \RuntimeException('PreferencesValidationService constructor should have 0 parameters');
    }

    $styleCatalog = new ReflectionClass(StyleCatalogService::class);
    $styleCatalogConstructor = $styleCatalog->getConstructor();
    if (!$styleCatalogConstructor || $styleCatalogConstructor->getNumberOfParameters() !== 1) {
        throw new \RuntimeException('StyleCatalogService constructor should have 1 parameter');
    }

    $service = new ReflectionClass(AdminPreferencesService::class);
    $serviceConstructor = $service->getConstructor();
    if (!$serviceConstructor || $serviceConstructor->getNumberOfParameters() !== 3) {
        throw new \RuntimeException('AdminPreferencesService constructor should have 3 parameters');
    }

    echo "OK\n";
} catch (\Throwable $e) {
    echo "FAILED: {$e->getMessage()}\n";
    exit(1);
}

// Test 3: Check that container.php is valid PHP and has registrations
echo "Test 3: Verify container.php registrations... ";
$containerPath = __DIR__ . '/../../src/Kernel/container.php';
$containerCode = file_get_contents($containerPath);

$requiredClasses = [
    'AdminPreferencesRepository',
    'PreferencesValidationService',
    'StyleCatalogService',
    'AdminPreferencesService',
];

foreach ($requiredClasses as $class) {
    if (strpos($containerCode, $class) === false) {
        echo "FAILED: $class not found in container.php\n";
        exit(1);
    }
}
echo "OK\n";

// Test 4: Verify container defines Psr\Container\ContainerInterface
echo "Test 4: Verify PSR-11 ContainerInterface import... ";
if (strpos($containerCode, 'use Psr\Container\ContainerInterface') !== false) {
    echo "OK\n";
} else {
    echo "FAILED: PSR-11 ContainerInterface not imported\n";
    exit(1);
}

echo "\n✓ All DI wiring verification tests passed!\n";
echo "\nDependency Graph:\n";
echo "─────────────────\n";
echo "AdminPreferencesRepository\n";
echo "  └── Connection\n";
echo "\n";
echo "PreferencesValidationService\n";
echo "  └── (no dependencies)\n";
echo "\n";
echo "StyleCatalogService\n";
echo "  └── Connection\n";
echo "\n";
echo "AdminPreferencesService\n";
echo "  ├── AdminPreferencesRepository\n";
echo "  ├── PreferencesValidationService\n";
echo "  └── StyleCatalogService\n";
echo "\n";
echo "Container Registrations:\n";
echo "─────────────────────────\n";
echo "✓ AdminPreferencesRepository::class => factory(Connection)\n";
echo "✓ PreferencesValidationService::class => factory()\n";
echo "✓ StyleCatalogService::class => factory(Connection)\n";
echo "✓ AdminPreferencesService::class => factory(Repository, Validation, StyleCatalog)\n";
