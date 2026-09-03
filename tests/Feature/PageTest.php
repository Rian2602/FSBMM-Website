<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_seeded_hero_and_stats_blocks(): void
    {
        $this->seed();

        $this->get('/')
            ->assertOk()
            ->assertSee('Federasi Serikat Buruh Makanan dan Minuman')
            ->assertSee('SBA Terdaftar');
    }

    public function test_published_page_renders_each_block_type_without_error(): void
    {
        $page = Page::factory()->create(['slug' => 'uji', 'is_published' => true]);
        $page->blocks()->createMany([
            ['type' => 'hero', 'payload' => ['title' => 'Judul Hero', 'subtitle' => 'Sub'], 'sort_order' => 1],
            ['type' => 'rich_text', 'payload' => ['content' => '<p>Paragraf uji</p>'], 'sort_order' => 2],
            ['type' => 'stats', 'payload' => ['items' => [['label' => 'SBA', 'value' => '25']]], 'sort_order' => 3],
            ['type' => 'image', 'payload' => ['image_path' => 'x.png', 'caption' => 'Gambar uji'], 'sort_order' => 4],
            ['type' => 'cta', 'payload' => ['title' => 'Ayo Gabung', 'body' => 'Teks CTA', 'label' => 'Daftar', 'url' => '/kontak'], 'sort_order' => 5],
            ['type' => 'quote', 'payload' => ['quote' => 'Kutipan uji', 'author' => 'Penulis'], 'sort_order' => 6],
        ]);

        $this->get('/uji')
            ->assertOk()
            ->assertSee('Judul Hero')
            ->assertSee('Paragraf uji')
            ->assertSee('25')
            ->assertSee('Ayo Gabung')
            ->assertSee('Kutipan uji');
    }

    public function test_unpublished_page_returns_404(): void
    {
        $page = Page::factory()->create(['is_published' => false]);

        $this->get('/'.$page->slug)->assertNotFound();
    }

    public function test_sync_blocks_splits_builder_state_into_rows(): void
    {
        $page = Page::factory()->create();
        $page->syncBlocks([
            ['type' => 'hero', 'data' => ['title' => 'Halo', 'subtitle' => 'Dunia']],
            ['type' => 'stats', 'data' => ['items' => [['label' => 'SBA', 'value' => '25']]]],
        ]);

        $this->assertSame(['hero', 'stats'], $page->blocks()->pluck('type')->all());
        $this->assertSame(['title' => 'Halo', 'subtitle' => 'Dunia'], $page->blocks()->first()->payload);
        $this->assertSame([0, 1], $page->blocks()->pluck('sort_order')->all());

        // Re-sync replaces rows (edit flow), keeping order by index.
        $page->syncBlocks([
            ['type' => 'quote', 'data' => ['quote' => 'Bersatu']],
        ]);
        $this->assertSame(['quote'], $page->blocks()->pluck('type')->all());
    }
}
