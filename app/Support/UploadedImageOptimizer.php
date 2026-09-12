<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Downscales + recompresses an uploaded image before it is written to the
 * public disk, so oversized photos (phone-camera JPEGs easily 4000px+/10MB+)
 * never sit on disk at full size or get served to every public-site visitor
 * unscaled. Used as a Filament FileUpload::saveUploadedFileUsing() callback.
 *
 * Deliberately dependency-free: uses PHP's built-in `gd` extension (already
 * present on virtually every PHP/cPanel stack) instead of adding
 * intervention/image or spatie/image-optimizer — one fewer package to keep
 * updated, matches the repo's minimalist-dependency convention.
 *
 * Server-load rationale: maxWidth caps the in-memory GD canvas size (a
 * decoded 6000x4000 truecolor image is ~72MB in memory even before resizing —
 * that alone can exhaust PHP's memory_limit on shared hosting). Pair this
 * with FileUpload::maxSize() at the field level so oversized files are
 * rejected by Livewire's validation BEFORE they ever reach this class —
 * maxSize is the cheap first line of defense, this is the second.
 *
 * (** executed (Phase B / T2 "ext-MIME lock"): this class used to fall back to
 * storeOriginal() — storing the raw bytes untouched with the client's
 * filename — whenever GD/meta-detail checks failed or GD was absent. Any
 * non-image payload (e.g. PHP renamed to `logo.png`) would then persist under
 * public/storage where Caddy's php_server could execute it. The fallback is
 * removed: anything this class cannot decode as a real JPEG/PNG/WEBP now
 * throws RuntimeException and nothing is written. Served output is therefore
 * always GD-re-encoded (polyglot/getimagesize tricks can't survive the
 * re-encode), never attacker-tagged raw bytes. **)
 */
class UploadedImageOptimizer
{
    /**
     * @param  UploadedFile  $file  Livewire's TemporaryUploadedFile (extends UploadedFile).
     * @param  string  $directory  Public-disk subdirectory, e.g. 'organizations'.
     * @param  int  $maxWidth  Images wider than this are downscaled (aspect ratio kept).
     * @param  int  $quality  JPEG quality 0-100 for re-encoded (non-PNG) output.
     * @return string Relative path on the 'public' disk, for the model's *_path column.
     *
     * @throws RuntimeException When the upload is not a decodable JPEG/PNG/WEBP
     *                          image, or the re-encoded output cannot be written.
     */
    public static function store(UploadedFile $file, string $directory, int $maxWidth = 1600, int $quality = 82): string
    {
        $directory = trim($directory, '/');

        if (! extension_loaded('gd')) {
            throw new RuntimeException('Environment lacks GD; uploads are disabled.');
        }

        $info = @getimagesize($file->getRealPath());

        if ($info === false) {
            throw new RuntimeException('File bukan gambar yang valid (JPEG/PNG/WEBP).');
        }

        [$width, $height, $type] = $info;

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : false,
            default => false,
        };

        if (! $source || $width <= 0 || $height <= 0) {
            throw new RuntimeException('File bukan gambar yang valid (JPEG/PNG/WEBP).');
        }

        $isPng = $type === IMAGETYPE_PNG;

        if ($width > $maxWidth) {
            $targetWidth = $maxWidth;
            $targetHeight = (int) round($height * ($maxWidth / $width));

            $resized = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($isPng) {
                // Preserve transparency (logos commonly have a transparent background).
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }

            imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        $filename = Str::random(40) . ($isPng ? '.png' : '.jpg');
        $relativePath = $directory . '/' . $filename;

        // (** executed: GD needs a real on-disk file, but the target disk may
        // be an S3-compatible object store (Vercel container runtime has a
        // read-only filesystem). Render to a temp file first, stream it onto
        // the disk, then drop the temp. Local disks accept the same streams,
        // so this stays driver-agnostic. **)
        $tmpPath = tempnam(sys_get_temp_dir(), 'img_');
        if ($tmpPath === false) {
            throw new RuntimeException('Gagal menyiapkan file sementara untuk gambar.');
        }

        // PNG stays PNG (transparency); everything else (JPEG, WEBP input) is
        // re-encoded as JPEG — smaller than PNG for photographic content and
        // consistent output regardless of the source format.
        $written = $isPng
            ? imagepng($source, $tmpPath, 6)
            : imagejpeg($source, $tmpPath, $quality);

        imagedestroy($source);

        if (! $written) {
            @unlink($tmpPath);

            throw new RuntimeException('Gagal mengompresi gambar.');
        }

        // (** executed: both write paths (put of a full buffer vs streaming)
        // exist; the buffer variant is fine for capped uploads (maxSize), and
        // keeps the failure branch simple. **)
        $stored = Storage::disk('public')->put($relativePath, (string) file_get_contents($tmpPath));
        @unlink($tmpPath);

        if (! $stored) {
            throw new RuntimeException('Gagal menyimpan gambar.');
        }

        return $relativePath;
    }
}
