<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Organization;
use App\Models\Page;
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

    public function test_sitemap_lists_published_dynamic_content_with_lastmod(): void
    {
        $page = Page::factory()->create(['slug' => 'sejarah', 'is_published' => true]);
        $article = Article::factory()->create(['published_at' => now()]);
        $org = Organization::factory()->create(['is_published' => true]);
        Article::factory()->create(['published_at' => null]);
        Organization::factory()->create(['is_published' => false]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk()
            ->assertSee('/sejarah', false)
            ->assertSee('/berita/'.$article->slug, false)
            ->assertSee('/sba/'.$org->slug, false);
    }

    public function test_robots_txt_declares_absolute_sitemap_url(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $this->assertStringContainsString('text/plain', $response->headers->get('content-type') ?? '');
        $response->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }
}
