<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Page extends Model
{
    use HasFactory;

    /** Slugs hard-wired to named public routes (/, /tentang, /kontak). */
    public const STRUCTURAL_SLUGS = ['home', 'tentang', 'kontak'];

    protected $fillable = [
        'title',
        'slug',
        'meta_title',
        'meta_description',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function blocks()
    {
        return $this->hasMany(PageBlock::class)->orderBy('sort_order');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function isStructural(): bool
    {
        return in_array($this->slug, self::STRUCTURAL_SLUGS, true);
    }

    protected static function booted(): void
    {
        // Enforcement backstop for every delete/rename path (UI, bulk, code,
        // tinker): structural slugs back the named routes /, /tentang, /kontak.
        static::deleting(function (Page $page): void {
            abort_if($page->isStructural(), 403, 'Halaman struktural (home/tentang/kontak) tidak dapat dihapus.');
        });

        static::updating(function (Page $page): void {
            // Check the ORIGINAL slug: at updating time the attribute already
            // holds the new value, so isStructural() alone would see the renamed slug.
            $originalSlug = $page->getOriginal('slug');

            if (in_array($originalSlug, self::STRUCTURAL_SLUGS, true) && $originalSlug !== $page->slug) {
                throw ValidationException::withMessages([
                    'slug' => 'Slug halaman struktural tidak dapat diubah.',
                ]);
            }
        });
    }

    /**
     * Replace this page's blocks from Filament Repeater+Builder state.
     *
     * Each item is ['payload' => ['type' => 'hero', 'data' => [...]]] (Builder
     * dehydrates to {type, data}); the type column and payload contents are
     * split back into normalized page_blocks rows ordered by index.
     *
     * @param  array<int, array{payload: array{type: string, data: array}}>  $items
     */
    public function syncBlocks(array $items): void
    {
        $this->blocks()->delete();

        $rows = [];
        foreach (array_values($items) as $index => $item) {
            $payload = $item['payload'] ?? $item;

            if (empty($payload['type'])) {
                continue;
            }

            $rows[] = [
                'type' => $payload['type'],
                'payload' => $payload['data'] ?? [],
                'sort_order' => $index,
            ];
        }

        if ($rows !== []) {
            $this->blocks()->createMany($rows);
        }
    }
}
