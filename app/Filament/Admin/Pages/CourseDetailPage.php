<?php

namespace App\Filament\Admin\Pages;

use App\Models\Course;
use App\Support\LearningProgress;
use Filament\Pages\Page;

class CourseDetailPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $title = 'Detail Kursus';

    protected static string $view = 'filament.admin.pages.course-detail';

    protected static ?string $slug = 'course-detail';

    public ?Course $record = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(?Course $record = null): void
    {
        // Learning surface shows published courses only — a draft course is
        // unreachable even by direct URL.
        abort_unless($record && $record->is_published, 404);

        $this->record = $record;
    }

    public function getCourse(): ?Course
    {
        return $this->record;
    }

    public function getProgress(): LearningProgress
    {
        return app(LearningProgress::class);
    }
}
