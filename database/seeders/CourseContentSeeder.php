<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseQuiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Illuminate\Database\Seeder;

class CourseContentSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::firstOrCreate(
            ['slug' => 'dasar-kepengurusan-serikat'],
            [
                'title' => 'Dasar Kepengurusan Serikat',
                'description' => 'Pengantar peran dan tanggung jawab pengurus serikat pekerja.',
                'level' => 'dasar',
                'is_published' => true,
                'pass_threshold' => 70,
            ]
        );

        if ($course->lessons()->exists()) {
            return; // idempotent
        }

        $lesson = CourseLesson::create([
            'course_id' => $course->id,
            'title' => 'Peran Pengurus',
            'content' => '<h2>Peran Pengurus</h2><p>Pengurus mewakili dan melindungi hak anggota serikat.</p>',
            'sort_order' => 1,
        ]);

        CourseLesson::create([
            'course_id' => $course->id,
            'title' => 'Struktur Organisasi',
            'content' => '<h2>Struktur Organisasi</h2><p>Bentuk serikat dan hierarki kepengurusan.</p>',
            'sort_order' => 2,
        ]);

        $quiz = CourseQuiz::create([
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'title' => 'Kuis Peran Pengurus',
            'pass_threshold' => 70,
        ]);

        $q = QuizQuestion::create(['course_quiz_id' => $quiz->id, 'question' => 'Siapa yang diwakili pengurus?', 'sort_order' => 1]);
        QuizOption::create(['quiz_question_id' => $q->id, 'option' => 'Anggota', 'is_correct' => true, 'sort_order' => 1]);
        QuizOption::create(['quiz_question_id' => $q->id, 'option' => 'Pemilik pabrik', 'is_correct' => false, 'sort_order' => 2]);

        $finalQuiz = CourseQuiz::create([
            'course_id' => $course->id,
            'lesson_id' => null,
            'title' => 'Evaluasi Akhir',
            'pass_threshold' => 70,
        ]);

        $fq = QuizQuestion::create(['course_quiz_id' => $finalQuiz->id, 'question' => 'Serikat bertujuan melindungi…', 'sort_order' => 1]);
        QuizOption::create(['quiz_question_id' => $fq->id, 'option' => 'Hak pekerja', 'is_correct' => true, 'sort_order' => 1]);
        QuizOption::create(['quiz_question_id' => $fq->id, 'option' => 'Keuntungan perusahaan', 'is_correct' => false, 'sort_order' => 2]);
    }
}
