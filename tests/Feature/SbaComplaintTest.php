<?php

namespace Tests\Feature;

use App\Filament\Sba\Resources\ComplaintResource\Pages\CreateComplaint;
use App\Filament\Sba\Resources\ComplaintResource\Pages\EditComplaint;
use App\Models\Complaint;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaComplaintTest extends TestCase
{
    use RefreshDatabase;

    private function sbaUser(): array
    {
        $org = Organization::factory()->create(['name' => 'SPM Milik Saya (ComplaintTest)']);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }

    public function test_anonymous_is_redirected_to_sba_login(): void
    {
        $this->get('/panel-sba/complaints')->assertRedirect('/panel-sba/login');
    }

    public function test_super_admin_and_editor_cannot_access_sba_complaints(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAs($super)->get('/panel-sba/complaints')->assertForbidden();
        $this->actingAs($editor)->get('/panel-sba/complaints')->assertForbidden();
    }

    public function test_sba_admin_can_list_their_own_complaints_only(): void
    {
        [$user, $org] = $this->sbaUser();
        Complaint::factory()->for($org)->create(['title' => 'Pengaduan Saya']);
        $other = Organization::factory()->create(['name' => 'SPM Lain']);
        Complaint::factory()->for($other)->create(['title' => 'Pengaduan Orang Lain']);

        $this->actingAs($user)
            ->get('/panel-sba/complaints')
            ->assertOk()
            ->assertSee('Pengaduan Saya')
            ->assertDontSee('Pengaduan Orang Lain');
    }

    public function test_editing_another_organizations_complaint_returns_404(): void
    {
        [$user] = $this->sbaUser();
        $other = Organization::factory()->create();
        $complaint = Complaint::factory()->for($other)->create();

        $this->actingAs($user)
            ->get('/panel-sba/complaints/'.$complaint->id.'/edit')
            ->assertNotFound();
    }

    public function test_creating_a_complaint_auto_links_org_and_sets_defaults(): void
    {
        [$user, $org] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateComplaint::class)
            ->fillForm([
                'reporter_name' => 'Budi Santoso',
                'title' => 'Fasilitas kantor rusak',
                'description' => 'AC ruang rapat tidak berfungsi sejak Senin.',
                'status' => 'baru',
                'submitted_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('complaints', [
            'organization_id' => $org->id,
            'reporter_name' => 'Budi Santoso',
            'status' => 'baru',
        ]);
    }

    public function test_required_fields_are_validated(): void
    {
        [$user] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateComplaint::class)
            ->fillForm([
                'reporter_name' => '',
                'title' => '',
                'description' => '',
                'submitted_at' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['reporter_name', 'title', 'description', 'submitted_at']);
    }

    public function test_member_id_accepts_null(): void
    {
        [$user, $org] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateComplaint::class)
            ->fillForm([
                'reporter_name' => 'Warga Umum',
                'title' => 'Pengaduan Tanpa Anggota',
                'description' => 'Tanpa data anggota.',
                'member_id' => null,
                'submitted_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('complaints', [
            'organization_id' => $org->id,
            'member_id' => null,
            'reporter_name' => 'Warga Umum',
        ]);
    }

    public function test_changing_status_to_diproses_sets_handled_by(): void
    {
        [$user, $org] = $this->sbaUser();
        $complaint = Complaint::factory()->for($org)->create(['status' => 'baru']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(EditComplaint::class, ['record' => $complaint->id])
            ->fillForm(['status' => 'diproses'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'diproses',
            'handled_by' => $user->id,
        ]);
    }

    public function test_changing_status_to_selesai_sets_handled_by_and_resolved_at(): void
    {
        [$user, $org] = $this->sbaUser();
        $complaint = Complaint::factory()->for($org)->create(['status' => 'baru']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(EditComplaint::class, ['record' => $complaint->id])
            ->fillForm(['status' => 'selesai'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'selesai',
            'handled_by' => $user->id,
        ]);

        $complaint->refresh();
        $this->assertNotNull($complaint->resolved_at);
    }

    public function test_cross_tenant_member_id_is_rejected(): void
    {
        [$user, $org] = $this->sbaUser();
        $other = Organization::factory()->create();
        $foreignMember = Member::factory()->for($other)->create();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(CreateComplaint::class)
            ->fillForm([
                'reporter_name' => 'Pelapor Uji',
                'member_id' => $foreignMember->id,
                'title' => 'Pengaduan Silang',
                'description' => 'Uji cross-tenant.',
                'submitted_at' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasFormErrors(['member_id']);
    }
}
