<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'aktif';

    public const STATUS_INACTIVE = 'nonaktif';

    protected $fillable = [
        'organization_id', 'nik', 'name', 'gender', 'birthplace', 'birthdate',
        'address', 'department', 'position', 'basic_salary', 'join_date',
        'education', 'status',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'join_date' => 'date',
            'basic_salary' => 'decimal:2',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function dues()
    {
        return $this->hasMany(Due::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }
}
