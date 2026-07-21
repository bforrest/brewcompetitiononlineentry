<?php
declare(strict_types=1);

namespace Tests\Unit\Domain\AdminPreferences\Service;

use Bcoem\Domain\AdminPreferences\AdminPreferences;
use Bcoem\Domain\AdminPreferences\Command\UpdateJudgingConfigCommand;
use Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException;
use Bcoem\Domain\AdminPreferences\Exception\PreferencesLockedForCompetitionException;
use Bcoem\Domain\AdminPreferences\Repository\AdminPreferencesRepository;
use Bcoem\Domain\AdminPreferences\Service\PreferencesValidationService;
use Bcoem\Domain\AdminPreferences\Service\UpdateJudgingConfigService;
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
 * UpdateJudgingConfigServiceTest verifies UpdateJudgingConfigService.execute().
 */
final class UpdateJudgingConfigServiceTest extends TestCase
{
    private UpdateJudgingConfigService $service;
    private AdminPreferencesRepository|MockObject $repository;
    private PreferencesValidationService $validation;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $builder = new \Symfony\Component\Validator\ValidatorBuilder();
        $this->validator = $builder->enableAttributeMapping(true)->getValidator();
        $this->validation = new PreferencesValidationService($this->validator);
        $this->repository = $this->createMock(AdminPreferencesRepository::class);
        $this->service = new UpdateJudgingConfigService($this->repository, $this->validation);
    }

    public function test_valid_judging_config_update_succeeds(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Planning);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new UpdateJudgingConfigCommand(
            isQueued: false,
            maxFlightEntries: 10,
            maxBosPerStyle: 5,
            maxRounds: 2
        );
        $admin = Identity::fromSession(['loginUsername' => 'admin', 'userLevel' => '1']);

        $result = $this->service->execute($command, $admin);

        self::assertNotNull($result);
        self::assertFalse($result->judgingConfig()->isQueued());
        self::assertEquals(10, $result->judgingConfig()->maxFlightEntries());
        self::assertEquals(5, $result->judgingConfig()->maxBosPerStyle());
        self::assertEquals(2, $result->judgingConfig()->maxRounds());
    }

    public function test_invalid_config_throws_exception(): void
    {
        // Range violation is caught by command validation, before the
        // aggregate is fetched - so getById() is never called here.
        $this->repository->expects($this->never())->method('getById');

        // Invalid: maxFlightEntries = 0
        $command = new UpdateJudgingConfigCommand(
            isQueued: true,
            maxFlightEntries: 0,
            maxBosPerStyle: 7,
            maxRounds: 3
        );
        $admin = Identity::fromSession(['loginUsername' => 'admin', 'userLevel' => '1']);

        $this->expectException(InvalidConstraintException::class);
        $this->service->execute($command, $admin);
    }

    public function test_locked_state_prevents_change(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Active);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new UpdateJudgingConfigCommand(true, 12, 7, 3);
        $admin = Identity::fromSession(['loginUsername' => 'admin', 'userLevel' => '1']);

        $this->expectException(PreferencesLockedForCompetitionException::class);
        $this->service->execute($command, $admin);
    }

    public function test_event_recorded_on_change(): void
    {
        $preferences = $this->createMockPreferences(CompetitionState::Planning);
        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new UpdateJudgingConfigCommand(true, 15, 8, 4);
        $admin = Identity::fromSession(['loginUsername' => 'admin', 'userLevel' => '1']);

        $result = $this->service->execute($command, $admin);

        $events = $result->events();
        self::assertGreaterThan(0, count($events));
        self::assertEquals('judging_config_updated', $events[0]['action']);
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
