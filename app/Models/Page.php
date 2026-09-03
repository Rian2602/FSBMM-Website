<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

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
