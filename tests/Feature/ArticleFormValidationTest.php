<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleResource\Pages\CreateArticle;
use App\Models\Category;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ArticleFormValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_requires_an_author(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $category = Category::factory()->create();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($editor)
            ->test(CreateArticle::class)
            ->fillForm([
                'title' => 'Artikel Tanpa Penulis',
                'slug' => 'artikel-tanpa-penulis',
                'excerpt' => 'Ringkasan.',
                'body' => '<p>Isi.</p>',
                'category_id' => $category->id,
                'author_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['author_id' => 'required']);
    }
}
