<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseAttempt extends Model
{
    use HasFactory;

    protected $fillable = ['course_quiz_id', 'user_id', 'score', 'passed', 'attempt_date'];

    protected function casts(): array
    {
        return ['score' => 'integer', 'passed' => 'boolean', 'attempt_date' => 'datetime'];
    }

    public function quiz()
    {
        return $this->belongsTo(CourseQuiz::class, 'course_quiz_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
