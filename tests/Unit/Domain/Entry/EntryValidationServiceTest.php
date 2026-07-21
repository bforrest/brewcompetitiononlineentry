<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Entry\Command\CreateEntryCommand;
use Bcoem\Domain\Entry\Command\UpdateEntryCommand;
use Bcoem\Domain\Entry\Exception\EntryWindowClosedException;
use Bcoem\Domain\Entry\Exception\EntryLimitReachedException;
use Bcoem\Domain\Entry\Exception\InvalidStyleSelectionException;
use Bcoem\Domain\Entry\Exception\BreweryNameRequiredException;
use Bcoem\Domain\Entry\Repository\EntryRepository;
use Bcoem\Domain\Entry\Service\EntryValidationService;
use Bcoem\Domain\Entry\Adapter\LegacyQueryAdapter;
use Bcoem\Security\Identity;
use Bcoem\Security\Role;

class EntryValidationServiceTest extends TestCase
{
    private EntryValidationService $validationService;
    private EntryRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntryRepository::class);
        $this->validationService = new EntryValidationService($this->repository);
    }

    public function test_validate_create_with_closed_window_throws_exception(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'user@example.com']);
        $command = new CreateEntryCommand([
            'brewName' => 'Test Beer',
            'brewCategorySort' => '1',
            'brewSubCategory' => 'A',
        ]);

        $this->expectException(EntryWindowClosedException::class);

        // Simulate closed window by returning false from window check
        // The method would need to call a helper to determine window status
        // For now, we test the basic flow
        $this->validationService->validateCreate($command, $identity);
    }

    public function test_validate_create_with_invalid_style_throws_exception(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'user@example.com']);
        $command = new CreateEntryCommand([
            'brewName' => 'Test Beer',
            'brewCategorySort' => 'INVALID',
            'brewSubCategory' => 'A',
        ]);

        // The validation service checks style availability
        $this->expectException(InvalidStyleSelectionException::class);

        $this->validationService->validateCreate($command, $identity);
    }

    public function test_validate_update_calls_repository_count(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'user@example.com']);
        $command = new UpdateEntryCommand([
            'id' => 123,
            'brewName' => 'Updated Beer',
            'brewCategorySort' => '1',
            'brewSubCategory' => 'A',
        ]);

        $this->repository->expects($this->once())
            ->method('countByBrewerId');

        $this->validationService->validateUpdate($command, $identity);
    }

    public function test_validate_delete_calls_repository(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'user@example.com']);
        $brewerId = 1;

        $this->repository->expects($this->once())
            ->method('countByBrewerId');

        $this->validationService->validateDelete($brewerId, $identity);
    }
}
