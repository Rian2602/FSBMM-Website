<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use App\Support\LearningProgress;
use App\Support\QuizEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseContentSeedTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_SLUG = 'dasar-kepengurusan-serikat';

    public function test_seeding_populates_demo_course_structure(): void
    {
        $this->seed();

        $course = Course::where('slug', self::DEMO_SLUG)->firstOrFail();

        $this->assertTrue($course->is_published);
        $this->assertSame(70, $course->pass_threshold);

        $this->assertSame(2, $course->lessons()->count());
        $this->assertSame([1, 2], $course->lessons()->orderBy('sort_order')->pluck('sort_order')->all());

        $lessonQuiz = $course->lessons()->with('quizzes')->get()->pluck('quizzes')->flatten();
        $this->assertCount(1, $lessonQuiz);
        $this->assertSame(70, $lessonQuiz->first()->pass_threshold);
        $this->assertSame(1, $lessonQuiz->first()->questions()->count());
        $this->assertSame(2, $lessonQuiz->first()->questions()->first()->options()->count());

        $final = $course->quizzes()->whereNull('lesson_id')->firstOrFail();
        $this->assertSame(1, $final->questions()->count());
        $this->assertSame(2, $final->questions()->first()->options()->count());
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $course = Course::where('slug', self::DEMO_SLUG)->firstOrFail();

        $this->assertSame(1, Course::where('slug', self::DEMO_SLUG)->count());
        $this->assertSame(2, $course->lessons()->count());
        $this->assertSame(2, $course->quizzes()->count());
    }

    public function test_seeded_course_has_exactly_one_final_quiz(): void
    {
        $this->seed();

        $course = Course::where('slug', self::DEMO_SLUG)->firstOrFail();

        $this->assertSame(1, $course->quizzes()->whereNull('lesson_id')->count());
    }

    public function test_seeded_course_appears_in_learner_surfaces(): void
    {
        $org = Organization::factory()->create();
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $sba = User::factory()->sbaAdmin($org)->create();

        $this->seed();

        $this->actingAs($editor)
            ->get('/admin/my-courses')
            ->assertOk()
            ->assertSee('Dasar Kepengurusan Serikat');

        $this->actingAs($sba)
            ->get('/panel-sba/my-courses')
            ->assertOk()
            ->assertSee('Dasar Kepengurusan Serikat');
    }

    public function test_seeded_course_lists_in_public_catalog(): void
    {
        $this->seed();

        $this->get('/e-learning')
            ->assertOk()
            ->assertSee('Dasar Kepengurusan Serikat');
    }

    public function test_seeded_course_is_completable(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $this->seed();

        $course = Course::where('slug', self::DEMO_SLUG)->firstOrFail();
        $lessonQuiz = $course->lessons()->with('quizzes')->get()->pluck('quizzes')->flatten()->first();
        $final = $course->quizzes()->whereNull('lesson_id')->first();

        $manualLesson = $course->lessons()->whereDoesntHave('quizzes')->first();
        app(LearningProgress::class)->setLessonCompleted($editor, $course, $manualLesson);
        app(QuizEngine::class)->submit($lessonQuiz, $editor, [
            $lessonQuiz->questions()->first()->id => $lessonQuiz->questions()->first()->options()->where('is_correct', true)->first()->id,
        ]);
        app(QuizEngine::class)->submit($final, $editor, [
            $final->questions()->first()->id => $final->questions()->first()->options()->where('is_correct', true)->first()->id,
        ]);

        $this->assertTrue(app(LearningProgress::class)->isCourseComplete($editor, $course));
    }
}
