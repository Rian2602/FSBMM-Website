<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_emit_meta_title_and_description(): void
    {
        $this->seed();

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>', false)
            ->assertSee('Situs resmi Federasi Serikat Buruh Makanan dan Minuman', false);

        $this->get('/berita')->assertOk()->assertSee('<title>', false);
        $this->get('/sba')->assertOk()->assertSee('<title>', false);
    }

    public function test_sitemap_lists_main_collections(): void
    {
        $this->seed();

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('content-type') ?? '');
        $response->assertSee('/berita', false)->assertSee('/sba', false)->assertSee('/e-resource', false);
    }
}
