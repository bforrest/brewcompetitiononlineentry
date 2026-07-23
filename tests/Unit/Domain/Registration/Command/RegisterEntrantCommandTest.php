<?php
declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\Registration\Command;

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Registration\Command\RegisterEntrantCommand;

class RegisterEntrantCommandTest extends TestCase
{
    private function baseData(): array
    {
        return [
            'user_name' => 'Entrant@Example.com',
            'password' => 'Sup3rSecret!',
            'userQuestion' => 'What is your favorite hop?',
            'userQuestionAnswer' => 'Citra',
            'brewerFirstName' => 'Jane',
            'brewerLastName' => 'Brewer',
            'brewerAddress' => '1 Test Street',
            'brewerCity' => 'Testville',
            'brewerStateUS' => 'TX',
            'brewerZip' => '75001',
            'brewerCountry' => 'United States',
            'brewerPhone1' => '555-555-0100',
        ];
    }

    public function test_hydrates_required_fields(): void
    {
        $cmd = new RegisterEntrantCommand($this->baseData());

        $this->assertSame('Entrant@Example.com', $cmd->userName);
        $this->assertSame('Sup3rSecret!', $cmd->password);
        $this->assertSame('Jane', $cmd->brewerFirstName);
        $this->assertSame('Brewer', $cmd->brewerLastName);
        $this->assertSame('N', $cmd->brewerJudge);
        $this->assertSame('N', $cmd->brewerSteward);
        $this->assertSame('', $cmd->brewerStaff);
        $this->assertSame('0', $cmd->brewerDropOff);
        $this->assertSame('Y', $cmd->brewerJudgeWaiver);
    }

    public function test_judge_and_steward_opt_ins_default_to_no(): void
    {
        $cmd = new RegisterEntrantCommand($this->baseData() + ['brewerJudge' => 'Y']);
        $this->assertSame('Y', $cmd->brewerJudge);
        $this->assertSame('N', $cmd->brewerSteward);
    }

    public function test_hydrates_standard_entrant_delivery_and_volunteer_values(): void
    {
        $cmd = new RegisterEntrantCommand($this->baseData() + [
            'brewerDropOff' => '999',
            'brewerJudge' => 'Y',
            'brewerSteward' => 'Y',
            'brewerStaff' => 'Y',
        ]);

        $this->assertSame('999', $cmd->brewerDropOff);
        $this->assertSame('Y', $cmd->brewerJudge);
        $this->assertSame('Y', $cmd->brewerSteward);
        $this->assertSame('Y', $cmd->brewerStaff);
    }

    public function test_uses_legacy_waiver_value_regardless_of_submitted_value(): void
    {
        foreach (['N', ''] as $submittedWaiver) {
            $cmd = new RegisterEntrantCommand($this->baseData() + ['brewerJudgeWaiver' => $submittedWaiver]);

            $this->assertSame('Y', $cmd->brewerJudgeWaiver);
        }
    }

    public function test_missing_required_field_throws(): void
    {
        $data = $this->baseData();
        unset($data['user_name']);

        $this->expectException(\InvalidArgumentException::class);
        new RegisterEntrantCommand($data);
    }

    public function test_preserves_array_value_for_judge_location(): void
    {
        $locations = ['Y-5', 'N-6'];
        $cmd = new RegisterEntrantCommand($this->baseData() + ['brewerJudgeLocation' => $locations]);
        $this->assertSame($locations, $cmd->brewerJudgeLocation);
        $this->assertIsArray($cmd->brewerJudgeLocation);
    }
}
