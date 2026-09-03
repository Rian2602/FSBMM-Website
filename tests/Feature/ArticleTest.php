<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_index_lists_published_articles_newest_first(): void
    {
        $category = Category::factory()->create(['name' => 'Kabar Federasi']);
        Article::factory()->create(['title' => 'Berita Lama', 'published_at' => now()->subDays(2), 'category_id' => $category->id]);
        Article::factory()->create(['title' => 'Berita Baru', 'published_at' => now(), 'category_id' => $category->id]);
        Article::factory()->draft()->create(['title' => 'Draft Rahasia', 'category_id' => $category->id]);

        $this->get('/berita')
            ->assertOk()
            ->assertSeeInOrder(['Berita Baru', 'Berita Lama'])
            ->assertDontSee('Draft Rahasia');
    }

    public function test_future_dated_published_article_is_treated_as_draft(): void
    {
        Article::factory()->create([
            'title' => 'Belum Tayang',
            'slug' => 'belum-tayang',
            'published_at' => now()->addDay(),
        ]);

        $this->get('/berita')->assertDontSee('Belum Tayang');
        $this->get('/berita/belum-tayang')->assertNotFound();
    }

    public function test_article_show_renders_body(): void
    {
        $article = Article::factory()->create(['body' => '<p>Isi artikel lengkap untuk diuji.</p>']);

        $this->get('/berita/'.$article->slug)
            ->assertOk()
            ->assertSee($article->title)
            ->assertSee('Isi artikel lengkap untuk diuji.');
    }

    public function test_index_can_be_filtered_by_category(): void
    {
        $kabar = Category::factory()->create(['name' => 'Kabar Federasi', 'slug' => 'kabar-federasi']);
        $edukasi = Category::factory()->create(['name' => 'Edukasi Anggota', 'slug' => 'edukasi-anggota']);
        Article::factory()->create(['title' => 'Berita Federasi A', 'category_id' => $kabar->id, 'published_at' => now()]);
        Article::factory()->create(['title' => 'Artikel Edukasi B', 'category_id' => $edukasi->id, 'published_at' => now()]);

        $this->get('/berita?category=kabar-federasi')
            ->assertOk()
            ->assertSee('Berita Federasi A')
            ->assertDontSee('Artikel Edukasi B');
    }

    public function test_index_hides_categories_without_published_articles(): void
    {
        $empty = Category::factory()->create(['name' => 'Kosong']);
        $published = Category::factory()->create(['name' => 'Terisi']);
        Article::factory()->create(['category_id' => $published->id, 'published_at' => now()]);
        Article::factory()->draft()->create(['category_id' => $empty->id]);

        $this->get('/berita')
            ->assertOk()
            ->assertSee('Terisi')
            ->assertDontSee('Kosong');
    }

    public function test_draft_article_direct_slug_returns_404(): void
    {
        Article::factory()->draft()->create(['title' => 'Draf Belum Terbit', 'slug' => 'draf-belum-terbit']);

        $this->get('/berita/draf-belum-terbit')->assertNotFound();
    }
}
