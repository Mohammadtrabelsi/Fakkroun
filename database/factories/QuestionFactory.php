<?php

namespace Database\Factories;

use App\Domain\Questions\DuplicateDetector;
use App\Domain\Questions\Enums\Difficulty;
use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Enums\TranslationStatus;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionChoice;
use App\Domain\Questions\QuestionPool;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Builds a complete question: four choices, the correct one set, and an
 * origin-language translation. Use ->published() for a playable question.
 *
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /** Translation text keyed by language, applied after creation. */
    private array $texts = [];

    private ?string $translationStatus = null;

    /**
     * Factories rebuild themselves on every state()/count() call; carry the
     * per-language texts across.
     */
    protected function newInstance(array $arguments = []): static
    {
        $factory = parent::newInstance($arguments);
        $factory->texts = $this->texts;
        $factory->translationStatus = $this->translationStatus;

        return $factory;
    }

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'origin_language' => 'en',
            'difficulty' => fake()->randomElement(Difficulty::values()),
            'source' => QuestionSource::Admin->value,
            'reference_url' => 'https://example.org/'.fake()->slug(),
        ];
    }

    /**
     * Runs on the factory that actually creates the models. An afterCreating()
     * closure registered in configure() would stay bound to the first
     * instance and miss texts added later with withText().
     *
     * @param  Collection<int, Question>  $instances
     */
    protected function callAfterCreating(Collection $instances, ?Model $parent = null): void
    {
        $instances->each(fn (Question $question) => $this->complete($question));

        parent::callAfterCreating($instances, $parent);
    }

    private function complete(Question $question): void
    {
        $choices = collect(range(1, 4))->map(fn (int $position) => QuestionChoice::query()->create([
            'question_id' => $question->id,
            'position' => $position,
        ]));

        $question->forceFill(['correct_choice_id' => $choices[0]->id])->saveQuietly();

        $status = $this->translationStatus
            ?? ($question->status === QuestionStatus::Published || $question->status === QuestionStatus::Approved
                ? TranslationStatus::Approved->value
                : TranslationStatus::Pending->value);

        $languages = array_unique([$question->origin_language, ...array_keys($this->texts)]);

        foreach ($languages as $language) {
            $text = $this->texts[$language] ?? [];
            $body = $text['body'] ?? fake()->unique()->sentence(8).'?';
            $answers = $text['choices'] ?? [fake()->word().' one', fake()->word().' two', fake()->word().' three', fake()->word().' four'];
            $map = $choices->values()->mapWithKeys(fn (QuestionChoice $choice, int $i) => [(string) $choice->id => $answers[$i]])->all();

            $translation = $question->translations()->create([
                'language' => $language,
                'body' => $body,
                'choices' => $map,
                'explanation' => $text['explanation'] ?? fake()->sentence(),
                'content_hash' => app(DuplicateDetector::class)->hash($body, $answers),
            ]);
            $translation->forceFill(['status' => $text['status'] ?? $status])->save();
        }

        app(QuestionPool::class)->sync($question->fresh());
    }

    public function status(QuestionStatus $status): static
    {
        return $this->state(fn () => [])->afterMaking(function (Question $question) use ($status): void {
            $question->status = $status;
            $question->published_at = $status === QuestionStatus::Published ? now() : null;
            $question->submitted_at = $status === QuestionStatus::Draft ? null : now();
        });
    }

    public function published(): static
    {
        return $this->status(QuestionStatus::Published);
    }

    public function pending(): static
    {
        return $this->status(QuestionStatus::Pending);
    }

    public function community(): static
    {
        return $this->state(fn () => ['source' => QuestionSource::Community->value]);
    }

    /**
     * Fixed text for a language (also adds that translation).
     *
     * @param  array{body?: string, choices?: array<int, string>, explanation?: string, status?: string}  $text
     */
    public function withText(string $language, array $text): static
    {
        $clone = clone $this;
        $clone->texts[$language] = $text;

        return $clone;
    }

    public function translationStatus(TranslationStatus $status): static
    {
        $clone = clone $this;
        $clone->translationStatus = $status->value;

        return $clone;
    }
}
