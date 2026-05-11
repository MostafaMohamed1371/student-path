<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table): void {
            $table->string('present_type', 64)->nullable()->after('notes');
            $table->text('moving_point')->nullable()->after('present_type');
            $table->text('stop_point')->nullable()->after('moving_point');
            $table->decimal('subscribe_price', 15, 2)->nullable()->after('stop_point');
        });
    }

    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'present_type',
                'moving_point',
                'stop_point',
                'subscribe_price',
            ]);
        });
    }
};
