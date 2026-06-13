<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_histories', function (Blueprint $table): void {
            $table->boolean('is_recurring_template')->default(false)->after('status');
            $table->foreignId('recurring_template_id')
                ->nullable()
                ->after('is_recurring_template')
                ->constrained('trip_histories')
                ->nullOnDelete();

            $table->index(['is_recurring_template', 'driver_id']);
            $table->index(['recurring_template_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::table('trip_histories', function (Blueprint $table): void {
            $table->dropForeign(['recurring_template_id']);
            $table->dropIndex(['is_recurring_template', 'driver_id']);
            $table->dropIndex(['recurring_template_id', 'start_time']);
            $table->dropColumn(['is_recurring_template', 'recurring_template_id']);
        });
    }
};
