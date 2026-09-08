<?php

namespace Tests\Feature;

use App\Filament\Widgets\LearningReportWidget;
use App\Models\Course;
use App\Models\CourseAttempt;
use App\Models\CourseLesson;
use App\Models\CourseQuiz;
use App\Models\Organization;
use App\Models\User;
use App\Support\LearningProgress;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LearningReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_widget_is_super_admin_only(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($editor);
        $this->assertFalse(LearningReportWidget::canView());

        $this->actingAs($super);
        $this->assertTrue(LearningReportWidget::canView());
    }

    public function test_report_lists_course_and_user_statuses(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $course = Course::factory()->create(['title' => 'Kursus Laporan']);
        $userA = User::factory()->create(['name' => 'Peserta A', 'role' => User::ROLE_EDITOR]);
        $userB = User::factory()->create(['name' => 'Peserta B', 'role' => User::ROLE_EDITOR]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($super)
            ->test(LearningReportWidget::class)
            ->assertOk()
            ->assertSee('Kursus Laporan')
            ->assertSee('Peserta A')
            ->assertSee('Peserta B');
    }

    public function test_report_renders_on_admin_dashboard_for_super_admin_only(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        Course::factory()->create(['title' => 'Kursus Laporan']);

        $this->actingAs($super)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Laporan Pembelajaran');

        $this->actingAs($editor)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Laporan Pembelajaran');
    }

    public function test_report_never_renders_on_sba_panel(): void
    {
        $org = Organization::factory()->create();
        $sba = User::factory()->sbaAdmin($org)->create();

        $this->actingAs($sba)
            ->get('/panel-sba')
            ->assertOk()
            ->assertDontSee('Laporan Pembelajaran');
    }

    public function test_report_shows_completer_count(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $learner = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $other = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create(['title' => 'Kursus Status']);
        $lesson = CourseLesson::factory()->for($course)->create();
        $final = CourseQuiz::factory()->for($course)->create(['lesson_id' => null]);

        app(LearningProgress::class)->setLessonCompleted($learner, $course, $lesson);
        CourseAttempt::create([
            'course_quiz_id' => $final->id,
            'user_id' => $learner->id,
            'score' => 100,
            'passed' => true,
            'attempt_date' => now(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($super)
            ->test(LearningReportWidget::class)
            ->assertSee('Kursus Status')
            ->assertSee('1/3 peserta selesai');
    }

    public function test_report_marks_in_progress_and_not_started_users(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $started = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $cold = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($course)->create();
        app(LearningProgress::class)->setLessonCompleted($started, $course, $lesson);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($super)
            ->test(LearningReportWidget::class)
            ->assertSee('Sedang')
            ->assertSee('Belum');
    }

    public function test_report_empty_state_when_no_published_courses(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($super)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Laporan Pembelajaran')
            ->assertSee('Belum ada kursus terbit');
    }
}
