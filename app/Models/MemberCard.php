<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberCard extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'aktif';

    public const STATUS_REVOKED = 'dicabut';

    protected $fillable = [
        'organization_id', 'member_id', 'card_number', 'verification_token',
        'status', 'issued_at', 'revoked_at', 'revocation_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
