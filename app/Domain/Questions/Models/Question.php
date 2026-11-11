<?php

namespace App\Domain\Questions\Models;

use App\Domain\Questions\Enums\Difficulty;
use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Enums\QuestionType;
use App\Domain\Questions\Enums\TranslationStatus;
use App\Models\Category;
use App\Models\Concerns\TracksUserActions;
use App\Models\User;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Language-independent part of a question. Text lives in QuestionTranslation;
 * answers are QuestionChoice rows with stable ids, and correct_choice_id
 * points at one of them (never at translated text).
 *
 * Status changes go through ModerationService; content changes through
 * QuestionService. Neither should be written directly.
 *
 * @property int $id
 * @property string $uuid
 * @property int $category_id
 * @property int|null $subcategory_id
 * @property int|null $creator_id
 * @property string $origin_language
 * @property QuestionType $type
 * @property Difficulty $difficulty
 * @property int|null $correct_choice_id
 * @property QuestionSource $source
 * @property QuestionStatus $status
 * @property int $version
 */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, SoftDeletes, TracksUserActions;

    protected $fillable = [
        'category_id',
        'subcategory_id',
        'creator_id',
        'origin_language',
        'type',
        'difficulty',
        'source',
        'reference_url',
        'reference_text',
        'is_sponsored',
        'campaign_id',
        'import_batch_id',
    ];

    protected $attributes = [
        'type' => 'mcq',
        'status' => 'draft',
        'version' => 1,
        'is_sponsored' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Question $question): void {
            $question->uuid ??= (string) Str::uuid();
        });
    }

    protected static function newFactory(): QuestionFactory
    {
        return QuestionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'difficulty' => Difficulty::class,
            'source' => QuestionSource::class,
            'status' => QuestionStatus::class,
            'is_sponsored' => 'boolean',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'version' => 'integer',
            'times_played' => 'integer',
            'times_correct' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    /**
     * The person who wrote the question (community submitter, admin, importer).
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<QuestionChoice, $this>
     */
    public function choices(): HasMany
    {
        return $this->hasMany(QuestionChoice::class)->orderBy('position');
    }

    /**
     * @return HasMany<QuestionTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(QuestionTranslation::class);
    }

    /**
     * @return HasMany<QuestionRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(QuestionRevision::class)->orderByDesc('version');
    }

    /**
     * @return HasMany<QuestionModerationLog, $this>
     */
    public function moderationLogs(): HasMany
    {
        return $this->hasMany(QuestionModerationLog::class)->orderByDesc('id');
    }

    /**
     * Notes recorded when the question was created (validator warnings,
     * possible duplicates), read from the loaded moderation logs.
     *
     * @return array<int|string, mixed>
     */
    public function creationNotes(string $key): array
    {
        return (array) (($this->moderationLogs->first()?->meta ?? [])[$key] ?? []);
    }

    /**
     * @return HasMany<QuestionReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(QuestionReport::class);
    }

    /**
     * @return HasMany<QuestionPoolEntry, $this>
     */
    public function poolEntries(): HasMany
    {
        return $this->hasMany(QuestionPoolEntry::class);
    }

    public function translation(string $language): ?QuestionTranslation
    {
        return $this->relationLoaded('translations')
            ? $this->translations->firstWhere('language', $language)
            : $this->translations()->where('language', $language)->first();
    }

    public function originTranslation(): ?QuestionTranslation
    {
        return $this->translation($this->origin_language);
    }

    /**
     * Languages a player could receive this question in right now.
     *
     * @return array<int, string>
     */
    public function playableLanguages(): array
    {
        if ($this->status !== QuestionStatus::Published) {
            return [];
        }

        return $this->translations()
            ->where('status', TranslationStatus::Approved->value)
            ->pluck('language')
            ->all();
    }

    public function isPublished(): bool
    {
        return $this->status === QuestionStatus::Published;
    }

    /**
     * Text shown in admin lists: the origin translation, or any translation.
     */
    public function label(): string
    {
        $translation = $this->originTranslation() ?? $this->translations()->first();

        return (string) ($translation?->body ?? '—');
    }

    /**
     * @param  Builder<Question>  $query
     * @param  array<int, int>  $categoryIds
     */
    public function scopeInCategories(Builder $query, array $categoryIds): void
    {
        $query->where(fn (Builder $q) => $q->whereIn('category_id', $categoryIds)->orWhereIn('subcategory_id', $categoryIds));
    }

    /**
     * @param  Builder<Question>  $query
     */
    public function scopeAwaitingReview(Builder $query): void
    {
        $query->whereIn('status', array_map(fn (QuestionStatus $s) => $s->value, QuestionStatus::awaitingReview()));
    }
}
