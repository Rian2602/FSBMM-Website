<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // hadir | izin | tidak_hadir
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
