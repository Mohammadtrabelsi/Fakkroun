<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Enums\TranslationStatus;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionTranslation;
use App\Models\User;
use App\Services\CategoryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read side of the question bank: admin listing and moderation queues.
 * Every query selects a page at a time; nothing loads the whole bank.
 */
class QuestionQueries
{
    private const SORTABLE = ['created_at', 'updated_at', 'difficulty', 'status'];

    public function __construct(private readonly CategoryService $categories) {}

    /**
     * Categories a user may moderate: null means all of them.
     *
     * @return array<int, int>|null
     */
    public function scopeFor(User $user): ?array
    {
        if ($user->can('manage questions')) {
            return null;
        }

        return $user->can('moderate questions') ? $this->categories->moderatedCategoryIds($user) : [];
    }

    /**
     * Filters: search, status, category_id, difficulty, language, source,
     * trashed (only), sort, direction.
     *
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters = [], int $perPage = 15, ?array $categoryScope = null): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $search = trim((string) ($filters['search'] ?? ''));

        return Question::query()
            ->with(['category:id,name,slug', 'translations:id,question_id,language,body,status', 'author:id,name'])
            ->when($categoryScope !== null, fn ($query) => $query->inCategories($categoryScope))
            ->when(($filters['trashed'] ?? null) === 'only', fn ($query) => $query->onlyTrashed())
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['category_id'] ?? null), fn ($query) => $query->inCategories([(int) $filters['category_id']]))
            ->when(filled($filters['difficulty'] ?? null), fn ($query) => $query->where('difficulty', $filters['difficulty']))
            ->when(filled($filters['source'] ?? null), fn ($query) => $query->where('source', $filters['source']))
            ->when(filled($filters['language'] ?? null), fn ($query) => $query->whereHas(
                'translations', fn ($q) => $q->where('language', $filters['language'])
            ))
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search): void {
                $q->where('uuid', $search)
                    ->orWhereHas('translations', fn ($t) => $t->where('body', 'like', '%'.addcslashes($search, '%_\\').'%'));
            }))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Questions awaiting a decision, oldest submission first.
     */
    public function reviewQueue(User $moderator, int $perPage = 10): LengthAwarePaginator
    {
        return Question::query()
            ->awaitingReview()
            ->when(($scope = $this->scopeFor($moderator)) !== null, fn ($query) => $query->inCategories($scope))
            ->with(['category', 'subcategory', 'choices', 'translations', 'author:id,name', 'moderationLogs' => fn ($q) => $q->where('action', 'created')])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate($perPage, pageName: 'queue');
    }

    /**
     * Pending non-origin translations of questions in the moderator's scope.
     */
    public function translationQueue(User $moderator, ?string $language = null, int $perPage = 10): LengthAwarePaginator
    {
        $scope = $this->scopeFor($moderator);

        return QuestionTranslation::query()
            ->where('status', TranslationStatus::Pending->value)
            ->when($language, fn ($query) => $query->where('language', $language))
            ->whereHas('question', function ($query) use ($scope): void {
                $query->whereColumn('questions.origin_language', '!=', 'question_translations.language')
                    ->whereNotIn('status', [QuestionStatus::Rejected->value]);

                if ($scope !== null) {
                    $query->inCategories($scope);
                }
            })
            ->with(['question.translations', 'question.choices', 'question.category'])
            ->orderBy('updated_at')
            ->paginate($perPage, pageName: 'translations');
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(?array $categoryScope = null): array
    {
        $counts = Question::query()
            ->when($categoryScope !== null, fn ($query) => $query->inCategories($categoryScope))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(QuestionStatus::values())->mapWithKeys(fn (string $status) => [$status => (int) ($counts[$status] ?? 0)])->all();
    }
}
