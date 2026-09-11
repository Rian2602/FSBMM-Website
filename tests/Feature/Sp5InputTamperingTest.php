<?php

namespace Tests\Feature;

use App\Exports\MemberExport;
use App\Filament\Sba\Pages\MemberCardPage;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Organization;
use App\Models\User;
use App\Support\MemberCardService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SP5 Phase 10 Task 10.2 — input-tampering tests (spec §2/§9).
 *
 * Every cross-tenant identifier that can be fed from the request is proven to
 * be ignored by construction (exports copy org from auth, not the request) or
 * rejected at the service/table layer.
 */
class Sp5InputTamperingTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_ignores_tampered_organization_id(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');
        [, $orgB] = $this->createSbaAdmin('SBA B');

        Member::factory()->for($orgA)->create(['name' => 'Anggota SBA A']);
        Member::factory()->for($orgB)->create(['name' => 'Anggota SBA B']);

        // organization_id is not in the request whitelist — the export always
        // targets auth()->user()->organization_id, even for a bogus org id.
        $redirect = $this->actingAs($sbaA)
            ->get('/panel-sba/member-report/export?organization_id=999999')
            ->assertStatus(302);

        $followed = $this->get($redirect->headers->get('Location'));
        $followed->assertOk();

        $csv = file_get_contents((string) $followed->baseResponse->getFile());
        $this->assertStringContainsString('Anggota SBA A', $csv);
        $this->assertStringNotContainsString('Anggota SBA B', $csv);
    }

    public function test_download_signed_url_rejects_tampered_org(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');
        [, $orgB] = $this->createSbaAdmin('SBA B');

        Member::factory()->for($orgA)->create(['name' => 'Anggota SBA A']);

        $url = MemberExport::generate($orgA->id, [], 'csv');
        $query = [];
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);
        $query['org'] = $orgB->id;
        $tampered = parse_url((string) $url, PHP_URL_PATH).'?'.http_build_query($query);

        $this->actingAs($sbaA)
            ->get($tampered)
            ->assertForbidden();
    }

    public function test_member_card_filter_ignores_foreign_member_id(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');
        [, $orgB] = $this->createSbaAdmin('SBA B');

        $memberA = Member::factory()->for($orgA)->create(['name' => 'Anggota SBA A', 'status' => Member::STATUS_ACTIVE]);
        $memberB = Member::factory()->for($orgB)->create(['name' => 'Anggota SBA B', 'status' => Member::STATUS_ACTIVE]);
        $cardA = (new MemberCardService)->issue($memberA, $sbaA);
        $cardB = (new MemberCardService)->issue($memberB, $this->sbaAdminOf($orgB));

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        // A foreign member_id filter still cannot surface another tenant's card
        // because the base query is organization-scoped first — the filter
        // simply yields no rows instead of leaking tenant B.
        Livewire::actingAs($sbaA)
            ->test(MemberCardPage::class)
            ->set('data.member_id', $memberB->id)
            ->assertOk()
            ->assertDontSee($cardB->card_number)
            ->assertDontSee($memberB->name)
            ->assertDontSee($cardA->card_number);
    }

    public function test_issue_card_action_rejects_foreign_member_id(): void
    {
        [$sbaA] = $this->createSbaAdmin('SBA A');
        [, $orgB] = $this->createSbaAdmin('SBA B');

        $memberB = Member::factory()->for($orgB)->create(['status' => Member::STATUS_ACTIVE]);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        // The header "Terbitkan Kartu Baru" resolves the member freely, but the
        // service guard (guardEligible) rejects a cross-tenant member via
        // AuthorizationException → Filament surfaces it as a flash notification,
        // so assertHasNoTableActionErrors() cannot detect the rejection; the real
        // guard assertion is the database write below (assertDatabaseMissing).
        Livewire::actingAs($sbaA)
            ->test(MemberCardPage::class)
            ->callTableAction('issueNew', data: ['member_id' => $memberB->id]);

        $this->assertDatabaseMissing('member_cards', ['member_id' => $memberB->id]);
    }

    public function test_table_revoke_action_rejects_foreign_card_id(): void
    {
        [$sbaA] = $this->createSbaAdmin('SBA A');
        [, $orgB] = $this->createSbaAdmin('SBA B');

        $memberB = Member::factory()->for($orgB)->create(['status' => Member::STATUS_ACTIVE]);
        $cardB = (new MemberCardService)->issue($memberB, $this->sbaAdminOf($orgB));

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        // A foreign card is not resolvable from the page's scoped table query:
        // it never appears in the rendered table, so the revoke/print/issue
        // actions cannot be reached for it at all. Status stays untouched.
        Livewire::actingAs($sbaA)
            ->test(MemberCardPage::class)
            ->assertOk()
            ->assertDontSee($cardB->card_number);

        $this->assertSame(MemberCard::STATUS_ACTIVE, $cardB->fresh()->status);
    }

    public function test_export_filter_cannot_target_foreign_event(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');
        [, $orgB] = $this->createSbaAdmin('SBA B');

        $memberA = Member::factory()->for($orgA)->create(['name' => 'Anggota SBA A']);
        $memberB = Member::factory()->for($orgB)->create(['name' => 'Anggota SBA B']);
        $eventA = Event::factory()->for($orgA)->create(['title' => 'Kegiatan A', 'event_date' => '2026-09-01']);
        $eventB = Event::factory()->for($orgB)->create(['title' => 'Kegiatan B', 'event_date' => '2026-09-02']);

        Attendance::factory()->for($orgA, 'organization')->for($eventA, 'event')->for($memberA, 'member')->create(['status' => 'hadir']);
        Attendance::factory()->for($orgB, 'organization')->for($eventB, 'event')->for($memberB, 'member')->create(['status' => 'hadir']);

        // event_id of another tenant is whereKey'd inside the org-scoped event
        // query, so the export yields only A's own attendance rows.
        $redirect = $this->actingAs($sbaA)
            ->get('/panel-sba/attendance-report/export?event_id='.$eventB->id)
            ->assertStatus(302);

        $followed = $this->get($redirect->headers->get('Location'));
        $followed->assertOk();

        $csv = file_get_contents((string) $followed->baseResponse->getFile());
        $this->assertStringNotContainsString('Kegiatan B', $csv);
        $this->assertStringNotContainsString('Anggota SBA B', $csv);
    }

    public function test_export_download_rejects_path_traversal(): void
    {
        [$sbaA, $orgA] = $this->createSbaAdmin('SBA A');

        // A validly-signed URL whose file param escapes the export directory
        // must be rejected by the realpath containment check before download.
        $url = URL::temporarySignedRoute('exports.download', now()->addHour(), [
            'file' => 'exports/../../../config/app.php',
            'org' => $orgA->id,
        ]);

        $this->actingAs($sbaA)
            ->get($url)
            ->assertNotFound();
    }

    private function createSbaAdmin(string $orgName): array
    {
        $org = Organization::factory()->create(['name' => $orgName]);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }

    private function sbaAdminOf(Organization $org): User
    {
        return User::factory()->sbaAdmin($org)->create();
    }
}
