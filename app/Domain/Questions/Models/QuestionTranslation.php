<?php

namespace App\Domain\Questions\Models;

use App\Domain\Questions\Enums\TranslationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The text of one question in one language, approved independently of the
 * other languages (spec §5.2, §5.4).
 *
 * @property int $id
 * @property int $question_id
 * @property string $language
 * @property string $body
 * @property array<string, string> $choices choice id => text
 * @property string|null $explanation
 * @property string $content_hash
 * @property TranslationStatus $status
 */
class QuestionTranslation extends Model
{
    protected $fillable = [
        'question_id',
        'language',
        'body',
        'choices',
        'explanation',
        'content_hash',
        'is_machine_translated',
        'translated_by',
    ];

    protected $attributes = [
        'status' => 'pending',
        'is_machine_translated' => false,
    ];

    protected function casts(): array
    {
        return [
            'choices' => 'array',
            'status' => TranslationStatus::class,
            'is_machine_translated' => 'boolean',
            'validated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function translator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'translated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function isApproved(): bool
    {
        return $this->status === TranslationStatus::Approved;
    }

    public function choiceText(int $choiceId): ?string
    {
        return $this->choices[(string) $choiceId] ?? null;
    }
}
