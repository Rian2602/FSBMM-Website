<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\CourseDetailPage;
use App\Filament\Admin\Pages\QuizViewPage;
use App\Filament\Sba\Pages\CourseDetailPage as SbaCourseDetailPage;
use App\Filament\Sba\Pages\QuizViewPage as SbaQuizViewPage;
use App\Models\Course;
use App\Models\CourseAttempt;
use App\Models\CourseLesson;
use App\Models\CourseQuiz;
use App\Models\Organization;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Support\LearningProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LearnerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_sba_admin_sees_only_published_courses_in_my_courses(): void
    {
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();
        Course::factory()->create(['title' => 'Kursus Terbit']);
        Course::factory()->unpublished()->create(['title' => 'Kursus Draf']);

        $this->actingAs($sba)
            ->get('/panel-sba/my-courses')
            ->assertOk()
            ->assertSee('Kursus Terbit')
            ->assertDontSee('Kursus Draf');
    }

    public function test_editor_sees_only_published_courses_in_my_courses(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        Course::factory()->create(['title' => 'Kursus Terbit']);
        Course::factory()->unpublished()->create(['title' => 'Kursus Draf']);

        $this->actingAs($editor)
            ->get('/admin/my-courses')
            ->assertOk()
            ->assertSee('Kursus Terbit')
            ->assertDontSee('Kursus Draf');
    }

    public function test_anonymous_is_redirected_away_from_my_courses(): void
    {
        $this->get('/panel-sba/my-courses')->assertRedirect('/panel-sba/login');
        $this->get('/admin/my-courses')->assertRedirect('/admin/login');
    }

    // (** executed: the plan's prose asks to verify the detail URLs render for
    // the right roles, so these cases cover the pretty routes registered via
    // panel authenticatedRoutes (page-level getRoutes() does not exist in
    // Filament v3.3.55). **)
    public function test_course_detail_shows_lessons_for_published_course(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create(['title' => 'Kursus Detail', 'slug' => 'kursus-detail']);
        CourseLesson::factory()->for($course)->create(['title' => 'Pelajaran Satu', 'sort_order' => 1]);

        $this->actingAs($editor)
            ->get('/admin/courses/kursus-detail')
            ->assertOk()
            ->assertSee('Pelajaran Satu');
    }

    public function test_course_detail_is_unavailable_for_draft_course(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        Course::factory()->unpublished()->create(['title' => 'Kursus Draf', 'slug' => 'kursus-draf']);

        $this->actingAs($editor)
            ->get('/admin/courses/kursus-draf')
            ->assertNotFound();
    }

    public function test_sba_course_detail_and_lesson_view_render(): void
    {
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();
        $course = Course::factory()->create(['title' => 'Kursus SBA', 'slug' => 'kursus-sba']);
        $lesson = CourseLesson::factory()->for($course)->create(['title' => 'Materi Satu']);

        $this->actingAs($sba)
            ->get('/panel-sba/courses/kursus-sba')
            ->assertOk()
            ->assertSee('Materi Satu');

        $this->actingAs($sba)
            ->get('/panel-sba/courses/kursus-sba/lessons/' . $lesson->id)
            ->assertOk()
            ->assertSee('Materi Satu');
    }

    // (** executed: spec §6b — lesson without a quiz gets a manual
    // "Tandai Selesai"/"Batal Selesai" toggle; Task 5's view omitted it and
    // the Task 9 smoke proved course completion was unreachable for quiz-less
    // lessons. **)
    public function test_lesson_without_quiz_can_be_toggled_done_from_course_detail(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create(['slug' => 'kursus-toggle']);
        $lesson = CourseLesson::factory()->for($course)->create(['title' => 'Pelajaran Manual']);

        Livewire::actingAs($editor)
            ->test(CourseDetailPage::class, ['record' => $course])
            ->call('toggleLessonCompletion', $lesson->id);

        $this->assertDatabaseHas('course_progress', [
            'user_id' => $editor->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
        ]);

        // toggling again undoes the completion
        Livewire::actingAs($editor)
            ->test(CourseDetailPage::class, ['record' => $course])
            ->call('toggleLessonCompletion', $lesson->id);

        $this->assertDatabaseHas('course_progress', [
            'user_id' => $editor->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'is_completed' => false,
        ]);
    }

    // (** executed: task 5 evaluation — the toggle is only legal for lessons
    // WITHOUT a quiz (spec §6b); the blade hides the button for quiz lessons
    // but the method accepted any lesson, letting a crafted Livewire call mark
    // a quiz-bearing lesson done without passing its quiz. LearningStatus()
    // prefers the progress row over quiz attempts, so this forged completion
    // also flips course completion. The guard must be server-side. **)
    public function test_lesson_with_quiz_cannot_be_toggled_done_from_course_detail(): void
    {
        $this->withoutExceptionHandling();

        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create(['slug' => 'kursus-guard']);
        $lesson = CourseLesson::factory()->for($course)->create(['title' => 'Berkuis']);
        CourseQuiz::factory()->for($course, 'course')->for($lesson, 'lesson')->create(['title' => 'Kuis 1']);

        $this->assertThrows(
            fn () => Livewire::actingAs($editor)
                ->test(CourseDetailPage::class, ['record' => $course])
                ->call('toggleLessonCompletion', $lesson->id),
            HttpException::class,
        );

        $this->assertDatabaseMissing('course_progress', [
            'user_id' => $editor->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    // (** executed: task 5 evaluation — the detail page must render every
    // quiz of a lesson; the view only linked $lesson->quizzes->first(), so
    // quizzes 2..n were unreachable from the learning surface. **)
    public function test_course_detail_links_each_quiz_of_a_lesson(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create(['slug' => 'kursus-duakuis', 'title' => 'Dua Kuis']);
        $lesson = CourseLesson::factory()->for($course)->create(['title' => 'Pelajaran Kuis']);
        $k1 = CourseQuiz::factory()->for($course, 'course')->for($lesson, 'lesson')->create(['title' => 'Kuis Satu']);
        $k2 = CourseQuiz::factory()->for($course, 'course')->for($lesson, 'lesson')->create(['title' => 'Kuis Dua']);

        $this->actingAs($editor)
            ->get('/admin/courses/kursus-duakuis')
            ->assertOk()
            ->assertSee('/admin/quizzes/' . $k1->id, false)
            ->assertSee('/admin/quizzes/' . $k2->id, false);
    }

    public function test_course_detail_shows_lesson_statuses_and_quiz_button(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create(['slug' => 'kursus-status', 'title' => 'Kursus Status']);
        CourseLesson::factory()->for($course)->create(['title' => 'Pelajaran Tanpa Kuis']);
        $quizzed = CourseLesson::factory()->for($course)->create(['title' => 'Pelajaran Berkuis']);
        CourseQuiz::factory()->for($course, 'course')->for($quizzed, 'lesson')->create(['title' => 'Kuis Pelajaran']);

        $this->actingAs($editor)
            ->get('/admin/courses/kursus-status')
            ->assertOk()
            ->assertSee('Pelajaran Tanpa Kuis')
            ->assertSee('Pelajaran Berkuis')
            ->assertSee('Belum')
            ->assertSee('Kuis belum lulus')
            ->assertSee('Tandai Selesai')
            ->assertSee('Kerjakan Kuis');
    }

    public function test_course_detail_shows_final_quiz_entry_and_flips_when_passed(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create(['slug' => 'kursus-final', 'title' => 'Kursus Final']);
        $final = CourseQuiz::factory()->for($course)->create(['lesson_id' => null, 'title' => 'Evaluasi Akhir']);

        $this->actingAs($editor)
            ->get('/admin/courses/kursus-final')
            ->assertOk()
            ->assertSee('Kuis Akhir')
            ->assertSee('kerjakan setelah semua pelajaran selesai');

        CourseAttempt::create([
            'course_quiz_id' => $final->id,
            'user_id' => $editor->id,
            'score' => 100,
            'passed' => true,
            'attempt_date' => now(),
        ]);

        $this->actingAs($editor)
            ->get('/admin/courses/kursus-final')
            ->assertSee('lulus')
            ->assertDontSee('kerjakan setelah semua pelajaran selesai');
    }

    public function test_lesson_view_renders_lesson_content(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create(['slug' => 'kursus-konten', 'title' => 'Kursus Konten']);
        $lesson = CourseLesson::factory()->for($course)->create([
            'title' => 'Materi X',
            'content' => '<h2>Header Bab</h2><p>Isi paragraf.</p>',
        ]);

        $this->actingAs($editor)
            ->get('/admin/courses/kursus-konten/lessons/' . $lesson->id)
            ->assertOk()
            ->assertSee('Header Bab');
    }

    // (** executed: 88299e2 self-evaluation — the quiz-less toggle guard was
    // installed on the Admin twin only; the Sba CourseDetailPage still had the
    // unguarded method, so sba_admin could forge completion of a quiz-bearing
    // lesson. Parity probes below. **)
    public function test_sba_admin_can_toggle_quiz_less_lesson_from_course_detail(): void
    {
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();
        $course = Course::factory()->create(['slug' => 'kursus-sba-toggle']);
        $lesson = CourseLesson::factory()->for($course)->create(['title' => 'Pelajaran Manual']);

        Livewire::actingAs($sba)
            ->test(SbaCourseDetailPage::class, ['record' => $course])
            ->call('toggleLessonCompletion', $lesson->id);

        $this->assertDatabaseHas('course_progress', [
            'user_id' => $sba->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
        ]);
    }

    public function test_sba_admin_cannot_toggle_quiz_bearing_lesson(): void
    {
        $this->withoutExceptionHandling();

        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();
        $course = Course::factory()->create(['slug' => 'kursus-sba-guard']);
        $lesson = CourseLesson::factory()->for($course)->create(['title' => 'Berkuis']);
        CourseQuiz::factory()->for($course, 'course')->for($lesson, 'lesson')->create(['title' => 'Kuis 1']);

        $this->assertThrows(
            fn () => Livewire::actingAs($sba)
                ->test(SbaCourseDetailPage::class, ['record' => $course])
                ->call('toggleLessonCompletion', $lesson->id),
            HttpException::class,
        );

        $this->assertDatabaseMissing('course_progress', [
            'user_id' => $sba->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    // (** executed: 88299e2 self-evaluation — the toggle must flip the
    // rendered state on the re-render, not just write the DB row. **)
    public function test_toggle_updates_rendered_completion_state(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create(['slug' => 'kursus-rerender']);
        $lesson = CourseLesson::factory()->for($course)->create(['title' => 'Pelajaran Manual']);

        Livewire::actingAs($editor)
            ->test(CourseDetailPage::class, ['record' => $course])
            ->call('toggleLessonCompletion', $lesson->id)
            ->assertSee('Batal Selesai');

        Livewire::actingAs($editor)
            ->test(CourseDetailPage::class, ['record' => $course])
            ->call('toggleLessonCompletion', $lesson->id)
            ->assertSee('Tandai Selesai');
    }

    // (** executed: task 6 evaluation — QuizViewPage had zero coverage (the
    // same crash class Task 3/Task 4 fixed for other pages); these lock the
    // taking flow across both panels. **)
    public function test_admin_can_take_and_pass_a_lesson_quiz(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create(['slug' => 'kursus-kuis']);
        $lesson = CourseLesson::factory()->for($course)->create(['title' => 'Pelajaran']);
        $quiz = CourseQuiz::factory()->for($course, 'course')->for($lesson, 'lesson')->create(['pass_threshold' => 50]);
        $q = QuizQuestion::factory()->for($quiz, 'quiz')->create(['question' => 'Siapa budak?']);
        QuizOption::factory()->for($q, 'question')->create(['option' => 'Salah', 'is_correct' => false]);
        QuizOption::factory()->for($q, 'question')->create(['option' => 'Benar', 'is_correct' => true]);

        Livewire::actingAs($editor)
            ->test(QuizViewPage::class, ['record' => $quiz])
            ->set('answers', [$q->id => $q->options->firstWhere('is_correct')->id])
            ->call('submit')
            ->assertSet('result.passed', true)
            ->assertSet('result.score', 100)
            ->assertSee('Lulus');

        $this->assertDatabaseHas('course_attempts', [
            'course_quiz_id' => $quiz->id,
            'user_id' => $editor->id,
            'score' => 100,
            'passed' => true,
        ]);
        $this->assertDatabaseHas('course_progress', [
            'user_id' => $editor->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
        ]);
    }

    public function test_quiz_page_rejects_submission_with_unanswered_question(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $quiz = CourseQuiz::factory()->for($course, 'course')->for($lesson, 'lesson')->create();
        $q1 = QuizQuestion::factory()->for($quiz, 'quiz')->create();
        QuizOption::factory()->for($q1, 'question')->create(['option' => 'A', 'is_correct' => false]);
        QuizOption::factory()->for($q1, 'question')->create(['option' => 'B', 'is_correct' => true]);
        $q2 = QuizQuestion::factory()->for($quiz, 'quiz')->create();
        QuizOption::factory()->for($q2, 'question')->create(['option' => 'C', 'is_correct' => false]);
        QuizOption::factory()->for($q2, 'question')->create(['option' => 'D', 'is_correct' => true]);

        Livewire::actingAs($editor)
            ->test(QuizViewPage::class, ['record' => $quiz])
            ->set('answers', [$q1->id => $q1->options->firstWhere('is_correct')->id, $q2->id => null])
            ->call('submit')
            ->assertSet('result', null);

        $this->assertDatabaseCount('course_attempts', 0);
    }

    public function test_quiz_page_returns_404_for_draft_course_quiz(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->unpublished()->create();
        $quiz = CourseQuiz::factory()->for($course)->create(['lesson_id' => null]);

        $this->actingAs($editor)
            ->get('/admin/quizzes/' . $quiz->id)
            ->assertNotFound();
    }

    public function test_sba_quiz_page_renders_and_submits(): void
    {
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();
        $course = Course::factory()->create(['slug' => 'kursus-sba-kuis']);
        $quiz = CourseQuiz::factory()->for($course)->create(['lesson_id' => null, 'pass_threshold' => 50]);
        $q = QuizQuestion::factory()->for($quiz, 'quiz')->create();
        QuizOption::factory()->for($q, 'question')->create(['option' => 'Salah', 'is_correct' => false]);
        QuizOption::factory()->for($q, 'question')->create(['option' => 'Benar', 'is_correct' => true]);

        $this->actingAs($sba)
            ->get('/panel-sba/quizzes/' . $quiz->id)
            ->assertOk()
            ->assertSee('Benar');

        Livewire::actingAs($sba)
            ->test(SbaQuizViewPage::class, ['record' => $quiz])
            ->set('answers', [$q->id => $q->options->firstWhere('is_correct')->id])
            ->call('submit')
            ->assertSet('result.passed', true);

        $this->assertDatabaseHas('course_attempts', [
            'course_quiz_id' => $quiz->id,
            'user_id' => $sba->id,
            'passed' => true,
        ]);
    }

    public function test_quiz_result_shows_retake_when_failed(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $quiz = CourseQuiz::factory()->for($course, 'course')->for($lesson, 'lesson')->create(['pass_threshold' => 90]);
        $q = QuizQuestion::factory()->for($quiz, 'quiz')->create();
        QuizOption::factory()->for($q, 'question')->create(['option' => 'Bencana', 'is_correct' => false]);
        QuizOption::factory()->for($q, 'question')->create(['option' => 'Benar', 'is_correct' => true]);

        Livewire::actingAs($editor)
            ->test(QuizViewPage::class, ['record' => $quiz])
            ->set('answers', [$q->id => $q->options->firstWhere('is_correct', false)->id])
            ->call('submit')
            ->assertSet('result.passed', false)
            ->assertSee('Belum lulus')
            ->assertSee('Ulangi kuis');
    }

    public function test_sba_admin_can_submit_lesson_quiz(): void
    {
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $quiz = CourseQuiz::factory()->for($course, 'course')->for($lesson, 'lesson')->create(['pass_threshold' => 50]);
        $q = QuizQuestion::factory()->for($quiz, 'quiz')->create();
        QuizOption::factory()->for($q, 'question')->create(['option' => 'Salah', 'is_correct' => false]);
        QuizOption::factory()->for($q, 'question')->create(['option' => 'Benar', 'is_correct' => true]);

        Livewire::actingAs($sba)
            ->test(SbaQuizViewPage::class, ['record' => $quiz])
            ->set('answers', [$q->id => $q->options->firstWhere('is_correct')->id])
            ->call('submit')
            ->assertSet('result.passed', true);

        $this->assertDatabaseHas('course_attempts', [
            'course_quiz_id' => $quiz->id,
            'user_id' => $sba->id,
            'passed' => true,
        ]);
        $this->assertDatabaseHas('course_progress', [
            'user_id' => $sba->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
        ]);
    }

    public function test_my_courses_shows_completion_badge_only_when_course_complete(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create(['slug' => 'kursus-badge', 'title' => 'Kursus Badge']);
        $lesson = CourseLesson::factory()->for($course)->create(['title' => 'Pelajaran']);
        $final = CourseQuiz::factory()->for($course)->create(['lesson_id' => null]);

        $this->actingAs($editor)
            ->get('/admin/my-courses')
            ->assertOk()
            ->assertDontSee('✓ Selesai');

        app(LearningProgress::class)->setLessonCompleted($editor, $course, $lesson);
        CourseAttempt::create([
            'course_quiz_id' => $final->id,
            'user_id' => $editor->id,
            'score' => 100,
            'passed' => true,
            'attempt_date' => now(),
        ]);

        $this->actingAs($editor)
            ->get('/admin/my-courses')
            ->assertOk()
            ->assertSee('✓ Selesai');
    }

    public function test_my_courses_shows_completion_badge_for_sba_admin(): void
    {
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();
        $course = Course::factory()->create(['title' => 'Kursus SBA Badge']);
        $lesson = CourseLesson::factory()->for($course)->create();
        $final = CourseQuiz::factory()->for($course)->create(['lesson_id' => null]);
        app(LearningProgress::class)->setLessonCompleted($sba, $course, $lesson);
        CourseAttempt::create([
            'course_quiz_id' => $final->id,
            'user_id' => $sba->id,
            'score' => 100,
            'passed' => true,
            'attempt_date' => now(),
        ]);

        $this->actingAs($sba)
            ->get('/panel-sba/my-courses')
            ->assertOk()
            ->assertSee('✓ Selesai');
    }

    public function test_course_detail_hints_when_final_quiz_is_missing(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create(['slug' => 'kursus-tanpa-final']);

        $this->actingAs($editor)
            ->get('/admin/courses/kursus-tanpa-final')
            ->assertOk()
            ->assertSee('Kuis akhir belum tersedia');
    }

    public function test_sba_course_detail_hints_when_final_quiz_is_missing(): void
    {
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();
        $course = Course::factory()->create(['slug' => 'kursus-sba-tanpa-final']);

        $this->actingAs($sba)
            ->get('/panel-sba/courses/kursus-sba-tanpa-final')
            ->assertOk()
            ->assertSee('Kuis akhir belum tersedia');
    }
}
