<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
 */
class UploadedImageOptimizer
{
    /**
     * @param  UploadedFile  $file  Livewire's TemporaryUploadedFile (extends UploadedFile).
     * @param  string  $directory  Public-disk subdirectory, e.g. 'organizations'.
     * @param  int  $maxWidth  Images wider than this are downscaled (aspect ratio kept).
     * @param  int  $quality  JPEG quality 0-100 for re-encoded (non-PNG) output.
     * @return string Relative path on the 'public' disk, for the model's *_path column.
     */
    public static function store(UploadedFile $file, string $directory, int $maxWidth = 1600, int $quality = 82): string
    {
        $directory = trim($directory, '/');

        // Graceful degradation: no GD, unreadable dimensions, or an
        // unsupported/corrupt image → store the original untouched rather
        // than fail the whole form submission. A missed optimization is
        // safer than a broken upload.
        if (! extension_loaded('gd')) {
            return static::storeOriginal($file, $directory);
        }

        $info = @getimagesize($file->getRealPath());

        if ($info === false) {
            return static::storeOriginal($file, $directory);
        }

        [$width, $height, $type] = $info;

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : false,
            default => false,
        };

        if (! $source || $width <= 0 || $height <= 0) {
            return static::storeOriginal($file, $directory);
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

        Storage::disk('public')->makeDirectory($directory);
        $absolutePath = Storage::disk('public')->path($relativePath);

        // PNG stays PNG (transparency); everything else (JPEG, WEBP input) is
        // re-encoded as JPEG — smaller than PNG for photographic content and
        // consistent output regardless of the source format.
        $written = $isPng
            ? imagepng($source, $absolutePath, 6)
            : imagejpeg($source, $absolutePath, $quality);

        imagedestroy($source);

        if (! $written) {
            return static::storeOriginal($file, $directory);
        }

        return $relativePath;
    }

    private static function storeOriginal(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'public');

        return $path ?: '';
    }
}
