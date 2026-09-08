<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseQuiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Support\QuizEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizEngineTest extends TestCase
{
    use RefreshDatabase;

    private function quizWithThreshold(?int $threshold = null): CourseQuiz
    {
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        // (** executed: `Factory::for()` infers the relationship from the class
        // name (`courseLesson`), but the model relation is named `lesson` — pass
        // the relationship name explicitly. Same for `->for($quiz, 'quiz')` and
        // `->for($q, 'question')` below. Snippet plan omitted these. **)
        // (** executed: only set pass_threshold when given — the column is
        // NOT NULL, so an explicit null insert fails. Snippet plan always
        // passed `['pass_threshold' => $threshold]`. **)
        $quiz = CourseQuiz::factory()->for($course)->for($lesson, 'lesson')
            ->create($threshold === null ? [] : ['pass_threshold' => $threshold]);

        $q1 = QuizQuestion::factory()->for($quiz, 'quiz')->create();
        QuizOption::factory()->for($q1, 'question')->count(3)->create(['is_correct' => false]);
        QuizOption::factory()->for($q1, 'question')->create(['is_correct' => true]); // 1 of 4 correct

        $q2 = QuizQuestion::factory()->for($quiz, 'quiz')->create();
        QuizOption::factory()->for($q2, 'question')->count(2)->create(['is_correct' => false]);
        QuizOption::factory()->for($q2, 'question')->create(['is_correct' => true]); // 1 of 3 correct

        return $quiz;
    }

    public function test_score_is_100_when_all_answers_correct(): void
    {
        $quiz = $this->quizWithThreshold();
        $answers = [
            $quiz->questions->get(0)->id => $quiz->questions->get(0)->options->firstWhere('is_correct')->id,
            $quiz->questions->get(1)->id => $quiz->questions->get(1)->options->firstWhere('is_correct')->id,
        ];

        $this->assertSame(100, $quiz->score($answers));
    }

    public function test_score_is_0_when_all_answers_wrong(): void
    {
        $quiz = $this->quizWithThreshold();
        $answers = [
            $quiz->questions->get(0)->id => $quiz->questions->get(0)->options->where('is_correct', false)->first()->id,
            $quiz->questions->get(1)->id => $quiz->questions->get(1)->options->where('is_correct', false)->first()->id,
        ];

        $this->assertSame(0, $quiz->score($answers));
    }

    public function test_score_counts_partial_correct(): void
    {
        $quiz = $this->quizWithThreshold();
        $answers = [
            $quiz->questions->get(0)->id => $quiz->questions->get(0)->options->firstWhere('is_correct')->id, // right
            $quiz->questions->get(1)->id => $quiz->questions->get(1)->options->where('is_correct', false)->first()->id, // wrong
        ];

        $this->assertSame(50, $quiz->score($answers));
    }

    public function test_score_tolerates_string_option_ids_from_form_input(): void
    {
        $quiz = $this->quizWithThreshold();
        $answers = [
            $quiz->questions->get(0)->id => (string) $quiz->questions->get(0)->options->firstWhere('is_correct')->id,
            $quiz->questions->get(1)->id => (string) $quiz->questions->get(1)->options->firstWhere('is_correct')->id,
        ];

        $this->assertSame(100, $quiz->score($answers));
    }

    public function test_passes_when_score_meets_threshold(): void
    {
        $quiz = $this->quizWithThreshold(50);
        $answers = [
            $quiz->questions->get(0)->id => $quiz->questions->get(0)->options->firstWhere('is_correct')->id,
            $quiz->questions->get(1)->id => $quiz->questions->get(1)->options->where('is_correct', false)->first()->id,
        ];

        $this->assertTrue($quiz->score($answers) >= $quiz->passThreshold());
    }

    public function test_per_quiz_threshold_overrides_course_default(): void
    {
        $course = Course::factory()->create(['pass_threshold' => 70]);
        // quiz with no explicit threshold → uses course default
        // (** executed: lesson_id forced null so the factory doesn't link a
        // stray lesson from another course — threshold fallback is what's
        // under test, the lesson is irrelevant. **)
        $defaultQuiz = CourseQuiz::factory()->for($course)->create(['lesson_id' => null]);
        $this->assertSame(70, $defaultQuiz->passThreshold());

        // quiz with explicit threshold → wins
        $explicit = CourseQuiz::factory()->for($course)->create(['lesson_id' => null, 'pass_threshold' => 60]);
        $this->assertSame(60, $explicit->passThreshold());
    }

    public function test_submit_records_attempt_and_marks_lesson_complete_when_passed(): void
    {
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $quiz = CourseQuiz::factory()->for($course)->for($lesson, 'lesson')->create(['pass_threshold' => 50]);
        $q = QuizQuestion::factory()->for($quiz, 'quiz')->create();
        QuizOption::factory()->for($q, 'question')->create(['is_correct' => false]);
        QuizOption::factory()->for($q, 'question')->create(['is_correct' => true]);
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $answers = [$q->id => $q->options->firstWhere('is_correct')->id];

        $result = app(QuizEngine::class)->submit($quiz, $user, $answers);

        $this->assertSame(100, $result['score']);
        $this->assertTrue($result['passed']);
        $this->assertDatabaseHas('course_attempts', [
            'course_quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'score' => 100,
            'passed' => true,
        ]);
        $this->assertDatabaseHas('course_progress', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
        ]);
    }

    public function test_failed_attempt_does_not_mark_lesson_complete(): void
    {
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $quiz = CourseQuiz::factory()->for($course)->for($lesson, 'lesson')->create(['pass_threshold' => 90]);
        $q = QuizQuestion::factory()->for($quiz, 'quiz')->create();
        QuizOption::factory()->for($q, 'question')->create(['is_correct' => false]);
        QuizOption::factory()->for($q, 'question')->create(['is_correct' => true]);
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $answers = [$q->id => $q->options->where('is_correct', false)->first()->id];

        app(QuizEngine::class)->submit($quiz, $user, $answers);

        $this->assertDatabaseHas('course_progress', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'is_completed' => false,
        ]);
    }

    public function test_retake_records_multiple_attempts(): void
    {
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $quiz = CourseQuiz::factory()->for($course)->for($lesson, 'lesson')->create(['pass_threshold' => 50]);
        $q = QuizQuestion::factory()->for($quiz, 'quiz')->create();
        QuizOption::factory()->for($q, 'question')->create(['is_correct' => true]);
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $answers = [$q->id => $q->options->first()->id];

        app(QuizEngine::class)->submit($quiz, $user, $answers);
        app(QuizEngine::class)->submit($quiz, $user, $answers);

        $this->assertSame(2, $quiz->attempts()->where('user_id', $user->id)->count());
    }
}
