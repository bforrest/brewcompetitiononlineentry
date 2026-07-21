<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Entry\Command\CreateEntryCommand;
use Bcoem\Domain\Entry\Command\UpdateEntryCommand;
use Bcoem\Domain\Entry\Exception\EntryWindowClosedException;
use Bcoem\Domain\Entry\Exception\InvalidStyleSelectionException;
use Bcoem\Domain\Entry\Repository\EntryRepository;
use Bcoem\Domain\Entry\Service\EntryValidationService;
use Bcoem\Domain\Entry\Service\StyleService;
use Bcoem\Security\Identity;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\ValidatorBuilder;

class EntryValidationServiceTest extends TestCase
{
    private EntryValidationService $validationService;
    private EntryRepository $repository;
    private StyleService $styleService;

    protected function setUp(): void
    {
        unset($_SESSION['entry_window_open'], $_SESSION['prefsProEdition'], $_SESSION['prefsUserSubCatLimit']);

        $this->repository = $this->createMock(EntryRepository::class);
        $this->styleService = $this->createMock(StyleService::class);
        $this->styleService->method('isStyleAvailable')->willReturn(true);

        $this->validationService = new EntryValidationService(
            $this->repository,
            self::createValidator(),
            $this->styleService
        );
    }

    protected function tearDown(): void
    {
        unset($_SESSION['entry_window_open'], $_SESSION['prefsProEdition'], $_SESSION['prefsUserSubCatLimit']);
    }

    public function test_validate_create_with_closed_window_throws_exception(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'user@example.com']);
        $command = new CreateEntryCommand([
            'brewName' => 'Test Beer',
            'brewCategorySort' => '1',
            'brewSubCategory' => 'A',
        ]);

        // $_SESSION['entry_window_open'] deliberately unset => closed by default
        $this->expectException(EntryWindowClosedException::class);

        $this->validationService->validateCreate($command, $identity);
    }

    public function test_validate_create_with_invalid_style_throws_exception(): void
    {
        $_SESSION['entry_window_open'] = 1;

        $styleService = $this->createMock(StyleService::class);
        $styleService->method('isStyleAvailable')->willReturn(false);
        $validationService = new EntryValidationService($this->repository, self::createValidator(), $styleService);

        $identity = Identity::fromSession(['loginUsername' => 'user@example.com']);
        $command = new CreateEntryCommand([
            'brewName' => 'Test Beer',
            'brewCategorySort' => 'INVALID',
            'brewSubCategory' => 'A',
        ]);

        $this->expectException(InvalidStyleSelectionException::class);

        $validationService->validateCreate($command, $identity);
    }

    public function test_validate_update_with_closed_window_throws_exception(): void
    {
        $identity = Identity::fromSession(['loginUsername' => 'user@example.com']);
        $command = new UpdateEntryCommand([
            'id' => 123,
            'brewName' => 'Updated Beer',
            'brewCategorySort' => '1',
            'brewSubCategory' => 'A',
        ]);

        // $_SESSION['entry_window_open'] deliberately unset => closed by default
        $this->expectException(EntryWindowClosedException::class);

        $this->validationService->validateUpdate($command, $identity);
    }

    public function test_validate_update_succeeds_with_open_window(): void
    {
        $_SESSION['entry_window_open'] = 1;

        $identity = Identity::fromSession(['loginUsername' => 'user@example.com']);
        $command = new UpdateEntryCommand([
            'id' => 123,
            'brewName' => 'Updated Beer',
            'brewCategorySort' => '1',
            'brewSubCategory' => 'A',
        ]);

        $this->validationService->validateUpdate($command, $identity);
        $this->addToAssertionCount(1); // no exception thrown = success
    }

    private static function createValidator(): \Symfony\Component\Validator\Validator\ValidatorInterface
    {
        $builder = new ValidatorBuilder();
        return $builder->enableAttributeMapping(true)->getValidator();
    }
}
