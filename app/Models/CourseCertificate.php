<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'course_id', 'certificate_number', 'verification_token',
        'final_score', 'issued_at', 'revoked_at', 'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'final_score' => 'integer',
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
