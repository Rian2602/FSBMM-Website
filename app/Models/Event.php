<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id', 'title', 'event_date', 'description'];

    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
