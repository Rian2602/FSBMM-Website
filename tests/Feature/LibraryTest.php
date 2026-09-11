<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Eresource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_e_resource_index_lists_only_published_items_with_file_links(): void
    {
        Storage::fake('public');
        $res = Eresource::factory()->create(['title' => 'AD/ART Federasi', 'is_published' => true]);
        Eresource::factory()->create(['title' => 'Rahasia Internal', 'is_published' => false]);

        $this->get('/e-resource')
            ->assertOk()
            ->assertSee('AD/ART Federasi')
            ->assertDontSee('Rahasia Internal')
            ->assertSee(route('eresources.download', ['eresource' => $res->slug]));
    }

    public function test_signed_download_increments_counter_and_serves_the_file(): void
    {
        Storage::fake('public');
        $res = Eresource::factory()->create();
        Storage::disk('public')->put($res->file_path, '%PDF-1.4 fake');

        $url = URL::signedRoute('eresources.download', ['eresource' => $res->slug]);

        $this->get($url)->assertOk();
        $this->assertSame(1, $res->fresh()->downloads_count);
    }

    public function test_unsigned_download_request_is_rejected(): void
    {
        Storage::fake('public');
        $res = Eresource::factory()->create();

        $this->get(route('eresources.download', ['eresource' => $res->slug]))->assertForbidden();
    }

    public function test_index_renders_expiring_download_link(): void
    {
        Storage::fake('public');
        $res = Eresource::factory()->create();

        $this->get('/e-resource')
            ->assertOk()
            ->assertSee(route('eresources.download', ['eresource' => $res->slug]), false)
            ->assertSee('expires=', false);
    }

    public function test_expired_download_link_is_rejected(): void
    {
        Storage::fake('public');
        $res = Eresource::factory()->create();

        $url = URL::temporarySignedRoute('eresources.download', now()->subMinutes(1), ['eresource' => $res->slug]);

        $this->get($url)->assertForbidden();
    }

    public function test_unpublished_download_with_valid_signature_returns_404(): void
    {
        Storage::fake('public');
        $res = Eresource::factory()->unpublished()->create();
        Storage::disk('public')->put($res->file_path, '%PDF-1.4 rahasia');

        $url = URL::signedRoute('eresources.download', ['eresource' => $res->slug]);

        $this->get($url)->assertNotFound();
        $this->assertSame(0, $res->fresh()->downloads_count);
    }

    public function test_signed_download_of_missing_file_returns_404(): void
    {
        Storage::fake('public');
        $res = Eresource::factory()->create(['file_path' => 'eresources/tidak-ada.pdf']);

        $url = URL::signedRoute('eresources.download', ['eresource' => $res->slug]);

        $this->get($url)->assertNotFound();
        $this->assertSame(0, $res->fresh()->downloads_count);
    }

    public function test_course_catalog_lists_only_published_courses(): void
    {
        Course::factory()->create(['title' => 'Keselamatan Kerja', 'is_published' => true]);
        Course::factory()->create(['title' => 'Draft Kursus', 'is_published' => false]);

        $this->get('/e-learning')
            ->assertOk()
            ->assertSee('Keselamatan Kerja')
            ->assertDontSee('Draft Kursus');
    }

    public function test_download_rejects_path_escaping_disk_root(): void
    {
        Storage::fake('public');

        // Simulasikan file_path yang menunjuk DI LUAR root disk publik
        // (mewakili data DB yang dicorrupt). Flysystem menolak menulis lewat
        // '../', jadi sentinel ditulis native di induk root disk.
        $root = Storage::disk('public')->path('');
        file_put_contents(dirname($root) . '/evil.txt', 'RAHASIA');

        $res = Eresource::factory()->create(['file_path' => '../evil.txt']);
        $url = URL::signedRoute('eresources.download', ['eresource' => $res->slug]);

        $this->get($url)->assertNotFound();
    }
}
