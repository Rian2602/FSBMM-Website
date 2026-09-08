<?php

namespace App\Filament\Sba\Pages;

use App\Support\LearningProgress;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class MyCoursesPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Kursus Saya';

    protected static ?string $title = 'Kursus Saya';

    protected static string $view = 'filament.sba.pages.my-courses';

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
