<?php

namespace App\Filament\Sba\Pages;

use App\Models\CourseQuiz;
use App\Support\QuizEngine;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class QuizViewPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $title = 'Kerjakan Kuis';

    protected static string $view = 'filament.sba.pages.quiz-view';

    protected static ?string $slug = 'quiz-view';

    public ?CourseQuiz $quiz = null;

    /** @var array<int, int|null> keyed [question_id => option_id] */
    public array $answers = [];

    /** @var array{score: int, passed: bool}|null */
    public ?array $result = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(CourseQuiz $record): void
    {
        abort_unless($record->course?->is_published, 404);

        $this->quiz = $record;
        $this->answers = $record->questions->mapWithKeys(fn ($q) => [$q->id => null])->all();
    }

    public function submit(): void
    {
        $unanswered = collect($this->answers)->filter(fn ($v) => blank($v))->keys();

        if ($unanswered->isNotEmpty()) {
            Notification::make()
                ->warning()
                ->title('Lengkapi semua jawaban terlebih dahulu.')
                ->send();

            return;
        }

        $this->result = app(QuizEngine::class)->submit($this->quiz, Auth::user(), $this->answers);
    }

    public function getQuiz(): ?CourseQuiz
    {
        return $this->quiz;
    }

    public function getResult(): ?array
    {
        return $this->result;
    }
}
