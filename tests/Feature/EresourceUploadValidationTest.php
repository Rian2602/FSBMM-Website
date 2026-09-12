<?php

namespace Tests\Feature;

use App\Filament\Resources\EresourceResource\Pages\CreateEresource;
use App\Models\Eresource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase B / T2 evaluation gate: proves the server-side RealPdfFile sniff
 * actually fires through Filament's live upload pipeline (the form-level
 * `->rules()` value is a temp *path string*, not an UploadedFile — a fake
 * regression check that a valid PDF survives and a PHP payload never does).
 */
class EresourceUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_valid_pdf_upload_passes_the_form_and_is_stored(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $file = TemporaryUploadedFile::fake()->createWithContent('dokumen.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");

        Livewire::actingAs($admin)
            ->test(CreateEresource::class)
            ->fillForm([
                'title' => 'AD/ART Federasi',
                'slug' => 'ad-art-federasi',
                'description' => 'Dokumen.',
                'file_path' => $file,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $eresource = Eresource::where('slug', 'ad-art-federasi')->firstOrFail();

        $this->assertNotNull($eresource->sha256);
        $this->assertSame(hash('sha256', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"), $eresource->sha256);
        Storage::disk('public')->assertExists($eresource->file_path);
    }

    public function test_php_payload_renamed_as_pdf_is_rejected_by_the_form(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $file = TemporaryUploadedFile::fake()->createWithContent('dokumen.pdf', "<?php echo 'pwned'; ?>");

        Livewire::actingAs($admin)
            ->test(CreateEresource::class)
            ->fillForm([
                'title' => 'Trofis',
                'slug' => 'trofis',
                'description' => 'Dokumen.',
                'file_path' => $file,
            ])
            ->call('create');

        $this->assertDatabaseMissing('eresources', ['slug' => 'trofis']);
        Storage::disk('public')->assertDirectoryEmpty('eresources');
    }
}
