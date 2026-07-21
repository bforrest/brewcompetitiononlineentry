<?php
declare(strict_types=1);

namespace Tests\Unit\Domain\AdminPreferences\Service;

use Bcoem\Domain\AdminPreferences\AdminPreferences;
use Bcoem\Domain\AdminPreferences\Command\UpdateStyleSetCommand;
use Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException;
use Bcoem\Domain\AdminPreferences\Exception\PreferencesLockedForCompetitionException;
use Bcoem\Domain\AdminPreferences\Repository\AdminPreferencesRepository;
use Bcoem\Domain\AdminPreferences\Service\PreferencesValidationService;
use Bcoem\Domain\AdminPreferences\Service\UpdateStyleSetService;
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
 * UpdateStyleSetServiceTest verifies UpdateStyleSetService.execute().
 */
final class UpdateStyleSetServiceTest extends TestCase
{
    private UpdateStyleSetService $service;
    private AdminPreferencesRepository|MockObject $repository;
    private PreferencesValidationService $validation;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $builder = new \Symfony\Component\Validator\ValidatorBuilder();
        $this->validator = $builder->enableAttributeMapping(true)->getValidator();
        $this->validation = new PreferencesValidationService($this->validator);
        $this->repository = $this->createMock(AdminPreferencesRepository::class);
        $this->service = new UpdateStyleSetService($this->repository, $this->validation);
    }

    public function test_valid_style_set_change_succeeds(): void
    {
        $currentConfig = new StyleSetConfiguration(StyleSet::BJCP2021, [], []);
        $preferences = new AdminPreferences(
            new PreferencesId(1),
            $currentConfig,
            new EntryConstraints(5),
            new JudgingConfiguration(true, 12, 7, 3),
            CompetitionState::Planning,
            new DateTime()
        );

        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new UpdateStyleSetCommand('BJCP2025', [1, 2, 3]);
        $admin = Identity::fromSession(['loginUsername' => 'admin', 'userLevel' => '1']);

        $result = $this->service->execute($command, $admin);

        self::assertNotNull($result);
        self::assertEquals(StyleSet::BJCP2025, $result->styleSetConfig()->styleSet());
        self::assertEquals([1, 2, 3], $result->styleSetConfig()->allowedStyleIds());
    }

    public function test_invalid_style_set_throws_exception(): void
    {
        // Invalid enum value is rejected during command validation, before the
        // aggregate is ever fetched - so getById() is never called here.
        $this->repository->expects($this->never())->method('getById');

        $command = new UpdateStyleSetCommand('INVALID_SET');
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

        $command = new UpdateStyleSetCommand('BJCP2025');
        $admin = Identity::fromSession(['loginUsername' => 'admin', 'userLevel' => '1']);

        $this->expectException(PreferencesLockedForCompetitionException::class);
        $this->service->execute($command, $admin);
    }

    public function test_event_recorded_on_change(): void
    {
        $currentConfig = new StyleSetConfiguration(StyleSet::BJCP2021, [], []);
        $preferences = new AdminPreferences(
            new PreferencesId(1),
            $currentConfig,
            new EntryConstraints(5),
            new JudgingConfiguration(true, 12, 7, 3),
            CompetitionState::Planning,
            new DateTime()
        );

        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new UpdateStyleSetCommand('BJCP2025');
        $admin = Identity::fromSession(['loginUsername' => 'admin', 'userLevel' => '1']);

        $result = $this->service->execute($command, $admin);

        $events = $result->events();
        self::assertGreaterThan(0, count($events));
        self::assertEquals('style_set_updated', $events[0]['action']);
    }

    public function test_custom_exceptions_preserved(): void
    {
        $currentConfig = new StyleSetConfiguration(StyleSet::BJCP2021, [], []);
        $preferences = new AdminPreferences(
            new PreferencesId(1),
            $currentConfig,
            new EntryConstraints(5),
            new JudgingConfiguration(true, 12, 7, 3),
            CompetitionState::Planning,
            new DateTime()
        );

        $this->repository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($preferences);

        $command = new UpdateStyleSetCommand('BJCP2025', [], [100, 101]);
        $admin = Identity::fromSession(['loginUsername' => 'admin', 'userLevel' => '1']);

        $result = $this->service->execute($command, $admin);

        self::assertEquals([100, 101], $result->styleSetConfig()->customExceptions());
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
