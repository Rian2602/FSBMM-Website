<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseQuiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Support\LearningProgress;
use App\Support\QuizEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseProgressTest extends TestCase
{
    use RefreshDatabase;

    private function completedCourse(User $user): Course
    {
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $finalQuiz = CourseQuiz::factory()->for($course)->create(['lesson_id' => null, 'pass_threshold' => 50]);
        // (** executed: `->for()` must name the relation explicitly — the
        // factory infers `courseQuiz`/`quizQuestion` from the class name but
        // the relations are `quiz`/`question` (Task 1 evaluation finding). **)
        $q = QuizQuestion::factory()->for($finalQuiz, 'quiz')->create();
        QuizOption::factory()->for($q, 'question')->create(['is_correct' => true]);

        // lesson with no quiz → manual complete
        app(LearningProgress::class)->setLessonCompleted($user, $course, $lesson);
        // final quiz passed
        app(QuizEngine::class)->submit($finalQuiz, $user, [$q->id => $q->options->first()->id]);

        return $course;
    }

    public function test_course_is_complete_when_lessons_done_and_final_quiz_passed(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = $this->completedCourse($user);

        $this->assertTrue(app(LearningProgress::class)->isCourseComplete($user, $course));
    }

    public function test_course_is_not_complete_without_final_quiz_pass(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        app(LearningProgress::class)->setLessonCompleted($user, $course, $lesson);
        CourseQuiz::factory()->for($course)->create(['lesson_id' => null]); // final quiz, never passed

        $this->assertFalse(app(LearningProgress::class)->isCourseComplete($user, $course));
    }

    public function test_course_is_not_complete_with_unfinished_lesson(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        CourseLesson::factory()->for($course)->create(); // not completed
        $finalQuiz = CourseQuiz::factory()->for($course)->create(['lesson_id' => null, 'pass_threshold' => 50]);
        $q = QuizQuestion::factory()->for($finalQuiz, 'quiz')->create();
        QuizOption::factory()->for($q, 'question')->create(['is_correct' => true]);
        app(QuizEngine::class)->submit($finalQuiz, $user, [$q->id => $q->options->first()->id]);

        $this->assertFalse(app(LearningProgress::class)->isCourseComplete($user, $course));
    }

    public function test_undoing_lesson_completion_degrades_course_status(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = $this->completedCourse($user);
        $lesson = $course->lessons->first();
        app(LearningProgress::class)->setLessonCompleted($user, $course, $lesson, false);

        $this->assertFalse(app(LearningProgress::class)->isCourseComplete($user, $course));
    }

    public function test_for_user_lists_only_published_courses_with_status(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $done = $this->completedCourse($user);
        Course::factory()->unpublished()->create(['title' => 'Draf']);

        $rows = app(LearningProgress::class)->forUser($user);

        $this->assertCount(1, $rows);
        $this->assertSame($done->id, $rows->first()['course']->id);
        $this->assertTrue($rows->first()['is_complete']);
    }
}
