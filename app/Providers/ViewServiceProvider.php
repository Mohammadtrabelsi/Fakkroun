<?php

namespace App\Providers;

use App\Services\CategoryService;
use App\Services\NotificationService;
use App\Services\PartnerService;
use App\Services\PlatformSettings;
use App\Support\View\MarketingArt;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

/**
 * View composers: the data that Blade pages and layouts need but that no
 * Livewire component hands them. Views stay free of PHP blocks and queries
 * (house rule: logic in services, views only render).
 */
class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // @themeScript: the render-blocking theme loader (resources/js/theme.js), so that
        // templates hold no <script> tag of their own.
        Blade::directive('themeScript', fn (): string => '<script src="<?php echo e(\\Illuminate\\Support\\Facades\\Vite::asset(\'resources/js/theme.js\')); ?>"></script>');

        // Every "Play" button opens the chooser: join a room or host one.
        ViewFacade::composer(['welcome', 'about', 'components.layouts.fakkroun'], function (View $view): void {
            $view->with('playUrl', route('play'));
        });

        ViewFacade::composer('welcome', function (View $view): void {
            $categories = app(CategoryService::class);

            $view->with([
                'locale' => app()->getLocale(),
                'priority' => $categories->homeCategories(priority: true),
                'others' => $categories->homeCategories(priority: false),
                'partners' => app(PartnerService::class)->visible(),
                'tiles' => MarketingArt::HOME_TILES,
                'icons' => MarketingArt::HOME_ICONS,
            ]);
        });

        ViewFacade::composer('about', function (View $view): void {
            $view->with([
                'check' => MarketingArt::CHECK_ICON,
                'icons' => MarketingArt::ABOUT_ICONS,
                'tints' => MarketingArt::ABOUT_TINTS,
                'sections' => MarketingArt::ABOUT_SECTIONS,
            ]);
        });

        ViewFacade::composer('terms', function (View $view): void {
            $legal = (array) config('fakkroun.legal');

            $view->with(['legal' => $legal, 'age' => $legal['minimum_age']]);
        });

        // Sponsored card: when the browser may report it as seen (advertising settings).
        ViewFacade::composer('partials.ads.card', function (View $view): void {
            $rules = app(PlatformSettings::class)->all()['advertising'];

            $view->with([
                'adViewRatio' => max(0.1, min(1, ((int) $rules['view_ratio_percent']) / 100)),
                'adViewDelay' => max(1, (int) $rules['view_seconds']) * 1000,
            ]);
        });

        // Bell badge in the signed-in shell.
        ViewFacade::composer(['layouts.app', 'layouts::app'], function (View $view): void {
            $view->with('unreadCount', auth()->check() ? app(NotificationService::class)->unreadCount(auth()->user()) : 0);
        });
    }
}
