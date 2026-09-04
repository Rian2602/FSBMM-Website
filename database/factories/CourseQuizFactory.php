<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseQuiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseQuiz>
 */
class CourseQuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'lesson_id' => CourseLesson::factory(),
            'title' => fake()->randomElement(['Kuis 1', 'Kuis 2', 'Evaluasi Akhir']),
            'pass_threshold' => 70,
        ];
    }
}
