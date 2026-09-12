<?php

namespace App\Providers;

use App\Models\Complaint;
use App\Models\Member;
use App\Observers\ComplaintObserver;
use App\Observers\MemberObserver;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Member::observe(MemberObserver::class);
        Complaint::observe(ComplaintObserver::class);

        // Self-hosted fonts (Phase C): inject the static fonts.css into both
        // Filament panels (NullFontProvider already killed the Bunny <link>).
        FilamentView::registerRenderHook(PanelsRenderHook::STYLES_AFTER, fn (): HtmlString => new HtmlString(
            (string) Blade::render('<link rel="stylesheet" href="{{ asset(\'css/fonts.css\') }}">'),
        ));
    }
}
