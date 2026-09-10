<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('card_number')->unique();
            $table->string('verification_token')->unique();
            $table->string('status')->default('aktif'); // aktif | dicabut
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('member_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_cards');
    }
};
