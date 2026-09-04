<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('course_lessons')->cascadeOnDelete();
            $table->string('title');
            // (** executed: nullable so an empty authoring value stores NULL and
            // CourseQuiz::passThreshold() falls back to the course default —
            // plan Task 2 field is ->nullable() with "Kosong = pakai ambang
            // default kursus"; the plan's migration snippet omitted it. **)
            $table->unsignedTinyInteger('pass_threshold')->nullable()->default(70);
            $table->timestamps();
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_quizzes');
    }
};
