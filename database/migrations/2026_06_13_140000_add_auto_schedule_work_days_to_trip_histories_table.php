<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_histories', function (Blueprint $table): void {
            $table->boolean('auto_schedule_work_days')
                ->default(false)
                ->after('is_recurring_template');
        });

        DB::table('trip_histories')
            ->where('is_recurring_template', true)
            ->whereNull('recurring_template_id')
            ->update(['auto_schedule_work_days' => true]);
    }

    public function down(): void
    {
        Schema::table('trip_histories', function (Blueprint $table): void {
            $table->dropColumn('auto_schedule_work_days');
        });
    }
};
