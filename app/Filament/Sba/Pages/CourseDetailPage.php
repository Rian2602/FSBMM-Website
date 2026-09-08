<?php

namespace App\Filament\Sba\Pages;

use App\Models\Course;
use App\Support\LearningProgress;
use Filament\Pages\Page;

class CourseDetailPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $title = 'Detail Kursus';

    protected static string $view = 'filament.sba.pages.course-detail';

    protected static ?string $slug = 'course-detail';

    public ?Course $record = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(?Course $record = null): void
    {
        abort_unless($record && $record->is_published, 404);

        $this->record = $record;
    }

    public function getCourse(): ?Course
    {
        return $this->record;
    }

    public function getCourseProgress(): array
    {
        return app(LearningProgress::class)->forCourse(auth()->user(), $this->record);
    }

    // (** executed: spec §6b toggle for lessons without a quiz (see Admin twin). **)
    public function toggleLessonCompletion(int $lessonId): void
    {
        $lesson = $this->record->lessons()->findOrFail($lessonId);
        $progress = app(LearningProgress::class);
        $user = auth()->user();
        $nowDone = $progress->lessonStatus($user, $this->record, $lesson);

        $progress->setLessonCompleted($user, $this->record, $lesson, ! $nowDone);
    }
}
