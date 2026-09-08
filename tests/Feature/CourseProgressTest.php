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

    private function quizWithCorrectAnswer(CourseQuiz $quiz): array
    {
        $question = QuizQuestion::factory()->for($quiz, 'quiz')->create();
        $option = QuizOption::factory()->for($question, 'question')->create(['is_correct' => true]);

        return [$question, $option];
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

    public function test_progress_is_isolated_per_user(): void
    {
        $userA = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $userB = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = $this->completedCourse($userA);

        $rows = app(LearningProgress::class)->forUser($userB);

        $this->assertCount(1, $rows);
        $this->assertFalse($rows->first()['is_complete']);
        $this->assertFalse(app(LearningProgress::class)->lessonStatus($userB, $course, $course->lessons->first()));
    }

    public function test_final_quiz_pass_alone_does_not_complete_lessons(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $lessonQuiz = CourseQuiz::factory()->for($course, 'course')->for($lesson, 'lesson')->create();
        [$q] = $this->quizWithCorrectAnswer($lessonQuiz);
        $finalQuiz = CourseQuiz::factory()->for($course)->create(['lesson_id' => null, 'pass_threshold' => 50]);
        [$fq] = $this->quizWithCorrectAnswer($finalQuiz);
        app(QuizEngine::class)->submit($finalQuiz, $user, [$fq->id => $fq->options->first()->id]);

        $this->assertFalse(app(LearningProgress::class)->lessonStatus($user, $course, $lesson));
        $this->assertFalse(app(LearningProgress::class)->isCourseComplete($user, $course));
    }

    public function test_course_without_final_quiz_is_never_complete(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        app(LearningProgress::class)->setLessonCompleted($user, $course, $lesson);

        $this->assertFalse(app(LearningProgress::class)->isCourseComplete($user, $course));
    }

    public function test_mixed_manual_and_quiz_lessons_complete_with_final(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $manual = CourseLesson::factory()->for($course)->create(['title' => 'Manual']);
        $quizzed = CourseLesson::factory()->for($course)->create(['title' => 'Berkuis']);
        app(LearningProgress::class)->setLessonCompleted($user, $course, $manual);
        $lessonQuiz = CourseQuiz::factory()->for($course, 'course')->for($quizzed, 'lesson')->create();
        [$q] = $this->quizWithCorrectAnswer($lessonQuiz);
        app(QuizEngine::class)->submit($lessonQuiz, $user, [$q->id => $q->options->first()->id]);
        $finalQuiz = CourseQuiz::factory()->for($course)->create(['lesson_id' => null, 'pass_threshold' => 50]);
        [$fq] = $this->quizWithCorrectAnswer($finalQuiz);
        app(QuizEngine::class)->submit($finalQuiz, $user, [$fq->id => $fq->options->first()->id]);

        $this->assertTrue(app(LearningProgress::class)->isCourseComplete($user, $course));
    }

    public function test_for_course_shape_and_lesson_ordering(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $first = CourseLesson::factory()->for($course)->create(['sort_order' => 20]);
        $second = CourseLesson::factory()->for($course)->create(['sort_order' => 10]);

        $progress = app(LearningProgress::class)->forCourse($user, $course);

        $this->assertSame($course->id, $progress['course']->id);
        $this->assertFalse($progress['is_complete']);
        $this->assertNull($progress['final_quiz']);
        $this->assertFalse($progress['final_quiz_passed']);
        $this->assertSame([$second->id, $first->id], $progress['lessons']->pluck('lesson.id')->all());
        $this->assertFalse($progress['lessons']->first()['done']);
    }

    public function test_undoing_reflects_in_for_user(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = $this->completedCourse($user);
        $lesson = $course->lessons->first();
        app(LearningProgress::class)->setLessonCompleted($user, $course, $lesson, false);

        $row = app(LearningProgress::class)->forUser($user)->first();

        $this->assertFalse($row['is_complete']);
        $this->assertSame(0, $row['lessons_done']);
    }

    public function test_report_maps_published_courses_to_all_users(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $learner = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = $this->completedCourse($learner);
        Course::factory()->unpublished()->create(['title' => 'Draf']);

        $report = app(LearningProgress::class)->report();

        $this->assertCount(1, $report);
        $this->assertSame($course->id, $report->first()['course']->id);
        $this->assertCount(2, $report->first()['rows']);
        $byId = $report->first()['rows']->keyBy(fn (array $row) => $row['user']->id);
        $this->assertTrue($byId[$learner->id]['is_complete']);
        $this->assertFalse($byId[$super->id]['is_complete']);
        $this->assertSame(1, $byId[$learner->id]['lessons_done']);
        $this->assertSame(0, $byId[$super->id]['lessons_done']);
    }
}
