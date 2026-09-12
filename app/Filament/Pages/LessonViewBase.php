<?php

namespace App\Filament\Pages;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Support\ResolvesPanelRoutes;
use Filament\Pages\Page;

abstract class LessonViewBase extends Page
{
    use ResolvesPanelRoutes;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $title = 'Materi Pelajaran';

    protected static string $view = 'filament.pages.lesson-view';

    protected static ?string $slug = 'lesson-view';

    public ?Course $record = null;

    public ?CourseLesson $lesson = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(?Course $record = null, ?CourseLesson $lesson = null): void
    {
        abort_unless($record && $record->is_published, 404);
        abort_unless($lesson && $lesson->course_id === $record->id, 404);

        $this->record = $record;
        $this->lesson = $lesson;
    }

    public function getCourse(): ?Course
    {
        return $this->record;
    }

    public function getLesson(): ?CourseLesson
    {
        return $this->lesson;
    }
}
