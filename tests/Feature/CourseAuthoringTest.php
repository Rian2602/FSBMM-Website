<?php

namespace Tests\Feature;

use App\Filament\Resources\CourseLessonResource\Pages\CreateCourseLesson;
use App\Filament\Resources\CourseLessonResource\Pages\EditCourseLesson;
use App\Filament\Resources\CourseLessonResource\RelationManagers\QuizzesRelationManager;
use App\Filament\Resources\CourseQuizResource\Pages\CreateCourseQuiz;
use App\Filament\Resources\CourseQuizResource\Pages\EditCourseQuiz;
use App\Filament\Resources\CourseQuizResource\RelationManagers\QuestionsRelationManager;
use App\Filament\Resources\CourseResource\Pages\EditCourse;
use App\Filament\Resources\CourseResource\RelationManagers\FinalQuizRelationManager;
use App\Filament\Resources\CourseResource\RelationManagers\LessonsRelationManager;
use App\Filament\Resources\QuizQuestionResource\Pages\EditQuizQuestion;
use App\Filament\Resources\QuizQuestionResource\RelationManagers\OptionsRelationManager;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseQuiz;
use App\Models\Organization;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourseAuthoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    // (** executed: no cross-tenant 404 test here — course content is NOT
    // tenant-scoped (SP4 spec §3): lessons are global federation content, so
    // the SP3-style foreign-tenant 404 does not apply. **)
    public function test_sba_admin_cannot_access_lesson_resource(): void
    {
        $sba = User::factory()->sbaAdmin(Organization::factory()->create())->create();

        $this->actingAs($sba)->get('/admin/course-lessons')->assertForbidden();
    }

    // (** executed: the plan's snippet mounted CreateCourseLesson with
    // ['record' => ...] and omitted course_id, but CreateRecord pages take no
    // record param (repo convention) and course_id is a required select — the
    // test must fill it. **)
    public function test_editor_can_create_a_lesson_on_a_course(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();

        Livewire::actingAs($editor)
            ->test(CreateCourseLesson::class)
            ->fillForm([
                'course_id' => $course->id,
                'title' => 'Pelajaran Perkenalan',
                'content' => '<p>Isi materi.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('course_lessons', [
            'course_id' => $course->id,
            'title' => 'Pelajaran Perkenalan',
        ]);
    }

    public function test_lesson_requires_title_and_content(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();

        Livewire::actingAs($editor)
            ->test(CreateCourseLesson::class)
            ->fillForm([
                'course_id' => $course->id,
                'title' => '',
                'content' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['title', 'content']);
    }

    // (** executed: the plan's Task 3 test snippets omitted pageClass, but
    // relation-manager tests in this repo always mount with
    // ['ownerRecord' => ..., 'pageClass' => ...] (SP3 SbaEventAttendanceTest,
    // and the Task 2 evaluation proved the pageClass-less mount crashes with
    // a null getPageClass()). **)
    public function test_editor_can_create_a_question_on_a_quiz(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $quiz = CourseQuiz::factory()->create();

        Livewire::actingAs($editor)
            ->test(QuestionsRelationManager::class, ['ownerRecord' => $quiz, 'pageClass' => EditCourseQuiz::class])
            ->callTableAction('create', data: ['question' => 'Apa itu serikat pekerja?'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('quiz_questions', [
            'course_quiz_id' => $quiz->id,
            'question' => 'Apa itu serikat pekerja?',
        ]);
    }

    public function test_editor_can_create_an_option_and_mark_it_correct(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $question = QuizQuestion::factory()->create();

        Livewire::actingAs($editor)
            ->test(OptionsRelationManager::class, ['ownerRecord' => $question, 'pageClass' => EditQuizQuestion::class])
            ->callTableAction('create', data: ['option' => 'Jawaban Benar', 'is_correct' => true])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('quiz_options', [
            'quiz_question_id' => $question->id,
            'option' => 'Jawaban Benar',
            'is_correct' => true,
        ]);
    }

    public function test_question_requires_text(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $quiz = CourseQuiz::factory()->create();

        Livewire::actingAs($editor)
            ->test(QuestionsRelationManager::class, ['ownerRecord' => $quiz, 'pageClass' => EditCourseQuiz::class])
            ->callTableAction('create', data: ['question' => ''])
            ->assertHasTableActionErrors(['question']);
    }

    // (** executed: plan snippet mounted the RM without pageClass — always
    // required in this repo (see Task 3 note). **)
    public function test_editor_can_create_a_final_quiz_on_a_course(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();

        Livewire::actingAs($editor)
            ->test(FinalQuizRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class])
            ->callTableAction('create', data: ['title' => 'Evaluasi Akhir', 'pass_threshold' => 70])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('course_quizzes', [
            'course_id' => $course->id,
            'lesson_id' => null,
            'title' => 'Evaluasi Akhir',
        ]);
    }

    public function test_lesson_relation_manager_table_renders(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();

        Livewire::actingAs($editor)
            ->test(LessonsRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$lesson]);
    }

    public function test_editor_can_add_a_quiz_through_the_lesson_relation_manager(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $otherCourse = Course::factory()->create();

        Livewire::actingAs($editor)
            ->test(QuizzesRelationManager::class, ['ownerRecord' => $lesson, 'pageClass' => EditCourseLesson::class])
            ->callTableAction('create', data: [
                'title' => 'Kuis Pelajaran',
                'pass_threshold' => null,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('course_quizzes', [
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'title' => 'Kuis Pelajaran',
            'pass_threshold' => null,
        ]);
        $this->assertDatabaseMissing('course_quizzes', ['course_id' => $otherCourse->id]);
    }

    public function test_editor_can_add_a_lesson_through_the_course_relation_manager(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();

        Livewire::actingAs($editor)
            ->test(LessonsRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class])
            ->callTableAction('create', data: [
                'title' => 'Pelajaran RM',
                'content' => '<p>Isi.</p>',
                'sort_order' => 3,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('course_lessons', [
            'course_id' => $course->id,
            'title' => 'Pelajaran RM',
            'sort_order' => 3,
        ]);
    }

    public function test_lesson_list_page_renders_for_editor(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($editor)->get('/admin/course-lessons')->assertSuccessful();
    }

    // (** executed: Task 3 evaluation (C-T3-1) — spec §7 requires that the
    // authoring UI keeps exactly one correct option per question; the shipped
    // RM was a plain toggle. score() uses firstWhere('is_correct', true), so a
    // second key would be silently ignored and deleting the only key makes the
    // question permanently count as wrong. Guards live in the RM actions. **)
    public function test_cannot_mark_a_second_option_correct(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $question = QuizQuestion::factory()->create();
        QuizOption::factory()->create(['quiz_question_id' => $question->id, 'is_correct' => true]);

        Livewire::actingAs($editor)
            ->test(OptionsRelationManager::class, ['ownerRecord' => $question, 'pageClass' => EditQuizQuestion::class])
            ->callTableAction('create', data: ['option' => 'Kunci Kedua', 'is_correct' => true]);

        $this->assertDatabaseCount('quiz_options', 1);
        $this->assertDatabaseMissing('quiz_options', ['option' => 'Kunci Kedua']);
    }

    public function test_cannot_delete_the_only_correct_option(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $question = QuizQuestion::factory()->create();
        $option = QuizOption::factory()->create(['quiz_question_id' => $question->id, 'is_correct' => true]);

        Livewire::actingAs($editor)
            ->test(OptionsRelationManager::class, ['ownerRecord' => $question, 'pageClass' => EditQuizQuestion::class])
            ->callTableAction('delete', $option->id);

        $this->assertDatabaseCount('quiz_options', 1);
        $this->assertDatabaseHas('quiz_options', ['id' => $option->id, 'is_correct' => true]);
    }

    public function test_editing_an_option_can_keep_it_as_the_correct_one(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $question = QuizQuestion::factory()->create();
        $option = QuizOption::factory()->create(['quiz_question_id' => $question->id, 'is_correct' => true]);

        Livewire::actingAs($editor)
            ->test(OptionsRelationManager::class, ['ownerRecord' => $question, 'pageClass' => EditQuizQuestion::class])
            ->callTableAction('edit', $option->id, data: ['option' => 'Jawaban Benar (revisi)', 'is_correct' => true])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('quiz_options', [
            'id' => $option->id,
            'option' => 'Jawaban Benar (revisi)',
            'is_correct' => true,
        ]);
    }

    public function test_can_mark_a_new_option_correct_after_unchecking_the_previous_key(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $question = QuizQuestion::factory()->create();
        $old = QuizOption::factory()->create(['quiz_question_id' => $question->id, 'is_correct' => true]);

        Livewire::actingAs($editor)
            ->test(OptionsRelationManager::class, ['ownerRecord' => $question, 'pageClass' => EditQuizQuestion::class])
            ->callTableAction('edit', $old->id, data: ['option' => $old->option, 'is_correct' => false])
            ->callTableAction('create', data: ['option' => 'Kunci Baru', 'is_correct' => true])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('quiz_options', ['id' => $old->id, 'is_correct' => true]);
        $this->assertDatabaseHas('quiz_options', ['option' => 'Kunci Baru', 'is_correct' => true]);
    }

    // (** executed: Task 3 evaluation (C-T3-2) — CourseQuizResource disables
    // course_id on edit but leaves lesson_id editable, so without re-deriving
    // on save an editor could move a quiz to a lesson of another course and
    // break the create invariant (course from lesson) that d4c5bc5 enforced. **)
    public function test_editing_a_quiz_lesson_to_another_course_rebinds_the_course(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $quiz = CourseQuiz::factory()->create();
        $otherLesson = CourseLesson::factory()->create();

        $this->assertNotEquals($quiz->course_id, $otherLesson->course_id);

        Livewire::actingAs($editor)
            ->test(EditCourseQuiz::class, ['record' => $quiz->id])
            ->fillForm(['lesson_id' => $otherLesson->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $quiz->refresh();
        $this->assertEquals($otherLesson->course_id, $quiz->course_id);
        $this->assertEquals($otherLesson->id, $quiz->lesson_id);
    }

    // (** executed: Task 3 evaluation (C-T3-3) — the standalone quiz create
    // path carrying d4c5bc5's fix had zero coverage. The lesson must always
    // win over the manually picked course, exactly like CreateCourseQuiz. **)
    public function test_creating_a_quiz_via_standalone_page_derives_course_from_lesson(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $lesson = CourseLesson::factory()->create();
        $otherCourse = Course::factory()->create();

        Livewire::actingAs($editor)
            ->test(CreateCourseQuiz::class)
            ->fillForm(['title' => 'Kuis Standalone', 'course_id' => $otherCourse->id, 'lesson_id' => $lesson->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('course_quizzes', [
            'title' => 'Kuis Standalone',
            'course_id' => $lesson->course_id,
            'lesson_id' => $lesson->id,
        ]);
        $this->assertDatabaseMissing('course_quizzes', ['title' => 'Kuis Standalone', 'course_id' => $otherCourse->id]);
    }

    public function test_creating_a_final_quiz_via_standalone_page_allows_empty_lesson(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();

        Livewire::actingAs($editor)
            ->test(CreateCourseQuiz::class)
            ->fillForm(['title' => 'Kuis Akhir', 'course_id' => $course->id, 'lesson_id' => null])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('course_quizzes', [
            'title' => 'Kuis Akhir',
            'course_id' => $course->id,
            'lesson_id' => null,
        ]);
    }

    // (** executed: Task 3 evaluation (C-T3-3) — smoke-render the standalone
    // quiz/question pages, the same class of crash d4c5bc5 fixed (list/create
    // pages were never rendered by any test). **)
    public function test_quiz_list_page_renders_for_editor(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        CourseQuiz::factory()->create();

        $this->actingAs($editor)->get('/admin/course-quizzes')->assertSuccessful();
    }

    public function test_quiz_question_list_page_renders_for_editor(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        QuizQuestion::factory()->create();

        $this->actingAs($editor)->get('/admin/quiz-questions')->assertSuccessful();
    }

    public function test_quiz_create_page_renders_for_editor(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($editor)->get('/admin/course-quizzes/create')->assertSuccessful();
    }

    public function test_quiz_question_create_page_renders_for_editor(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($editor)->get('/admin/quiz-questions/create')->assertSuccessful();
    }

    public function test_quiz_edit_page_renders_for_editor(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $quiz = CourseQuiz::factory()->create();

        $this->actingAs($editor)->get("/admin/course-quizzes/{$quiz->id}/edit")->assertSuccessful();
    }

    public function test_quiz_question_edit_page_renders_for_editor(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $question = QuizQuestion::factory()->create();

        $this->actingAs($editor)->get("/admin/quiz-questions/{$question->id}/edit")->assertSuccessful();
    }
}
