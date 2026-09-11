<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'role', 'organization_id',
        'action', 'description', 'subject_type', 'subject_id', 'ip_address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
