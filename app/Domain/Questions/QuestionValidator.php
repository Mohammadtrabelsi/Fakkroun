<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Enums\Difficulty;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Structural and quality checks for question content (spec §7.1, §8).
 *
 * Errors throw a ValidationException keyed by field so forms can show them.
 * Softer findings come back as warnings for the moderator to weigh: they
 * never block a submission on their own.
 *
 * Content shape:
 *   body, choices (4 strings), correct (0-based index), explanation?,
 *   reference_url?, reference_text?
 */
class QuestionValidator
{
    public const CHOICES = 4;

    public function __construct(
        private readonly DuplicateDetector $duplicates,
        private readonly CategoryService $categories,
    ) {}

    /**
     * Rules for the language-specific text (used for originals and translations).
     *
     * @return array<string, array<int, mixed>>
     */
    public function textRules(): array
    {
        $config = config('fakkroun.questions');

        return [
            'body' => ['required', 'string', 'min:'.$config['body_min'], 'max:'.$config['body_max']],
            'choices' => ['required', 'array', 'size:'.self::CHOICES],
            'choices.*' => ['required', 'string', 'max:'.$config['choice_max']],
            'explanation' => ['nullable', 'string', 'max:'.$config['explanation_max']],
        ];
    }

    /**
     * Validates a full question (metadata + origin text). Returns warnings.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public function validateQuestion(array $data): array
    {
        $config = config('fakkroun.questions');

        $validator = Validator::make($data, [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'difficulty' => ['required', Rule::in(Difficulty::values())],
            'language' => ['required', Rule::in(array_keys((array) config('app.available_locales')))],
            'correct' => ['required', 'integer', 'min:0', 'max:'.(self::CHOICES - 1)],
            'reference_url' => ['nullable', 'url:http,https', 'max:2048'],
            'reference_text' => ['nullable', 'string', 'max:'.$config['reference_text_max']],
            ...$this->textRules(),
        ]);

        $validator->after(function ($validator) use ($data): void {
            $this->checkDistinctChoices($validator, (array) ($data['choices'] ?? []));

            if (! empty($data['subcategory_id']) && ! empty($data['category_id'])) {
                $parent = $this->categories->query()->whereKey($data['subcategory_id'])->value('parent_id');

                if ((int) $parent !== (int) $data['category_id']) {
                    $validator->errors()->add('subcategory_id', __('questions.errors.subcategory_mismatch'));
                }
            }
        });

        $validator->validate();

        return $this->warnings($data);
    }

    /**
     * Validates a translation's text. Returns warnings.
     *
     * @param  array<string, mixed>  $data  body, choices, explanation?
     * @return array<int, string>
     */
    public function validateText(array $data): array
    {
        $validator = Validator::make($data, $this->textRules());
        $validator->after(fn ($validator) => $this->checkDistinctChoices($validator, (array) ($data['choices'] ?? [])));
        $validator->validate();

        return $this->warnings($data);
    }

    /**
     * Rules that must hold before a question may be approved.
     */
    public function assertApprovable(?string $referenceUrl, ?string $referenceText, Category $category): void
    {
        if ($category->requires_reference && blank($referenceUrl) && blank($referenceText)) {
            throw ValidationException::withMessages([
                'reference_url' => __('questions.errors.reference_required'),
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $choices
     */
    private function checkDistinctChoices($validator, array $choices): void
    {
        $normalized = array_map(fn ($choice) => $this->duplicates->normalize((string) $choice), $choices);
        $filled = array_filter($normalized, fn (string $choice) => $choice !== '');

        if (count($filled) !== count(array_unique($filled))) {
            $validator->errors()->add('choices', __('questions.errors.choices_not_distinct'));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function warnings(array $data): array
    {
        $warnings = [];
        $body = $this->duplicates->normalize((string) ($data['body'] ?? ''));
        $choices = array_values((array) ($data['choices'] ?? []));

        if (isset($data['correct'], $choices[(int) $data['correct']])) {
            $answer = $this->duplicates->normalize((string) $choices[(int) $data['correct']]);

            if (mb_strlen($answer) >= 4 && str_contains(" {$body} ", " {$answer} ")) {
                $warnings[] = __('questions.warnings.answer_in_question');
            }
        }

        $haystack = ' '.$body.' '.$this->duplicates->normalize(implode(' ', array_map('strval', $choices))).' ';

        foreach ((array) config('fakkroun.questions.flagged_terms', []) as $term) {
            $term = $this->duplicates->normalize((string) $term);

            if ($term !== '' && str_contains($haystack, " {$term} ")) {
                $warnings[] = __('questions.warnings.flagged_terms');

                break;
            }
        }

        return $warnings;
    }
}
