<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_directory_lists_only_published_organizations(): void
    {
        Organization::factory()->create(['name' => 'SPM Kecap Bango', 'is_published' => true]);
        Organization::factory()->create(['name' => 'SPM Minuman Segar', 'is_published' => false]);

        $this->get('/sba')
            ->assertOk()
            ->assertSee('SPM Kecap Bango')
            ->assertDontSee('SPM Minuman Segar');
    }

    public function test_public_profile_page_renders_a_published_sba(): void
    {
        $org = Organization::factory()->create(['is_published' => true]);

        $this->get('/sba/'.$org->slug)->assertOk()->assertSee($org->name);
    }

    public function test_unpublished_sba_profile_returns_404(): void
    {
        $org = Organization::factory()->create(['is_published' => false]);

        $this->get('/sba/'.$org->slug)->assertNotFound();
    }
}
