<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('period'); // 'YYYY-MM'
            $table->decimal('amount', 14, 2);
            $table->date('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['member_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dues');
    }
};
