<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\CourseDetailPage;
use App\Filament\Admin\Pages\LessonViewPage;
use App\Filament\Admin\Pages\MyCoursesPage;
use App\Filament\Admin\Pages\QuizViewPage;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Emerald, // brand family placeholder; final palette when official assets arrive
                'info' => Color::Sky,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'success' => Color::Lime,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
                MyCoursesPage::class,
                CourseDetailPage::class,
                LessonViewPage::class,
                QuizViewPage::class,
            ])
            // (** executed: plan Task 5 used a page-level getRoutes() method,
            // which does not exist in Filament v3.3.55 (custom pages get a
            // single slug route). Pretty record URLs are registered here as
            // panel authenticatedRoutes — inside the auth middleware group, so
            // anonymous users are redirected to login — and rendered as
            // Livewire full-page components with implicit route binding into
            // mount(?Course $record). **)
            ->authenticatedRoutes(function (): array {
                return [
                    Route::get('/courses/{record}', CourseDetailPage::class)->name('courses.show'),
                    Route::get('/courses/{record}/lessons/{lesson}', LessonViewPage::class)->name('courses.lessons.show'),
                    Route::get('/quizzes/{record}', QuizViewPage::class)->name('quizzes.show'),
                ];
            })
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
