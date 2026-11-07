<?php

namespace App\Providers;

use App\Domain\Ai\Contracts\QuestionGenerator;
use App\Domain\Ai\Drivers\AnthropicQuestionGenerator;
use App\Domain\Ai\Drivers\FakeQuestionGenerator;
use App\Domain\Questions\Contracts\SimilarQuestionFinder;
use App\Domain\Questions\Support\TokenOverlapFinder;
use App\Models\Post;
use App\Observers\CrudNotificationObserver;
use App\Observers\PostSitemapObserver;
use App\Services\StructuredDataService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Near-duplicate question detection; swap for an embedding-based
        // finder (Phase 4) without touching the callers.
        $this->app->bind(SimilarQuestionFinder::class, TokenOverlapFinder::class);

        // AI provider (spec §10.3): swap drivers through fakkroun.ai.driver.
        $this->app->bind(QuestionGenerator::class, fn () => config('fakkroun.ai.driver') === 'anthropic'
            ? new AnthropicQuestionGenerator
            : new FakeQuestionGenerator);

        // AI notes on advertising campaigns (story 16 S8), same driver switch as drafting.
        $this->app->bind(\App\Domain\Ai\Contracts\AdReviewer::class, fn () => config('fakkroun.ai.driver') === 'anthropic'
            ? new \App\Domain\Ai\Drivers\AnthropicAdReviewer
            : new \App\Domain\Ai\Drivers\FakeAdReviewer);

        // Payment method: cash only for now. Add a gateway driver and map it here.
        $this->app->bind(\App\Domain\Billing\Contracts\PaymentProvider::class, fn () => match (config('fakkroun.payments.driver')) {
            default => new \App\Domain\Billing\Drivers\CashPaymentProvider,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePasswordRules();
        $this->configureRateLimiting();
        $this->registerCrudNotifications();
        $this->registerStructuredData();
        $this->alertOnFailedJobs();

        // Keeps sitemap.xml fresh between scheduled runs (see config/sitemap.php).
        Post::observe(PostSitemapObserver::class);
    }

    /**
     * Register the generic CRUD notification observer against every model
     * declared in config/crud_notifications.php. Adding a resource to that
     * config is all it takes to start notifying — no per-model wiring here.
     */
    /**
     * JSON-LD for the static public pages (the blog pages are Livewire
     * components that pass their own to the marketing layout).
     */
    private function registerStructuredData(): void
    {
        View::composer('welcome', fn ($view) => $view->with(
            'structuredData', app(StructuredDataService::class)->forHome(),
        ));

        View::composer('about', fn ($view) => $view->with(
            'structuredData', app(StructuredDataService::class)->forPage([__('marketing.about') => route('about')]),
        ));

        View::composer('docs', fn ($view) => $view->with(
            'structuredData', app(StructuredDataService::class)->forPage([__('marketing.docs') => route('docs')]),
        ));
    }

    private function registerCrudNotifications(): void
    {
        if (! config('crud_notifications.enabled', true)) {
            return;
        }

        /** @var array<class-string<Model>, mixed> $models */
        $models = (array) config('crud_notifications.models', []);

        foreach (array_keys($models) as $model) {
            $model::observe(CrudNotificationObserver::class);
        }
    }

    /**
     * A single, centrally-tunable password policy used by every validation
     * rule in the app. Kept lenient in local/testing so factories and tests
     * stay fast, and hardened in production with complexity + breach checks.
     */
    private function configurePasswordRules(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8);

            return $this->app->isProduction()
                ? $rule->mixedCase()->numbers()->uncompromised()
                : $rule;
        });
    }

    /**
     * Named rate limiter guarding the stateless API auth endpoints against
     * credential stuffing / enumeration, keyed by email + client IP.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            $key = mb_strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(6)->by($key);
        });
    }

    /**
     * Next step 10: a failed queued job notifies administrators (database
     * notification), at most once per job type every ten minutes.
     */
    private function alertOnFailedJobs(): void
    {
        \Illuminate\Support\Facades\Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event): void {
            $name = (string) ($event->job->resolveName() ?: 'job');

            if (! \Illuminate\Support\Facades\Cache::add('job-failed-alert:'.md5($name), true, 600)) {
                return;
            }

            rescue(fn () => \App\Models\User::permission('view admin dashboard')->get()
                ->each(fn (\App\Models\User $admin) => $admin->notify(new \App\Notifications\JobFailedAlert($name, $event->exception->getMessage()))), report: true);
        });
    }
}
