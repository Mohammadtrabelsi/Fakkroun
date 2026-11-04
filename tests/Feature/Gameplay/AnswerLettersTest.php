<?php

namespace Tests\Feature\Gameplay;

use App\Domain\Questions\Models\QuestionChoice;
use Tests\TestCase;

class AnswerLettersTest extends TestCase
{
    public function test_latin_and_arabic_answer_letters(): void
    {
        $letters = fn (?string $language) => array_map(
            fn (int $position) => (new QuestionChoice(['position' => $position]))->letter($language),
            [1, 2, 3, 4],
        );

        $this->assertSame(['A', 'B', 'C', 'D'], $letters(null));
        $this->assertSame(['A', 'B', 'C', 'D'], $letters('fr'));
        $this->assertSame(['أ', 'ب', 'ج', 'د'], $letters('ar'));
    }
}
