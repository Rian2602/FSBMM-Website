<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseLesson extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'title', 'content', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function quizzes()
    {
        // (** executed: hasMany infers course_lesson_id from the class name;
        // the actual FK column is lesson_id. Without this, counts('quizzes')
        // columns in the lesson list and LessonsRelationManager tables throw
        // "no such column: course_quizzes.course_lesson_id". **)
        return $this->hasMany(CourseQuiz::class, 'lesson_id');
    }
}
