<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trip_histories', function (Blueprint $table): void {
            $table->decimal('distance_km', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trip_histories', function (Blueprint $table): void {
            $table->decimal('distance_km', 8, 2)->default(0)->change();
        });
    }
};
