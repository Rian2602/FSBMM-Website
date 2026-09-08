<?php

namespace App\Filament\Widgets;

use App\Support\LearningProgress;
use Filament\Widgets\Widget;

class LearningReportWidget extends Widget
{
    protected static string $view = 'filament.widgets.learning-report';

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
