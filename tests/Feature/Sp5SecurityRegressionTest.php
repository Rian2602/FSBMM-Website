<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Due;
use App\Models\Event;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Support\MemberCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SP5 Phase 10 Task 10.1 — the 12-row security regression matrix (spec §2/§9).
 *
 * Rows 2/4/6/8/9/10/12 were already proven in Sp5SecurityTest /
 * CardVerificationTest; this file re-proves every row against the real
 * artefacts so the matrix stands on its own as the final SP5 security gate.
 */
class Sp5SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_row1_sba_a_allows_own_member(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');
        $memberA = Member::factory()->for($orgA)->create(['name' => 'Andi Wijaya']);

        $this->actingAs($sbaA)
            ->get('/panel-sba/members')
            ->assertOk()
            ->assertSee($memberA->name);
    }

    public function test_row2_sba_a_cannot_access_foreign_member(): void
    {
        [$sbaA] = $this->createSbaAdmin('SBA A');
        [$sbaB, $orgB] = $this->createSbaAdmin('SBA B');
        $memberB = Member::factory()->for($orgB)->create(['name' => 'Member B']);

        $this->actingAs($sbaA)
            ->get('/panel-sba/members/' . $memberB->id . '/edit')
            ->assertNotFound();
    }

    public function test_row3_sba_a_allows_own_card(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');
        $memberA = Member::factory()->for($orgA)->create(['status' => Member::STATUS_ACTIVE]);
        $cardA = (new MemberCardService)->issue($memberA, $sbaA);

        $this->actingAs($sbaA)
            ->get(route('filament.sba.card.print', ['record' => $cardA->id]))
            ->assertOk()
            ->assertSee($memberA->name);
    }

    public function test_row4_sba_a_cannot_print_foreign_card(): void
    {
        [$sbaA] = $this->createSbaAdmin('SBA A');
        [$sbaB, $orgB] = $this->createSbaAdmin('SBA B');
        $memberB = Member::factory()->for($orgB)->create(['status' => Member::STATUS_ACTIVE]);
        $cardB = (new MemberCardService)->issue($memberB, $sbaB);

        $this->actingAs($sbaA)
            ->get(route('filament.sba.card.print', ['record' => $cardB->id]))
            ->assertNotFound();
    }

    public function test_row5_sba_a_allows_own_export(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');
        Member::factory()->for($orgA)->create(['name' => 'Member SBA A']);

        $redirect = $this->actingAs($sbaA)
            ->get('/panel-sba/member-report/export?organization_id=' . $orgA->id)
            ->assertStatus(302);

        $followed = $this->get($redirect->headers->get('Location'));
        $followed->assertOk();

        $csv = file_get_contents((string) $followed->baseResponse->getFile());
        $this->assertStringContainsString('Member SBA A', $csv);
    }

    public function test_row6_sba_a_cannot_export_foreign_data(): void
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
            'dues-report' => ['expected' => '100000', 'forbidden' => '999000'],
            'attendance-report' => ['expected' => 'Andi Wijaya', 'forbidden' => 'Budi Santoso'],
            'complaint-report' => ['expected' => 'Keluhan A', 'forbidden' => 'Keluhan B'],
        ] as $endpoint => $pairs) {
            $redirect = $this->actingAs($sbaA)
                ->get('/panel-sba/' . $endpoint . '/export?organization_id=' . $orgB->id)
                ->assertStatus(302);
            $downloaded = $this->get($redirect->headers->get('Location'));
            $downloaded->assertOk();

            $content = file_get_contents((string) $downloaded->baseResponse->getFile());
            $this->assertStringContainsString($pairs['expected'], $content);
            $this->assertStringNotContainsString($pairs['forbidden'], $content);
        }
    }

    public function test_row7_anonymous_cannot_access_member(): void
    {
        $this->get('/panel-sba/members')->assertRedirect('/panel-sba/login');
    }

    public function test_row8_anonymous_cannot_export(): void
    {
        $this->get('/panel-sba/member-report/export')->assertRedirect('/panel-sba/login');
    }

    public function test_row9_anonymous_can_verify_card(): void
    {
        [$user, $org] = $this->createSbaAdmin('SBA A');
        $member = Member::factory()->for($org)->create(['status' => Member::STATUS_ACTIVE]);
        $card = (new MemberCardService)->issue($member, $user);

        $this->get('/verifikasi/kartu/' . $card->verification_token)
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee($card->card_number);
    }

    public function test_row10_editor_cannot_access_member_pii(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($editor)
            ->get('/admin/members')
            ->assertNotFound();
    }

    public function test_row11_editor_cannot_see_federation_aggregate(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($editor)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Ringkasan Operasional Federasi');
    }

    public function test_row12_sba_cannot_access_federation_report(): void
    {
        [$sba] = $this->createSbaAdmin('SBA A');

        $this->actingAs($sba)
            ->get('/admin')
            ->assertForbidden();
    }

    private function createSbaAdmin(string $orgName): array
    {
        $org = Organization::factory()->create(['name' => $orgName]);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }
}
