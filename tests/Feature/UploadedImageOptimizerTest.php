<?php

namespace Tests\Feature;

use App\Support\UploadedImageOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadedImageOptimizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('gd extension not available in this environment.');
        }

        Storage::fake('public');
    }

    public function test_wide_image_is_downscaled_to_max_width(): void
    {
        $file = UploadedFile::fake()->image('foto-besar.jpg', 3000, 2000);

        $path = UploadedImageOptimizer::store($file, 'articles', maxWidth: 1600);

        Storage::disk('public')->assertExists($path);

        [$width, $height] = getimagesize(Storage::disk('public')->path($path));

        $this->assertSame(1600, $width);
        $this->assertSame(1067, $height); // round(2000 * 1600 / 3000)
    }

    public function test_image_narrower_than_max_width_is_not_upscaled(): void
    {
        $file = UploadedFile::fake()->image('kecil.jpg', 400, 300);

        $path = UploadedImageOptimizer::store($file, 'articles', maxWidth: 1600);

        [$width, $height] = getimagesize(Storage::disk('public')->path($path));

        $this->assertSame(400, $width);
        $this->assertSame(300, $height);
    }

    public function test_output_path_is_within_requested_directory(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 1000, 1000);

        $path = UploadedImageOptimizer::store($file, 'organizations', maxWidth: 512);

        $this->assertStringStartsWith('organizations/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_png_transparency_is_preserved_as_png(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 800, 800);

        $path = UploadedImageOptimizer::store($file, 'organizations', maxWidth: 512);

        $this->assertStringEndsWith('.png', $path);
    }

    public function test_jpeg_input_is_reencoded_as_jpg(): void
    {
        $file = UploadedFile::fake()->image('foto.jpg', 800, 600);

        $path = UploadedImageOptimizer::store($file, 'articles', maxWidth: 1600);

        $this->assertStringEndsWith('.jpg', $path);
    }

    // Phase B / T2 "ext-MIME lock": a payload that is NOT a decodable
    // JPEG/PNG/WEBP is rejected outright — the old storeOriginal() fallback
    // used to persist raw attacker-tagged bytes (e.g. PHP renamed to logo.png),
    // which Caddy's php_server could later execute under public/storage.

    public function test_php_payload_renamed_as_png_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('logo.png', "<?php echo 'pwned'; ?>");

        $this->expectException(\RuntimeException::class);

        UploadedImageOptimizer::store($file, 'organizations', maxWidth: 512);

        Storage::disk('public')->assertDirectoryEmpty('organizations');
    }

    public function test_text_file_renamed_as_jpg_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('logo.jpg', 2048);

        $this->expectException(\RuntimeException::class);

        UploadedImageOptimizer::store($file, 'organizations', maxWidth: 512);
    }

    public function test_really_valid_png_still_stored(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 600, 400);

        $path = UploadedImageOptimizer::store($file, 'organizations', maxWidth: 512);

        Storage::disk('public')->assertExists($path);
    }
}
