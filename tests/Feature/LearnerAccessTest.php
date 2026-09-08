<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\CourseDetailPage;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
            ->get('/panel-sba/courses/kursus-sba/lessons/'.$lesson->id)
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
}
