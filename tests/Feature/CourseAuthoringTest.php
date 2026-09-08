<?php

namespace Tests\Feature;

use App\Filament\Resources\CourseLessonResource\Pages\CreateCourseLesson;
use App\Filament\Resources\CourseLessonResource\Pages\EditCourseLesson;
use App\Filament\Resources\CourseLessonResource\RelationManagers\QuizzesRelationManager;
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
}
