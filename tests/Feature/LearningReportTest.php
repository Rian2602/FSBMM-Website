<?php

namespace Tests\Feature;

use App\Filament\Widgets\LearningReportWidget;
use App\Models\Course;
use App\Models\User;
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
}
