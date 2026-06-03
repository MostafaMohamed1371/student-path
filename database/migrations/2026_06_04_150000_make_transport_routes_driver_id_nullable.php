<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_routes', function (Blueprint $table): void {
            $table->dropForeign(['driver_id']);
            $table->dropUnique(['driver_id', 'trip_type']);
        });

        Schema::table('transport_routes', function (Blueprint $table): void {
            $table->unsignedBigInteger('driver_id')->nullable()->change();
        });

        Schema::table('transport_routes', function (Blueprint $table): void {
            $table->foreign('driver_id')->references('id')->on('drivers')->nullOnDelete();
            $table->unique(['driver_id', 'trip_type']);
        });
    }

    public function down(): void
    {
        Schema::table('transport_routes', function (Blueprint $table): void {
            $table->dropForeign(['driver_id']);
            $table->dropUnique(['driver_id', 'trip_type']);
        });

        Schema::table('transport_routes', function (Blueprint $table): void {
            $table->unsignedBigInteger('driver_id')->nullable(false)->change();
        });

        Schema::table('transport_routes', function (Blueprint $table): void {
            $table->foreign('driver_id')->references('id')->on('drivers')->cascadeOnDelete();
            $table->unique(['driver_id', 'trip_type']);
        });
    }
};
