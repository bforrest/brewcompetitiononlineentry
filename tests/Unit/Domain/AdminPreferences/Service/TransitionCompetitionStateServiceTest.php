<?php
declare(strict_types=1);

namespace Tests\Unit\Domain\AdminPreferences\Service;

use Bcoem\Domain\AdminPreferences\AdminPreferences;
use Bcoem\Domain\AdminPreferences\Command\TransitionCompetitionStateCommand;
use Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException;
use Bcoem\Domain\AdminPreferences\Repository\AdminPreferencesRepository;
use Bcoem\Domain\AdminPreferences\Service\PreferencesValidationService;
use Bcoem\Domain\AdminPreferences\Service\TransitionCompetitionStateService;
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
 * TransitionCompetitionStateServiceTest verifies state transitions.
 */
final class TransitionCompetitionStateServiceTest extends TestCase
{
    private TransitionCompetitionStateService $service;
    private AdminPreferencesRepository|MockObject $repository;
    private PreferencesValidationService $validation;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $builder = new \Symfony\Component\Validator\ValidatorBuilder();
        $this->validator = $builder->enableAttributeMapping(true)->getValidator();
        $this->validation = new PreferencesValidationService($this->validator);
        $this->repository = $this->createMock(AdminPreferencesRepository::class);
        $this->service = new TransitionCompetitionStateService($this->repository, $this->validation);
    }

    public function test_valid_transition_planning_to_active(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Planning);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new TransitionCompetitionStateCommand('active');
        $admin = new Identity(true, 'admin', \Bcoem\Security\Role::Admin);

        $result = $this->service->execute($command, $admin);

        self::assertEquals(CompetitionState::Active, $result->competitionState());
        self::assertFalse($result->canChangePreferences());
    }

    public function test_valid_transition_active_to_closed(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Active);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new TransitionCompetitionStateCommand('closed');
        $admin = new Identity(true, 'admin', \Bcoem\Security\Role::Admin);

        $result = $this->service->execute($command, $admin);

        self::assertEquals(CompetitionState::Closed, $result->competitionState());
    }

    public function test_valid_transition_active_to_planning_revert(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Active);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new TransitionCompetitionStateCommand('planning');
        $admin = new Identity(true, 'admin', \Bcoem\Security\Role::Admin);

        $result = $this->service->execute($command, $admin);

        self::assertEquals(CompetitionState::Planning, $result->competitionState());
        self::assertTrue($result->canChangePreferences());
    }

    public function test_closed_state_is_terminal(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Closed);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new TransitionCompetitionStateCommand('planning');
        $admin = new Identity(true, 'admin', \Bcoem\Security\Role::Admin);

        $this->expectException(InvalidConstraintException::class);
        $this->service->execute($command, $admin);
    }

    public function test_invalid_transition_rejected(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Planning);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        // Closed is terminal, cannot transition to planning
        $preferences = $this->createMockPreferences(CompetitionState::Closed);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new TransitionCompetitionStateCommand('planning');
        $admin = new Identity(true, 'admin', \Bcoem\Security\Role::Admin);

        $this->expectException(InvalidConstraintException::class);
        $this->service->execute($command, $admin);
    }

    public function test_event_recorded_on_transition(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Planning);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new TransitionCompetitionStateCommand('active');
        $admin = new Identity(true, 'admin', \Bcoem\Security\Role::Admin);

        $result = $this->service->execute($command, $admin);

        $events = $result->events();
        self::assertGreaterThan(0, count($events));
        self::assertEquals('state_changed', $events[0]['action']);
    }

    public function test_permissions_changed_after_transition(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Planning);
        self::assertTrue($preferences->canChangePreferences());

        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new TransitionCompetitionStateCommand('active');
        $admin = new Identity(true, 'admin', \Bcoem\Security\Role::Admin);

        $result = $this->service->execute($command, $admin);

        self::assertFalse($result->canChangePreferences());
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
