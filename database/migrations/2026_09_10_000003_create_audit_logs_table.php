<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Denormalised actor snapshot so the trail stays readable after an
            // account is deleted (user_id becomes null).
            $table->string('user_name')->nullable();
            $table->string('role')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('action');
            $table->string('description');
            $table->nullableMorphs('subject');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
