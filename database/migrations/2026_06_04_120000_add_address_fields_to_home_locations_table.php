<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_locations', function (Blueprint $table): void {
            $table->string('district_area')->nullable()->after('formatted_address');
            $table->string('nearest_landmark')->nullable()->after('district_area');
        });
    }

    public function down(): void
    {
        Schema::table('home_locations', function (Blueprint $table): void {
            $table->dropColumn(['district_area', 'nearest_landmark']);
        });
    }
};
