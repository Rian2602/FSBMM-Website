<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Due;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberDataOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_aggregate_numbers_on_admin_dashboard(): void
    {
        $org = Organization::factory()->create();
        $member = Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);
        Due::factory()->for($member)->for($org)->create(['period' => now()->format('Y-m'), 'amount' => 50000]);
        Complaint::factory()->for($org)->create(['status' => 'baru']);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('50.000', false); // formatted dues sum appears somewhere on the dashboard
    }

    public function test_admin_dashboard_response_never_contains_a_member_name_or_nik(): void
    {
        $org = Organization::factory()->create();
        $member = Member::factory()->for($org)->create(['name' => 'Nama Sangat Unik Sekali', 'nik' => '99998888']);

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertDontSee('Nama Sangat Unik Sekali');
        $response->assertDontSee('99998888');
    }
}
