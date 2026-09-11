<?php

namespace Database\Factories;

use App\Models\CourseQuiz;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 */
class QuizQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_quiz_id' => CourseQuiz::factory(),
            'question' => fake()->sentence() . '?',
            'sort_order' => 0,
        ];
    }
}
