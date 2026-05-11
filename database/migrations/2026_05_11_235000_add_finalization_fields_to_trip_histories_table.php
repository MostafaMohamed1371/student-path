<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_histories', function (Blueprint $table): void {
            $table->decimal('final_lat', 10, 7)->nullable()->after('end_time');
            $table->decimal('final_lng', 10, 7)->nullable()->after('final_lat');
        });
    }

    public function down(): void
    {
        Schema::table('trip_histories', function (Blueprint $table): void {
            $table->dropColumn(['final_lat', 'final_lng']);
        });
    }
};
