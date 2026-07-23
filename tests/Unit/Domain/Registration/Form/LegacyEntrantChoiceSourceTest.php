<?php

declare(strict_types=1);

namespace BCOEM\Tests\Unit\Domain\Registration\Form;

use Bcoem\Domain\Registration\Form\LegacyEntrantChoiceSource;
use PHPUnit\Framework\TestCase;

final class LegacyEntrantChoiceSourceTest extends TestCase
{
    public function test_reads_the_legacy_localized_security_question_bank(): void
    {
        $questions = (new LegacyEntrantChoiceSource())->securityQuestions();

        self::assertGreaterThanOrEqual(10, count($questions));
        self::assertContains('What is your favorite all-time beer to drink?', $questions);
        self::assertSame([], array_values(array_filter($questions, static fn (string $question): bool => str_contains($question, '&'))));
    }
}
