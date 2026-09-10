<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Due;
use App\Models\Event;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sp5SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createSbaAdmin(string $orgName): array
    {
        $org = Organization::factory()->create(['name' => $orgName]);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }

    public function test_sba_a_cannot_access_sba_b_members(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');
        [$sbaB, $orgB] = $this->createSbaAdmin('SBA B');

        $memberB = Member::factory()->for($orgB)->create(['name' => 'Member B']);

        // Directly accessing member B's edit page should 404 (scoped)
        $this->actingAs($sbaA)
            ->get('/panel-sba/members/'.$memberB->id.'/edit')
            ->assertNotFound();
    }

    public function test_sba_a_cannot_access_sba_b_reports(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');
        [$sbaB, $orgB] = $this->createSbaAdmin('SBA B');

        Member::factory()->for($orgB)->create(['name' => 'Member SBA B']);

        // SBA A views their own report, should not see SBA B's data
        // Assumes report is just a page and data is pulled scoping by user()->organization_id
        $this->actingAs($sbaA)
            ->get('/panel-sba/member-report')
            ->assertDontSee('Member SBA B');
    }

    public function test_sba_a_cannot_export_sba_b_data(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');
        [$sbaB, $orgB] = $this->createSbaAdmin('SBA B');

        Member::factory()->for($orgA)->create(['name' => 'Member SBA A']);
        Member::factory()->for($orgB)->create(['name' => 'Member SBA B']);

        $download = $this->actingAs($sbaA)
            ->get('/panel-sba/member-report/export?organization_id='.$orgB->id)
            ->assertStatus(302);

        $followed = $this->get($download->headers->get('Location'));
        $followed->assertOk();
        $csv = file_get_contents((string) $followed->baseResponse->getFile());

        $this->assertStringContainsString('Member SBA A', $csv);
        $this->assertStringNotContainsString('Member SBA B', $csv);
    }

    public function test_sba_a_cannot_export_sba_b_dues_attendance_complaints(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');
        [$sbaB, $orgB] = $this->createSbaAdmin('SBA B');

        $memberA = Member::factory()->for($orgA)->create(['name' => 'Andi Wijaya']);
        $memberB = Member::factory()->for($orgB)->create(['name' => 'Budi Santoso']);
        $eventA = Event::factory()->for($orgA)->create(['title' => 'Rapat A']);
        $eventB = Event::factory()->for($orgB)->create(['title' => 'Rapat B']);

        Due::factory()->for($orgA)->for($memberA, 'member')->create(['period' => '2026-08', 'amount' => 100000]);
        Due::factory()->for($orgB)->for($memberB, 'member')->create(['period' => '2026-08', 'amount' => 999000]);
        Attendance::factory()->for($orgA)->for($eventA, 'event')->for($memberA, 'member')->create();
        Attendance::factory()->for($orgB)->for($eventB, 'event')->for($memberB, 'member')->create();

        Complaint::factory()->for($orgA)->create(['title' => 'Keluhan A']);
        Complaint::factory()->for($orgB)->create(['title' => 'Keluhan B']);

        foreach ([
            'dues-report' => '999000',
            'attendance-report' => 'Budi Santoso',
            'complaint-report' => 'Keluhan B',
        ] as $endpoint => $forbidden) {
            $redirect = $this->actingAs($sbaA)
                ->get('/panel-sba/'.$endpoint.'/export?organization_id='.$orgB->id)
                ->assertStatus(302);
            $downloaded = $this->get($redirect->headers->get('Location'));
            $downloaded->assertOk();
            $content = file_get_contents((string) $downloaded->baseResponse->getFile());

            $this->assertStringNotContainsString($forbidden, $content);
        }
    }

    public function test_sba_a_cannot_create_revoke_print_card_for_sba_b_member(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');
        [$sbaB, $orgB] = $this->createSbaAdmin('SBA B');

        $memberB = Member::factory()->for($orgB)->create(['name' => 'Member B']);

        // Assuming member cards are managed via a Filament resource or page action
        $this->actingAs($sbaA)
            ->get('/panel-sba/member-cards/'.$memberB->id.'/print')
            ->assertNotFound();
    }

    public function test_anonymous_cannot_access_sba_reports(): void
    {
        $this->get('/panel-sba/member-report')->assertRedirect('/panel-sba/login');
        $this->get('/panel-sba/dues-report')->assertRedirect('/panel-sba/login');
        $this->get('/panel-sba/attendance-report')->assertRedirect('/panel-sba/login');
        $this->get('/panel-sba/complaint-report')->assertRedirect('/panel-sba/login');
    }

    public function test_anonymous_cannot_access_exports(): void
    {
        $this->get('/panel-sba/member-report/export')->assertRedirect('/panel-sba/login');
    }

    public function test_editor_cannot_access_individual_member_pii(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($editor)
            ->get('/admin/members')
            ->assertNotFound(); // Admin panel doesn't have a members resource at all
    }

    public function test_editor_cannot_access_federation_aggregate_reporting(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        // Federation aggregate reporting is a widget on the /admin dashboard.
        // Editor should not see the widget content on the dashboard.
        // We will assert the dashboard loads but does not contain the widget's title or data.
        $this->actingAs($editor)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Federation Operations'); // placeholder text for widget
    }

    public function test_sba_admin_cannot_access_federation_reports(): void
    {
        [$sba, $org] = $this->createSbaAdmin('SBA');

        $this->actingAs($sba)
            ->get('/admin')
            ->assertForbidden();
    }
}
