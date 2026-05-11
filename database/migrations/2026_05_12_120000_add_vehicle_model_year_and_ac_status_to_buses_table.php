<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buses', function (Blueprint $table): void {
            $table->unsignedSmallInteger('vehicle_model_year')->nullable()->after('type');
            $table->string('ac_status', 16)->nullable()->after('vehicle_model_year');
        });
    }

    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table): void {
            $table->dropColumn(['vehicle_model_year', 'ac_status']);
        });
    }
};
