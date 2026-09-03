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
}
