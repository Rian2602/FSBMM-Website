<?php

namespace App\Filament\Admin\Pages;

use App\Models\Course;
use App\Support\LearningProgress;
use Filament\Pages\Page;

class MyCoursesPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Kursus Saya';

    protected static ?string $title = 'Kursus Saya';

    protected static string $view = 'filament.admin.pages.my-courses';

    protected static ?string $slug = 'my-courses';

    public static function getNavigationGroup(): ?string
    {
        return 'Pembelajaran';
    }

    public function getCourses()
    {
        // (** executed: `finalQuiz` is a method returning ?CourseQuiz, not a
        // relation — with('finalQuiz') crashes eager loading (addEagerConstraints
        // on null). Access it as $course->finalQuiz (dynamic call) instead. **)
        return Course::published()->with('lessons')->orderBy('title')->get();
    }

    public function getProgress(): LearningProgress
    {
        return app(LearningProgress::class);
    }
}
