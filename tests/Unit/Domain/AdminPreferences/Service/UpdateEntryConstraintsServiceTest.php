<?php
declare(strict_types=1);

namespace Tests\Unit\Domain\AdminPreferences\Service;

use Bcoem\Domain\AdminPreferences\AdminPreferences;
use Bcoem\Domain\AdminPreferences\Command\UpdateEntryConstraintsCommand;
use Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException;
use Bcoem\Domain\AdminPreferences\Exception\PreferencesLockedForCompetitionException;
use Bcoem\Domain\AdminPreferences\Repository\AdminPreferencesRepository;
use Bcoem\Domain\AdminPreferences\Service\PreferencesValidationService;
use Bcoem\Domain\AdminPreferences\Service\UpdateEntryConstraintsService;
use Bcoem\Domain\AdminPreferences\ValueObject\CompetitionState;
use Bcoem\Domain\AdminPreferences\ValueObject\EntryConstraints;
use Bcoem\Domain\AdminPreferences\ValueObject\JudgingConfiguration;
use Bcoem\Domain\AdminPreferences\ValueObject\PreferencesId;
use Bcoem\Domain\AdminPreferences\ValueObject\StyleSet;
use Bcoem\Domain\AdminPreferences\ValueObject\StyleSetConfiguration;
use Bcoem\Security\Identity;
use DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * UpdateEntryConstraintsServiceTest verifies UpdateEntryConstraintsService.execute().
 */
final class UpdateEntryConstraintsServiceTest extends TestCase
{
    private UpdateEntryConstraintsService $service;
    private AdminPreferencesRepository|MockObject $repository;
    private PreferencesValidationService $validation;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $builder = new \Symfony\Component\Validator\ValidatorBuilder();
        $this->validator = $builder->enableAttributeMapping(true)->getValidator();
        $this->validation = new PreferencesValidationService($this->validator);
        $this->repository = $this->createMock(AdminPreferencesRepository::class);
        $this->service = new UpdateEntryConstraintsService($this->repository, $this->validation);
    }

    public function test_valid_constraint_update_succeeds(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Planning);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new UpdateEntryConstraintsCommand(10, [1 => 3, 2 => 5]);
        $admin = new Identity(true, 'admin', \Bcoem\Security\Role::Admin);

        $result = $this->service->execute($command, $admin);

        self::assertNotNull($result);
        self::assertEquals(10, $result->entryConstraints()->globalEntryLimit());
        self::assertEquals([1 => 3, 2 => 5], $result->entryConstraints()->perStyleLimits());
    }

    public function test_mutually_exclusive_constraint_rejected(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Planning);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        // Both perStyleLimits and perTableLimit set - should fail
        $command = new UpdateEntryConstraintsCommand(10, [1 => 3], 5);
        $admin = new Identity(true, 'admin', \Bcoem\Security\Role::Admin);

        $this->expectException(InvalidConstraintException::class);
        $this->service->execute($command, $admin);
    }

    public function test_range_validation_enforced(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Planning);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        // globalEntryLimit < 1 should fail validation
        $command = new UpdateEntryConstraintsCommand(0);
        $admin = new Identity(true, 'admin', \Bcoem\Security\Role::Admin);

        $this->expectException(InvalidConstraintException::class);
        $this->service->execute($command, $admin);
    }

    public function test_event_recorded(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Planning);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new UpdateEntryConstraintsCommand(15);
        $admin = new Identity(true, 'admin', \Bcoem\Security\Role::Admin);

        $result = $this->service->execute($command, $admin);

        $events = $result->events();
        self::assertGreaterThan(0, count($events));
        self::assertEquals('entry_constraints_updated', $events[0]['action']);
    }

    public function test_locked_state_prevents_change(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Active);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new UpdateEntryConstraintsCommand(15);
        $admin = new Identity(true, 'admin', \Bcoem\Security\Role::Admin);

        $this->expectException(PreferencesLockedForCompetitionException::class);
        $this->service->execute($command, $admin);
    }

    public function test_valid_per_table_limit(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Planning);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new UpdateEntryConstraintsCommand(10, [], 3);
        $admin = new Identity(true, 'admin', \Bcoem\Security\Role::Admin);

        $result = $this->service->execute($command, $admin);

        self::assertEquals(3, $result->entryConstraints()->perTableLimit());
        self::assertEmpty($result->entryConstraints()->perStyleLimits());
    }

    private function createMockPreferences(CompetitionState $state): AdminPreferences
    {
        return new AdminPreferences(
            new PreferencesId(1),
            new StyleSetConfiguration(StyleSet::BJCP2021, [], []),
            new EntryConstraints(5),
            new JudgingConfiguration(true, 12, 7, 3),
            $state,
            new DateTime()
        );
    }
}
