<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'member_id', 'reporter_name', 'title', 'description',
        'status', 'submitted_at', 'resolved_at', 'handled_by',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'date',
            'resolved_at' => 'date',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
