<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    public const LEVELS = ['dasar', 'menengah', 'lanjut'];

    protected $fillable = ['title', 'slug', 'description', 'level', 'is_published', 'pass_threshold'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'pass_threshold' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function lessons()
    {
        return $this->hasMany(CourseLesson::class)->orderBy('sort_order');
    }

    public function quizzes()
    {
        return $this->hasMany(CourseQuiz::class);
    }

    /** The course's final quiz (lesson_id null), if any. */
    public function finalQuiz(): ?CourseQuiz
    {
        return $this->quizzes()->whereNull('lesson_id')->first();
    }
}
