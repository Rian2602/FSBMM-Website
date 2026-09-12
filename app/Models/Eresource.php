<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Eresource extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'file_path',
        'is_published',
        'downloads_count',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'downloads_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Eresource $eresource): void {
            if ($eresource->isDirty('file_path')) {
                $eresource->sha256 = static::fingerprintPath($eresource->file_path);
            }
        });
    }

    /**
     * Phase B / T2 "upload fingerprinting": SHA-256 of the stored file, written
     * on every file change so duplicate/scam re-uploads of the same document
     * are detectable without reading the file again. Null when the file is
     * missing on disk (deleted or a test-only dummy path).
     */
    public static function fingerprintPath(?string $path): ?string
    {
        // Same logical traversal guard as EresourceController::download: only
        // hash files that live legally inside the public disk root. A corrupt
        // '..'/'/' path must never be read (the Local adapter rejects it — a
        // save would otherwise 500), nor its content hashed.
        if ($path === null || str_contains($path, '..') || str_starts_with($path, '/')) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        return hash('sha256', (string) $disk->get($path));
    }

    /**
     * True when another published e-resource already carries the exact same
     * file bytes as this one (same SHA-256 fingerprint).
     */
    public function hasDuplicateFile(): bool
    {
        return $this->sha256 !== null
            && static::query()
                ->where('sha256', $this->sha256)
                ->whereKeyNot($this->getKey())
                ->exists();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
