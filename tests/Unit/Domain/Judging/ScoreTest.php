<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\Judging\ValueObject\Score;
use Bcoem\Domain\Judging\ValueObject\TableId;
use Bcoem\Domain\Entry\ValueObject\EntryId;

class ScoreTest extends TestCase
{
    public function test_create_score_with_valid_data(): void
    {
        $score = new Score(
            id: 1,
            entryId: new EntryId(100),
            brewerId: 50,
            tableId: new TableId(1),
            score: 28.5,
            place: '1',
            scoreType: 'regular',
            miniBos: 0,
            version: 1
        );

        $this->assertEquals(1, $score->id());
        $this->assertEquals(100, $score->entryId()->value());
        $this->assertEquals(50, $score->brewerId());
        $this->assertEquals(1, $score->tableId()->value());
        $this->assertEquals(28.5, $score->score());
        $this->assertEquals('1', $score->place());
        $this->assertSame('regular', $score->scoreType());
        $this->assertEquals(0, $score->miniBos());
        $this->assertEquals(1, $score->version());
    }

    public function test_score_must_be_between_0_and_50(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Score(
            id: 1,
            entryId: new EntryId(100),
            brewerId: 50,
            tableId: new TableId(1),
            score: 51,
            place: '1',
            scoreType: 'regular',
            miniBos: 0,
            version: 1
        );
    }

    public function test_negative_score_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Score(
            id: 1,
            entryId: new EntryId(100),
            brewerId: 50,
            tableId: new TableId(1),
            score: -1,
            place: '1',
            scoreType: 'regular',
            miniBos: 0,
            version: 1
        );
    }

    public function test_version_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Score(
            id: 1,
            entryId: new EntryId(100),
            brewerId: 50,
            tableId: new TableId(1),
            score: 25,
            place: '1',
            scoreType: 'regular',
            miniBos: 0,
            version: 0
        );
    }

    public function test_invalid_score_type_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Score(
            id: 1,
            entryId: new EntryId(100),
            brewerId: 50,
            tableId: new TableId(1),
            score: 25,
            place: '1',
            scoreType: 'invalid',
            miniBos: 0,
            version: 1
        );
    }

    public function test_equals_same_score(): void
    {
        $s1 = new Score(1, new EntryId(100), 50, new TableId(1), 28.5, '1', 'regular', 0, 1);
        $s2 = new Score(1, new EntryId(100), 50, new TableId(1), 28.5, '1', 'regular', 0, 1);
        $this->assertTrue($s1->equals($s2));
    }

    public function test_equals_different_version(): void
    {
        $s1 = new Score(1, new EntryId(100), 50, new TableId(1), 28.5, '1', 'regular', 0, 1);
        $s2 = new Score(1, new EntryId(100), 50, new TableId(1), 28.5, '1', 'regular', 0, 2);
        $this->assertFalse($s1->equals($s2));
    }
}
