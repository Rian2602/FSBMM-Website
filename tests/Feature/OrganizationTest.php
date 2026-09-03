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

    public function test_unsafe_website_value_renders_inert_not_as_link(): void
    {
        $org = Organization::factory()->create([
            'is_published' => true,
            'website' => 'javascript:alert(1)',
        ]);

        $response = $this->get('/sba/'.$org->slug);

        $response->assertOk();
        $response->assertDontSee('href="javascript:', false);
        $response->assertSee('javascript:alert(1)', false); // shown as inert text
    }

    public function test_https_website_renders_as_link(): void
    {
        $org = Organization::factory()->create([
            'is_published' => true,
            'website' => 'https://example.org/sba',
        ]);

        $this->get('/sba/'.$org->slug)
            ->assertOk()
            ->assertSee('href="https://example.org/sba"', false);
    }

    public function test_organization_without_company_lists_without_empty_row(): void
    {
        $org = Organization::factory()->create([
            'is_published' => true,
            'company' => null,
        ]);

        $this->get('/sba')
            ->assertOk()
            ->assertSee($org->name)
            ->assertDontSee('font-semibold text-stone-700"></p>', false);
    }

    public function test_cleared_founded_year_stores_null_not_zero(): void
    {
        $org = Organization::factory()->create(['founded_year' => '']);

        $this->assertNull($org->fresh()->founded_year);
    }

    public function test_valid_founded_year_is_preserved(): void
    {
        $org = Organization::factory()->create(['founded_year' => '2015']);

        $this->assertSame(2015, $org->fresh()->founded_year);
    }
}
