<?php

namespace App\Support;

use App\Models\CourseAttempt;
use App\Models\CourseProgress;
use App\Models\CourseQuiz;
use App\Models\User;

class QuizEngine
{
    /**
     * Record a quiz attempt and, for a passing lesson quiz, mark the owning
     * lesson complete for this user. Final quizzes (lesson_id null) record
     * the attempt only — course completion is derived in LearningProgress.
     *
     * @param  array<int, int|string>  $answers  keyed [question_id => option_id]
     * @return array{score: int, passed: bool, attempt: CourseAttempt}
     */
    public function submit(CourseQuiz $quiz, User $user, array $answers): array
    {
        $score = $quiz->score($answers);
        $passed = $score >= $quiz->passThreshold();

        $attempt = CourseAttempt::create([
            'course_quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'score' => $score,
            'passed' => $passed,
            'attempt_date' => now(),
        ]);

        if ($quiz->lesson_id !== null) {
            $this->syncLessonProgress($quiz, $user, $passed);
        }

        return ['score' => $score, 'passed' => $passed, 'attempt' => $attempt];
    }

    private function syncLessonProgress(CourseQuiz $quiz, User $user, bool $passed): void
    {
        // (** executed: the plan's engine snippet only touched CourseProgress
        // on a pass, but its own Task 6 test #2 asserts a row with
        // is_completed=false exists after a failed attempt — a first failed
        // attempt therefore records the not-yet-complete state. A later
        // failure never downgrades an existing completion (firstOrCreate), so
        // a lesson already passed stays passed. **)
        if ($passed) {
            CourseProgress::updateOrCreate(
                ['user_id' => $user->id, 'course_id' => $quiz->course_id, 'lesson_id' => $quiz->lesson_id],
                ['is_completed' => true, 'completed_at' => now()],
            );

            return;
        }

        CourseProgress::firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $quiz->course_id, 'lesson_id' => $quiz->lesson_id],
            ['is_completed' => false, 'completed_at' => null],
        );
    }
}
