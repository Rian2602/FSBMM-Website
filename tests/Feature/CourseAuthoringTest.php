<?php

namespace Tests\Feature;

use App\Filament\Resources\CourseLessonResource\Pages\CreateCourseLesson;
use App\Models\Course;
use App\Models\Organization;
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
}
