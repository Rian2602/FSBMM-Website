<?php

namespace App\Filament\Widgets;

use App\Support\LearningProgress;
use Filament\Widgets\Widget;

class LearningReportWidget extends Widget
{
    protected static string $view = 'filament.widgets.learning-report';

    // (** executed: Filament 3.3.55 renders widgets lazy by default
    // (CanBeLazy::$isLazy = true), so the content would only appear after
    // scrolling into view — same reason MemberDataOverviewWidget opts out.
    // Server-rendered content keeps HTTP dashboard assertions honest and
    // matches the SP3 widget convention. **)
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    /** Federation learning report is a super-admin view. */
    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getReport()
    {
        return app(LearningProgress::class)->report();
    }
}
