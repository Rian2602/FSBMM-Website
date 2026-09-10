<?php

namespace App\Providers\Filament;

use App\Exports\AttendanceExport;
use App\Exports\ComplaintExport;
use App\Exports\DuesExport;

use App\Filament\Sba\Pages\CourseDetailPage;
use App\Filament\Sba\Pages\LessonViewPage;
use App\Filament\Sba\Pages\MyCoursesPage;
use App\Filament\Sba\Pages\QuizViewPage;
use App\Filament\Sba\Pages\MemberReportPage;
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
use App\Exports\MemberExport;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SbaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('sba')
            ->path('panel-sba')
            ->login()
            ->profile()
            ->colors([
                'primary' => Color::Emerald, // same placeholder brand family as /admin
                'info' => Color::Sky,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'success' => Color::Lime,
            ])
            ->discoverResources(in: app_path('Filament/Sba/Resources'), for: 'App\\Filament\\Sba\\Resources')
            ->discoverPages(in: app_path('Filament/Sba/Pages'), for: 'App\\Filament\\Sba\\Pages')
            ->discoverWidgets(in: app_path('Filament/Sba/Widgets'), for: 'App\\Filament\\Sba\\Widgets')

            ->pages([
                Pages\Dashboard::class,
                MyCoursesPage::class,
                CourseDetailPage::class,
                LessonViewPage::class,
                QuizViewPage::class,
                MemberReportPage::class,
            ])
            // (** executed: same panel-route pattern as the admin panel — see
            // AdminPanelProvider note (page-level getRoutes() does not exist
            // in Filament v3.3.55). **)
            ->authenticatedRoutes(function (): array {
                return [
                    Route::get('/courses/{record}', CourseDetailPage::class)->name('courses.show'),
                    Route::get('/courses/{record}/lessons/{lesson}', LessonViewPage::class)->name('courses.lessons.show'),
                    Route::get('/quizzes/{record}', QuizViewPage::class)->name('quizzes.show'),
                    Route::get('/member-report/export', function (): BinaryFileResponse {
                        return MemberExport::streamFor(
                            auth()->user()->organization_id,
                            request()->only(['status', 'department', 'position', 'education', 'gender', 'join_date_start', 'join_date_end']),
                            request()->string('format', 'csv')->toString(),
                        );
                    })->name('member-report.export'),
                    Route::get('/dues-report/export', function (): BinaryFileResponse {
                        return DuesExport::streamFor(
                            auth()->user()->organization_id,
                            request()->only(['period', 'period_start', 'period_end']),
                            request()->string('format', 'csv')->toString(),
                        );
                    })->name('dues-report.export'),
                    Route::get('/attendance-report/export', function (): BinaryFileResponse {
                        return AttendanceExport::streamFor(
                            auth()->user()->organization_id,
                            request()->only(['event_id', 'event_date_start', 'event_date_end']),
                            request()->string('format', 'csv')->toString(),
                        );
                    })->name('attendance-report.export'),
                    Route::get('/complaint-report/export', function (): BinaryFileResponse {
                        return ComplaintExport::streamFor(
                            auth()->user()->organization_id,
                            request()->only(['status', 'submitted_start', 'submitted_end', 'include_description']),
                            request()->string('format', 'csv')->toString(),
                        );
                    })->name('complaint-report.export'),
                ];
            })
            ->widgets([
                Widgets\AccountWidget::class,
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
