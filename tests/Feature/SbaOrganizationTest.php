<?php

namespace Tests\Feature;

use App\Filament\Sba\Resources\OrganizationResource;
use App\Filament\Sba\Resources\OrganizationResource\Pages\EditOrganization;
use App\Filament\Sba\Widgets\OrganizationSummaryWidget;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SbaOrganizationTest extends TestCase
{
    use RefreshDatabase;

    private function sbaUser(): array
    {
        $org = Organization::factory()->create(['name' => 'SPM Milik Saya']);
        $user = User::factory()->sbaAdmin($org)->create();

        return [$user, $org];
    }

    public function test_sba_directory_still_lists_all_published_organizations(): void
    {
        Organization::factory()->create(['name' => 'SPM A', 'is_published' => true]);
        Organization::factory()->create(['name' => 'SPM B', 'is_published' => true]);
        Organization::factory()->create(['name' => 'SPM Rahasia', 'is_published' => false]);

        $this->get('/sba')
            ->assertOk()
            ->assertSee('SPM A')
            ->assertSee('SPM B')
            ->assertDontSee('SPM Rahasia');
    }

    public function test_sba_list_shows_only_own_organization(): void
    {
        [$user, $org] = $this->sbaUser();
        Organization::factory()->create(['name' => 'SPM Organisasi Lain']);

        $this->actingAs($user)->get('/panel-sba/organizations')
            ->assertOk()
            ->assertSee($org->name);
    }

    public function test_editing_another_organizations_url_returns_404(): void
    {
        [$user] = $this->sbaUser();
        $other = Organization::factory()->create(['name' => 'SPM Milik Orang Lain']);

        $this->actingAs($user)->get('/panel-sba/organizations/'.$other->slug.'/edit')
            ->assertNotFound();
    }

    public function test_sba_cannot_create_or_delete_organizations(): void
    {
        [$user] = $this->sbaUser();

        $this->assertFalse(OrganizationResource::canCreate());
        $this->actingAs($user)->get('/panel-sba/organizations/create')->assertNotFound();
    }

    public function test_sba_edits_own_organization_profile_fields_only(): void
    {
        [$user, $org] = $this->sbaUser();
        $originalSlug = $org->slug;
        $originalPublished = $org->is_published;
        $originalMemberCount = $org->member_count;

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(EditOrganization::class, ['record' => $org->slug])
            ->fillForm([
                'description' => '<p>Deskripsi baru dari pengurus SBA.</p>',
                'website' => 'https://contoh-sba.example',
                'location' => 'Karawang, Jawa Barat',
                // Federation-controlled fields are NOT in the form — attempt to smuggle them:
                'slug' => 'slug-bajakan',
                'is_published' => true,
                'member_count' => 999999,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $org->refresh();

        $this->assertSame('<p>Deskripsi baru dari pengurus SBA.</p>', $org->description);
        $this->assertSame('https://contoh-sba.example', $org->website);
        $this->assertSame('Karawang, Jawa Barat', $org->location);
        // Fields absent from the SBA form cannot be changed through the panel:
        $this->assertSame($originalSlug, $org->slug);
        $this->assertSame($originalPublished, $org->is_published);
        $this->assertSame($originalMemberCount, $org->member_count);
    }

    public function test_own_profile_edit_is_reflected_on_the_public_page(): void
    {
        [$user, $org] = $this->sbaUser();
        $org->update(['is_published' => true, 'description' => 'Profil lama']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(EditOrganization::class, ['record' => $org->slug])
            ->fillForm(['description' => '<p>Profil baru yang diubah pengurus.</p>'])
            ->call('save');

        $this->get('/sba/'.$org->slug)->assertOk()->assertSee('Profil baru yang diubah pengurus');
    }

    public function test_dashboard_summary_widget_shows_own_organization_only(): void
    {
        [$user, $org] = $this->sbaUser();
        Organization::factory()->create(['name' => 'SPM Lain Yang Tidak Terlihat']);

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(OrganizationSummaryWidget::class)
            ->assertOk()
            ->assertSee($org->name)
            ->assertDontSee('SPM Lain Yang Tidak Terlihat');
    }

    public function test_sba_cannot_store_scripts_or_event_handlers_in_description(): void
    {
        [$user, $org] = $this->sbaUser();

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(EditOrganization::class, ['record' => $org->slug])
            ->fillForm([
                'description' => '<p>OK</p><script>alert(1)</script><img src=x onerror=alert(2)>',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $org->refresh();

        $this->assertStringNotContainsString('<script', $org->description);
        $this->assertStringNotContainsString('onerror', $org->description);
        $this->assertStringContainsString('<p>OK</p>', $org->description);
    }

    public function test_sanitization_preserves_safe_rich_text_markup_in_description(): void
    {
        [$user, $org] = $this->sbaUser();
        $payload = '<p><strong>Bold</strong></p><p><a href="https://contoh.example">link</a></p><ul><li>a</li></ul>';

        Filament::setCurrentPanel(Filament::getPanel('sba'));

        Livewire::actingAs($user)
            ->test(EditOrganization::class, ['record' => $org->slug])
            ->fillForm(['description' => $payload])
            ->call('save')
            ->assertHasNoFormErrors();

        $org->refresh();

        $this->assertStringContainsString('<strong>Bold</strong>', $org->description);
        $this->assertStringContainsString('<a href="https://contoh.example"', $org->description);
        $this->assertStringContainsString('<ul>', $org->description);
        $this->assertStringContainsString('<li>a</li>', $org->description);
    }
}
