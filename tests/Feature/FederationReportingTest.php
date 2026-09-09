<?php

namespace Tests\Feature;

use App\Filament\Widgets\FederationOperationsWidget;
use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Due;
use App\Models\Event;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class FederationReportingTest extends TestCase
{
    use RefreshDatabase;

    private function seedOperationalData(): void
    {
        $orgA = Organization::factory()->create(['name' => 'SPM Alpha', 'member_count' => 0]);
        $orgB = Organization::factory()->create(['name' => 'SPM Beta', 'member_count' => 0]);
        Organization::factory()->create(['name' => 'SPM Gamma', 'member_count' => 0]);

        $period = now()->format('Y-m');

        $a1 = Member::factory()->for($orgA)->create();
        $a2 = Member::factory()->for($orgA)->create();
        $b1 = Member::factory()->for($orgB)->create();

        Due::factory()->for($orgA, 'organization')->for($a1, 'member')->create(['period' => $period, 'amount' => 50000]);
        Due::factory()->for($orgA, 'organization')->for($a2, 'member')->create(['period' => $period, 'amount' => 50000]);
        Due::factory()->for($orgB, 'organization')->for($b1, 'member')->create(['period' => $period, 'amount' => 25000]);

        $eventA1 = Event::factory()->for($orgA)->create(['title' => 'Rapat Alpha']);
        $eventA2 = Event::factory()->for($orgA)->create(['title' => 'Pelatihan Alpha']);
        $eventB1 = Event::factory()->for($orgB)->create(['title' => 'Rapat Beta']);

        Attendance::factory()->for($orgA, 'organization')->for($eventA1, 'event')->for($a1, 'member')->create(['status' => 'hadir']);
        Attendance::factory()->for($orgA, 'organization')->for($eventA2, 'event')->for($a2, 'member')->create(['status' => 'hadir']);
        Attendance::factory()->for($orgA, 'organization')->for($eventA1, 'event')->for($a2, 'member')->create(['status' => 'izin']);
        Attendance::factory()->for($orgB, 'organization')->for($eventB1, 'event')->for($b1, 'member')->create(['status' => 'hadir']);

        Complaint::factory()->for($orgA, 'organization')->create(['status' => 'baru']);
        Complaint::factory()->for($orgB, 'organization')->create(['status' => 'selesai']);
    }

    public function test_metrics_aggregate_across_all_orgs(): void
    {
        $this->seedOperationalData();

        auth()->login(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $metrics = (new FederationOperationsWidget)->getMetrics();

        $this->assertSame(3, $metrics['organizations']);
        $this->assertSame(3, $metrics['active_members']);
        $this->assertSame(125000.0, $metrics['current_dues']);
        $this->assertSame(3, $metrics['events']);
        $this->assertSame(3, $metrics['attendances_hadir']);
        $this->assertSame(1, $metrics['open_complaints']);
        $this->assertSame(0, $metrics['active_cards']);
        $this->assertSame(0, $metrics['revoked_cards']);
    }

    public function test_widget_is_super_admin_only_and_server_rendered(): void
    {
        $this->assertFalse(FederationOperationsWidget::canView());

        auth()->login(User::factory()->create(['role' => User::ROLE_EDITOR]));
        $this->assertFalse(FederationOperationsWidget::canView());

        auth()->login(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
        $this->assertTrue(FederationOperationsWidget::canView());

        $isLazy = (new ReflectionClass(FederationOperationsWidget::class))->getStaticPropertyValue('isLazy');
        $this->assertFalse($isLazy);
    }

    public function test_per_sba_breakdown_is_aggregate_only(): void
    {
        $this->seedOperationalData();

        auth()->login(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));

        $breakdown = (new FederationOperationsWidget)->getPerSbaBreakdown();

        $this->assertCount(3, $breakdown);
        $this->assertSame('SPM Alpha', $breakdown[0]['name']);
        $this->assertSame(2, $breakdown[0]['active_members']);
        $this->assertSame(100000.0, $breakdown[0]['current_dues']);
        $this->assertSame(2, $breakdown[0]['events']);
        $this->assertSame(1, $breakdown[0]['open_complaints']);
        $this->assertSame(0, $breakdown[0]['active_cards']);

        $this->assertSame('SPM Beta', $breakdown[1]['name']);
        $this->assertSame(1, $breakdown[1]['active_members']);
        $this->assertSame(25000.0, $breakdown[1]['current_dues']);
        $this->assertSame(1, $breakdown[1]['events']);
        $this->assertSame(0, $breakdown[1]['open_complaints']);

        $this->assertSame('SPM Gamma', $breakdown[2]['name']);
        $this->assertSame([0, 0.0, 0, 0, 0], [
            $breakdown[2]['active_members'],
            $breakdown[2]['current_dues'],
            $breakdown[2]['events'],
            $breakdown[2]['open_complaints'],
            $breakdown[2]['active_cards'],
        ]);
    }

    public function test_super_admin_dashboard_shows_operations_widget(): void
    {
        $this->seedOperationalData();

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Ringkasan Operasional Federasi')
            ->assertSee('125.000', false)
            ->assertSee('100.000', false);
    }

    public function test_editor_dashboard_hides_operations_widget(): void
    {
        $this->seedOperationalData();

        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($editor)->get('/admin')
            ->assertOk()
            ->assertDontSee('Ringkasan Operasional Federasi');
    }

    public function test_anonymous_cannot_access_admin_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_dashboard_never_leaks_member_pii(): void
    {
        $org = Organization::factory()->create(['name' => 'SPM PII', 'member_count' => 0]);

        Member::factory()->for($org)->create([
            'name' => 'Nama Sangat Rahasia',
            'nik' => '9876543210',
            'address' => 'Jl. Rahasia No. 13',
            'birthdate' => '1990-01-01',
            'basic_salary' => 12345678,
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertDontSee('Nama Sangat Rahasia')
            ->assertDontSee('9876543210')
            ->assertDontSee('Jl. Rahasia No. 13')
            ->assertDontSee('1990-01-01')
            ->assertDontSee('12345678');
    }

    public function test_admin_dashboard_never_leaks_complaint_content(): void
    {
        $org = Organization::factory()->create(['name' => 'SPM PII', 'member_count' => 0]);

        $member = Member::factory()->for($org)->create();

        Complaint::factory()->for($org, 'organization')->for($member, 'member')->create([
            'reporter_name' => 'Pelapor Rahasia ZZ-7781',
            'title' => 'Keluhan Rahasia ZZ-7781',
            'description' => 'Isi keluhan super rahasia yang memuat rincian pribadi ZZ-7781-24680.',
            'status' => 'baru',
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertDontSee('ZZ-7781')
            ->assertDontSee('Pelapor Rahasia ZZ-7781')
            ->assertSee('Ringkasan Operasional Federasi');
    }
}
