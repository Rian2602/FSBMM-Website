<?php

namespace Tests\Feature;

use App\Models\Eresource;
use App\Rules\RealPdfFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Phase B / T2 "upload fingerprinting": e-resource files get a stored SHA-256
 * so duplicate/scam re-uploads of the same document are detectable, and the
 * RealPdfFile server-side sniff rejects non-PDF payloads.
 */
class EresourceFingerprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_fingerprint_is_stored_on_create(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('eresources/ad-art.pdf', 'PDF-CONTENT-1');

        $eresource = Eresource::factory()->create(['file_path' => 'eresources/ad-art.pdf']);

        $this->assertSame(hash('sha256', 'PDF-CONTENT-1'), $eresource->fresh()->sha256);
    }

    public function test_duplicate_file_is_detectable(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('eresources/a.pdf', 'SAME-BYTES');
        Storage::disk('public')->put('eresources/b.pdf', 'SAME-BYTES');
        Storage::disk('public')->put('eresources/c.pdf', 'OTHER-BYTES');

        $a = Eresource::factory()->create(['file_path' => 'eresources/a.pdf']);
        $b = Eresource::factory()->create(['file_path' => 'eresources/b.pdf']);
        $c = Eresource::factory()->create(['file_path' => 'eresources/c.pdf']);

        $this->assertTrue($a->fresh()->hasDuplicateFile());
        $this->assertTrue($b->fresh()->hasDuplicateFile());
        $this->assertFalse($c->fresh()->hasDuplicateFile());
    }

    public function test_fingerprint_is_null_when_file_is_missing(): void
    {
        Storage::fake('public');

        $eresource = Eresource::factory()->create(); // factory dummy path, no file on disk

        $this->assertNull($eresource->fresh()->sha256);
    }

    public function test_fingerprint_updates_when_file_is_replaced(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('eresources/v1.pdf', 'V1');
        Storage::disk('public')->put('eresources/v2.pdf', 'V2');

        $eresource = Eresource::factory()->create(['file_path' => 'eresources/v1.pdf']);
        $this->assertSame(hash('sha256', 'V1'), $eresource->fresh()->sha256);

        $eresource->update(['file_path' => 'eresources/v2.pdf']);

        $this->assertSame(hash('sha256', 'V2'), $eresource->fresh()->sha256);
    }

    public function test_real_pdf_passes_the_rule(): void
    {
        $path = $this->withTempContent("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");

        $validator = Validator::make(['file' => $path], ['file' => new RealPdfFile]);

        $this->assertFalse($validator->fails());
    }

    public function test_php_payload_renamed_as_pdf_fails_the_rule(): void
    {
        $path = $this->withTempContent("<?php echo 'pwned'; ?>");

        $validator = Validator::make(['file' => $path], ['file' => new RealPdfFile]);

        $this->assertTrue($validator->fails());
    }

    public function test_missing_file_fails_the_rule(): void
    {
        $validator = Validator::make(['file' => __DIR__ . '/this-file-does-not-exist'], ['file' => new RealPdfFile]);

        $this->assertTrue($validator->fails());
    }

    private function withTempContent(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf_test_');
        file_put_contents($path, $content);

        return $path;
    }
}
