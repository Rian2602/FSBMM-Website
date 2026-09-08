<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseQuiz extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'lesson_id', 'title', 'pass_threshold'];

    protected function casts(): array
    {
        return ['pass_threshold' => 'integer', 'lesson_id' => 'integer'];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson()
    {
        return $this->belongsTo(CourseLesson::class);
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order');
    }

    public function attempts()
    {
        return $this->hasMany(CourseAttempt::class);
    }

    /** Whether this is the course's final quiz (no owning lesson). */
    public function isFinal(): bool
    {
        return $this->lesson_id === null;
    }

    /** The effective pass threshold (per-quiz value overrides course default). */
    public function passThreshold(): int
    {
        return $this->pass_threshold ?? $this->course->pass_threshold ?? 70;
    }

    /**
     * Server-side score (0–100) for a submitted MCQ answer set
     * keyed [question_id => option_id].
     *
     * @param  array<int, int>  $answers
     */
    public function score(array $answers): int
    {
        $questions = $this->questions()->with('options')->get();
        if ($questions->isEmpty()) {
            return 0;
        }

        $correct = 0;
        foreach ($questions as $question) {
            $right = $question->options->firstWhere('is_correct', true)?->id;
            $chosen = $answers[$question->id] ?? null;
            // (** executed: form answers arrive as strings (radio value), so
            // strict comparison against the int option id scored 0 — cast both
            // sides at this trust boundary. **)
            if ($right !== null && $chosen !== null && (int) $chosen === (int) $right) {
                $correct++;
            }
        }

        return (int) round(($correct / $questions->count()) * 100);
    }
}
