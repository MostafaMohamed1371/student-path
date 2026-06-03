<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_routes', function (Blueprint $table): void {
            $table->foreignId('district_id')->nullable()->after('school_id')->constrained('districts')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->after('district_id')->constrained('areas')->nullOnDelete();
            $table->foreignId('neighborhood_id')->nullable()->after('area_id')->constrained('neighborhoods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transport_routes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('neighborhood_id');
            $table->dropConstrainedForeignId('area_id');
            $table->dropConstrainedForeignId('district_id');
        });
    }
};
