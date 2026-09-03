<?php

namespace Tests\Feature;

use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Models\Page;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Exercises the full Filament create-page form -> Page::syncBlocks() round
 * trip, which PageTest only unit-tests. Guards the Builder dehydrate shape
 * ({type, data}) against the syncBlocks mapping so block edits persist.
 */
class PageResourceRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_page_via_form_persists_blocks_in_order(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($editor)
            ->test(CreatePage::class)
            ->fillForm([
                'title' => 'Halaman Uji Roundtrip',
                'slug' => 'uji-roundtrip',
                'is_published' => true,
                'blocks' => [
                    ['type' => 'hero', 'data' => ['title' => 'Hero Uji', 'subtitle' => 'Sub']],
                    ['type' => 'stats', 'data' => ['items' => [['label' => 'SBA', 'value' => '25']]]],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::where('slug', 'uji-roundtrip')->firstOrFail();

        $this->assertSame(['hero', 'stats'], $page->blocks()->pluck('type')->all());
        $this->assertSame([0, 1], $page->blocks()->pluck('sort_order')->all());

        // Filament dehydrates unfilled optional hero fields as null keys, so
        // assert the meaningful payload rather than exact equality.
        $hero = $page->blocks()->first()->payload;
        $this->assertSame('Hero Uji', $hero['title']);
        $this->assertSame('Sub', $hero['subtitle']);
        $this->assertSame([['label' => 'SBA', 'value' => '25']], $page->blocks()->get()[1]->payload['items']);

        // Public render reflects the persisted blocks.
        $this->get('/uji-roundtrip')
            ->assertOk()
            ->assertSee('Hero Uji')
            ->assertSee('25');
    }
}
