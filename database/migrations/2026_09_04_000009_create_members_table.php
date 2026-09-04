<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('nik');
            $table->string('name');
            $table->string('gender', 1)->nullable(); // 'L' | 'P'
            $table->string('birthplace')->nullable();
            $table->date('birthdate')->nullable();
            $table->text('address')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->decimal('basic_salary', 14, 2)->nullable();
            $table->date('join_date')->nullable();
            $table->string('education')->nullable();
            $table->string('status')->default('aktif'); // aktif | nonaktif
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'nik']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
