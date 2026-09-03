<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'company',
        'logo_path',
        'description',
        'website',
        'location',
        'founded_year',
        'member_count',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'founded_year' => 'integer',
            'member_count' => 'integer',
        ];
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function hasSbaAccounts(): bool
    {
        return $this->users()->where('role', User::ROLE_SBA_ADMIN)->exists();
    }

    /**
     * Normalize a cleared/blank "tahun berdiri" to null so the nullable column
     * stores null (not the integer cast's 0) when a staff member blanks it.
     */
    public function setFoundedYearAttribute($value): void
    {
        $this->attributes['founded_year'] = ($value === '' || $value === null) ? null : (int) $value;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
