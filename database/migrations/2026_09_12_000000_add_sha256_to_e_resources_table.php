<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eresources', function (Blueprint $table): void {
            $table->string('sha256', 64)
                ->nullable()
                ->after('file_path')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('eresources', function (Blueprint $table): void {
            $table->dropIndex(['sha256']);
            $table->dropColumn('sha256');
        });
    }
};
