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
use App\Filament\Resources\FinalQuizResource\Pages\CreateFinalQuiz;
use App\Filament\Resources\FinalQuizResource\Pages\EditFinalQuiz;
use App\Filament\Resources\FinalQuizResource\Pages\ListFinalQuizzes;
use App\Filament\Resources\FinalQuizResource\RelationManagers\QuestionsRelationManager as FinalQuizQuestionsRelationManager;
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

    // (** executed: evaluation probes closing the remaining guard
    // branches of C-T3-1: edit-other-to-correct must halt (ignore-id path with
    // a conflicting key), and wrong-option create/delete must stay allowed. **)
    public function test_cannot_mark_an_edited_option_correct_while_another_key_exists(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $question = QuizQuestion::factory()->create();
        QuizOption::factory()->create(['quiz_question_id' => $question->id, 'is_correct' => true]);
        $other = QuizOption::factory()->create(['quiz_question_id' => $question->id, 'is_correct' => false]);

        Livewire::actingAs($editor)
            ->test(OptionsRelationManager::class, ['ownerRecord' => $question, 'pageClass' => EditQuizQuestion::class])
            ->callTableAction('edit', $other->id, data: ['option' => $other->option, 'is_correct' => true]);

        $this->assertDatabaseHas('quiz_options', ['id' => $other->id, 'is_correct' => false]);
    }

    public function test_deleting_a_non_key_option_is_allowed(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $question = QuizQuestion::factory()->create();
        $key = QuizOption::factory()->create(['quiz_question_id' => $question->id, 'is_correct' => true]);
        $other = QuizOption::factory()->create(['quiz_question_id' => $question->id, 'is_correct' => false]);

        Livewire::actingAs($editor)
            ->test(OptionsRelationManager::class, ['ownerRecord' => $question, 'pageClass' => EditQuizQuestion::class])
            ->callTableAction('delete', $other->id)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseCount('quiz_options', 1);
        $this->assertDatabaseHas('quiz_options', ['id' => $key->id, 'is_correct' => true]);
    }

    public function test_creating_a_wrong_option_when_a_key_exists_is_allowed(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $question = QuizQuestion::factory()->create();
        QuizOption::factory()->create(['quiz_question_id' => $question->id, 'is_correct' => true]);

        Livewire::actingAs($editor)
            ->test(OptionsRelationManager::class, ['ownerRecord' => $question, 'pageClass' => EditQuizQuestion::class])
            ->callTableAction('create', data: ['option' => 'Opsi Pengecoh', 'is_correct' => false])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseCount('quiz_options', 2);
        $this->assertDatabaseHas('quiz_options', ['option' => 'Opsi Pengecoh', 'is_correct' => false]);
    }

    // (** executed: evaluation probe for C-T3-2's reverse transition — turning
    // a lesson quiz into a final quiz (lesson_id → null) must keep the course,
    // since mutateFormDataBeforeSave only re-derives when a lesson is set. **)
    public function test_editing_a_quiz_to_blank_lesson_keeps_its_course(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $quiz = CourseQuiz::factory()->create();
        $originalCourseId = $quiz->course_id;

        Livewire::actingAs($editor)
            ->test(EditCourseQuiz::class, ['record' => $quiz->id])
            ->fillForm(['lesson_id' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $quiz->refresh();
        $this->assertNull($quiz->lesson_id);
        $this->assertEquals($originalCourseId, $quiz->course_id);
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

    // (** executed: Task 4 evaluation (F4-1) — authoring allowed unlimited
    // lesson_id-null quizzes per course, but completion resolves via
    // Course::finalQuiz()->first(), making any second final dead weight. Keep
    // at most one final per course at both Task 4 authoring surfaces. **)
    public function test_cannot_create_a_second_final_quiz_via_relation_manager(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        CourseQuiz::factory()->for($course)->create(['lesson_id' => null]);

        Livewire::actingAs($editor)
            ->test(FinalQuizRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class])
            ->callTableAction('create', data: ['title' => 'Kuis Akhir Kedua', 'pass_threshold' => 70]);

        $this->assertDatabaseMissing('course_quizzes', ['title' => 'Kuis Akhir Kedua']);
    }

    public function test_cannot_create_a_second_final_quiz_via_standalone_resource(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        CourseQuiz::factory()->for($course)->create(['lesson_id' => null]);

        Livewire::actingAs($editor)
            ->test(CreateFinalQuiz::class)
            ->fillForm(['title' => 'Kuis Akhir Kedua', 'course_id' => $course->id])
            ->call('create')
            ->assertHasFormErrors(['course_id']);
    }

    // (** executed: Task 4 evaluation (F4-3) — FinalQuizResource pages and its
    // own Questions RM were completely untested (the crash class d4c5bc5/C-T3-3
    // fixed in Task 3); these smoke the full standalone surface plus the
    // scoped-query boundary (a lesson quiz must NOT be editable as a final). **)
    public function test_final_quiz_list_page_renders_for_editor(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        CourseQuiz::factory()->for(Course::factory()->create())->create(['lesson_id' => null]);

        $this->actingAs($editor)->get('/admin/final-quizzes')->assertSuccessful();
    }

    public function test_final_quiz_create_page_renders_for_editor(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($editor)->get('/admin/final-quizzes/create')->assertSuccessful();
    }

    public function test_final_quiz_edit_page_renders_for_editor(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $finalQuiz = CourseQuiz::factory()->for($course)->create(['lesson_id' => null]);

        $this->actingAs($editor)->get("/admin/final-quizzes/{$finalQuiz->id}/edit")->assertSuccessful();
    }

    public function test_lesson_quiz_is_not_editable_as_final_quiz(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $lessonQuiz = CourseQuiz::factory()->for(CourseLesson::factory(), 'lesson')->create();

        $this->actingAs($editor)->get("/admin/final-quizzes/{$lessonQuiz->id}/edit")->assertNotFound();
    }

    public function test_creating_a_final_quiz_via_standalone_resource_forces_null_lesson(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();

        Livewire::actingAs($editor)
            ->test(CreateFinalQuiz::class)
            ->fillForm(['title' => 'Evaluasi Akhir', 'course_id' => $course->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('course_quizzes', [
            'title' => 'Evaluasi Akhir',
            'course_id' => $course->id,
            'lesson_id' => null,
        ]);
    }

    public function test_editing_a_final_quiz_keeps_null_lesson_and_course(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $finalQuiz = CourseQuiz::factory()->for($course)->create(['lesson_id' => null, 'title' => 'Lama']);

        Livewire::actingAs($editor)
            ->test(EditFinalQuiz::class, ['record' => $finalQuiz->id])
            ->fillForm(['title' => 'Evaluasi Akhir Revisi'])
            ->call('save')
            ->assertHasNoFormErrors();

        $finalQuiz->refresh();
        $this->assertEquals('Evaluasi Akhir Revisi', $finalQuiz->title);
        $this->assertNull($finalQuiz->lesson_id);
        $this->assertEquals($course->id, $finalQuiz->course_id);
    }

    public function test_final_quiz_list_shows_only_course_level_quizzes(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $finalQuiz = CourseQuiz::factory()->for($course)->create(['lesson_id' => null]);
        $lessonQuiz = CourseQuiz::factory()->for(CourseLesson::factory(), 'lesson')->create();

        Livewire::actingAs($editor)
            ->test(ListFinalQuizzes::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$finalQuiz])
            ->assertCanNotSeeTableRecords([$lessonQuiz]);
    }

    public function test_final_quiz_relation_manager_shows_only_final_quizzes(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        $finalQuiz = CourseQuiz::factory()->for($course)->create(['lesson_id' => null]);
        $lessonQuiz = CourseQuiz::factory()->for($lesson, 'lesson')->create();

        Livewire::actingAs($editor)
            ->test(FinalQuizRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$finalQuiz])
            ->assertCanNotSeeTableRecords([$lessonQuiz]);
    }

    public function test_editor_can_create_a_question_on_a_final_quiz(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $finalQuiz = CourseQuiz::factory()->for(Course::factory()->create())->create(['lesson_id' => null]);

        Livewire::actingAs($editor)
            ->test(FinalQuizQuestionsRelationManager::class, ['ownerRecord' => $finalQuiz, 'pageClass' => EditFinalQuiz::class])
            ->callTableAction('create', data: ['question' => 'Syarat kursus selesai?'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('quiz_questions', [
            'course_quiz_id' => $finalQuiz->id,
            'question' => 'Syarat kursus selesai?',
        ]);
    }

    // (** executed: Task 4 evaluation (F4-2) — the resource form pinned
    // pass_threshold with default(70) but no nullable(), so the spec's
    // "nilai per-kuis menimpa bila diisi" fallback to course->pass_threshold
    // could never apply to a final quiz (CourseQuizResource allows it). **)
    public function test_final_quiz_can_use_course_default_threshold(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create(['pass_threshold' => 80]);

        Livewire::actingAs($editor)
            ->test(CreateFinalQuiz::class)
            ->fillForm(['title' => 'Evaluasi Akhir', 'course_id' => $course->id, 'pass_threshold' => null])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('course_quizzes', [
            'title' => 'Evaluasi Akhir',
            'course_id' => $course->id,
            'lesson_id' => null,
            'pass_threshold' => null,
        ]);
    }
}
