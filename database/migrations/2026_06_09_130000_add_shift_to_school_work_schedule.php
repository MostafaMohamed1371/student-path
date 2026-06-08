<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->string('shift_period', 16)->nullable()->after('work_days');
            $table->time('evening_work_time_from')->nullable()->after('work_time_to');
            $table->time('evening_work_time_to')->nullable()->after('evening_work_time_from');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropColumn(['shift_period', 'evening_work_time_from', 'evening_work_time_to']);
        });
    }
};
