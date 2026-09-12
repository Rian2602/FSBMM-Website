<?php

namespace App\Filament\Pages;

use App\Support\LearningProgress;
use App\Support\ResolvesPanelRoutes;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

abstract class MyCoursesBase extends Page
{
    use ResolvesPanelRoutes;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Kursus Saya';

    protected static ?string $title = 'Kursus Saya';

    protected static string $view = 'filament.pages.my-courses';

    protected static ?string $slug = 'my-courses';

    public static function getNavigationGroup(): ?string
    {
        return 'Pembelajaran';
    }

    public function getCourses(): Collection
    {
        return app(LearningProgress::class)->forUser(auth()->user());
    }
}
