<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->boolean('passed');
            $table->timestamp('attempt_date');
            $table->timestamps();
            $table->index(['user_id', 'course_quiz_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_attempts');
    }
};
