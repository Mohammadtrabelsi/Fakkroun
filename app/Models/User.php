<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Models\Concerns\TracksUserActions;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'locale', 'question_locale'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
/**
 * Email verification is optional: players sign up with an email address or a
 * phone number and can play straight away, so the model does not implement
 * MustVerifyEmail (no verification mail on sign-up, no "verified" gate).
 * Verifying an email address is still possible from the verify-email page.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, Prunable, SoftDeletes, TracksUserActions;

    protected string $guard_name = 'web';

    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'status' => AccountStatus::class,
            'suspended_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_guest' => 'boolean',
            'plan_expires_at' => 'datetime',
        ];
    }

    /**
     * Nickname-only player created by the join page: may play in rooms but
     * has no account (cannot create rooms or open account pages).
     */
    public function isGuest(): bool
    {
        return (bool) $this->is_guest;
    }

    /**
     * Guest players are removed a week after they joined (model:prune).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::withTrashed()->where('is_guest', true)->where('created_at', '<', now()->subDays(7));
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Admins and moderators may enter /admin; each screen has its own permission.
     */
    public function canAccessBackoffice(): bool
    {
        return $this->can('access backoffice');
    }

    /**
     * First back-office screen this user may open (admins: the dashboard;
     * reviewers: the moderation queue).
     */
    public function backofficeHomeUrl(): string
    {
        return match (true) {
            $this->can('view admin dashboard') => route('admin.dashboard'),
            $this->can('manage questions') => route('admin.questions.index'),
            $this->can('moderate questions') => route('admin.moderation'),
            default => route('admin.notifications.index'),
        };
    }

    /**
     * Paid, ad-free plan currently running (see App\Domain\Billing\PlanService).
     */
    public function hasPaidPlan(): bool
    {
        return app(\App\Domain\Billing\PlanService::class)->isPaid($this);
    }

    public function isSuspended(): bool
    {
        return $this->status === AccountStatus::Suspended;
    }

    /**
     * Categories this user moderates (spec §3.3). Moderators only reach
     * content inside these categories unless they also manage all questions.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function moderatedCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_moderator')->withTimestamps();
    }

    /**
     * Interface language: the saved preference when it is still a supported locale.
     */
    public function preferredLocale(): ?string
    {
        return $this->supportedLocaleOrNull($this->locale);
    }

    /**
     * Language questions are served in. Independent of the interface language;
     * falls back to it, then to the platform default.
     */
    public function preferredQuestionLocale(): string
    {
        return $this->supportedLocaleOrNull($this->question_locale)
            ?? $this->preferredLocale()
            ?? (string) config('app.locale');
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/u', trim((string) $this->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    private function supportedLocaleOrNull(?string $locale): ?string
    {
        return $locale !== null && array_key_exists($locale, (array) config('app.available_locales', []))
            ? $locale
            : null;
    }

    /**
     * Two-factor is enforced only once the user has confirmed their secret.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * Send the email-verification notification on the queue so the request
     * that triggers it (registration / resend) isn't blocked on the mailer.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    /**
     * Same idea for password reset mails.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'status', 'suspension_reason'])
            ->logOnlyDirty()
            ->useLogName('users');
    }
}
