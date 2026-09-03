<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageBlock extends Model
{
    use HasFactory;

    public const TYPES = ['hero', 'rich_text', 'image', 'stats', 'cta', 'quote'];

    protected $fillable = ['page_id', 'type', 'payload', 'sort_order'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
