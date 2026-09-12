<?php

namespace App\Filament\Pages;

use App\Models\Course;
use App\Support\LearningProgress;
use App\Support\ResolvesPanelRoutes;
use Filament\Pages\Page;

abstract class CourseDetailBase extends Page
{
    use ResolvesPanelRoutes;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $title = 'Detail Kursus';

    protected static string $view = 'filament.pages.course-detail';

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

    public function getCourseProgress(): array
    {
        return app(LearningProgress::class)->forCourse(auth()->user(), $this->record);
    }

    // (** executed: spec §6b requires a manual "Tandai Selesai"/"Batal
    // Selesai" toggle for lessons WITHOUT a quiz (quiz lessons complete
    // automatically on pass); Task 5's view omitted it, which blocked course
    // completion for quiz-less lessons. Surfaced by the Task 9 smoke.
    // Task 5 evaluation: the toggle must reject quiz-bearing lessons
    // server-side — the blade hides the button, but a crafted Livewire call
    // would mark such a lesson done without passing its quiz, and
    // LearningProgress::lessonStatus() prefers the progress row over quiz
    // attempts. Merged into this shared base from the Admin twin; the SBA
    // twin's 88299e2 self-evaluation reached the same conclusion and both
    // panels now share this guard. **)
    public function toggleLessonCompletion(int $lessonId): void
    {
        $lesson = $this->record->lessons()->findOrFail($lessonId);

        abort_unless($lesson->quizzes->isEmpty(), 403);

        $progress = app(LearningProgress::class);
        $user = auth()->user();
        $nowDone = $progress->lessonStatus($user, $this->record, $lesson);

        $progress->setLessonCompleted($user, $this->record, $lesson, ! $nowDone);
    }
}
