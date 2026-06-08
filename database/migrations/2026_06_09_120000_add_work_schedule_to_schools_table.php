<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->json('work_days')->nullable()->after('status');
            $table->time('work_time_from')->nullable()->after('work_days');
            $table->time('work_time_to')->nullable()->after('work_time_from');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropColumn(['work_days', 'work_time_from', 'work_time_to']);
        });
    }
};
