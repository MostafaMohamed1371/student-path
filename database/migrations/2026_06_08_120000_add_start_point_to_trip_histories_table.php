<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_histories', function (Blueprint $table): void {
            $table->string('start_address')->nullable()->after('location');
            $table->decimal('start_latitude', 10, 7)->nullable()->after('start_address');
            $table->decimal('start_longitude', 10, 7)->nullable()->after('start_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('trip_histories', function (Blueprint $table): void {
            $table->dropColumn(['start_address', 'start_latitude', 'start_longitude']);
        });
    }
};
