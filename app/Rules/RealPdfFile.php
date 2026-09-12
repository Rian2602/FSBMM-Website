<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Phase B / T2 "ext-MIME lock" for the only non-image upload on the site:
 * e-resource documents. Filament's `acceptedFileTypes` is a client-side hint;
 * this rule sniffs the actual uploaded bytes on the server and rejects
 * anything that is not a real PDF. A PHP script renamed to `dokumen.pdf` (or a
 * polyglot prefix + PHP tail) never reaches the public disk where Caddy's
 * php_server could execute it.
 *
 * (** executed (Phase B evaluation gate): Filament v3.3 form-level `->rules()`
 * receives the pending upload as a `TemporaryUploadedFile` OBJECT (not the
 * disk-relative temp path string), and Laravel's built-in image/mimes rules
 * silently skip non-UploadedFile values — so the first shipped version of this
 * rule, which treated the value as a path string, rejected every genuine PDF.
 * The value is now sniffed through getRealPath() when it is an UploadedFile;
 * plain path strings are still accepted for direct/unit-test use. **)
 *
 * Detection: finfo() MIME when the extension is available (standard PHP
 * build), falling back to the PDF magic bytes "%PDF-" for environments where
 * fileinfo is missing — never the client-supplied extension or MIME.
 */
class RealPdfFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $path = null;

        if ($value instanceof UploadedFile) {
            $path = $value->getRealPath() ?: null;
        } elseif (is_string($value)) {
            $path = $value;
        }

        if ($path === null || ! is_file($path)) {
            $fail('File bukan PDF yang valid.');

            return;
        }

        if (class_exists(\finfo::class)) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

            if (is_string($mime) && in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
                return;
            }
        }

        $head = @file_get_contents($path, false, null, 0, 1024);

        if (is_string($head) && str_starts_with($head, '%PDF-')) {
            return;
        }

        $fail('File bukan PDF yang valid.');
    }
}
