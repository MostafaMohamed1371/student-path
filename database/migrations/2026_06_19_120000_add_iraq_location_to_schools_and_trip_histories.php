<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schools', 'district_id')) {
            Schema::table('schools', function (Blueprint $table): void {
                $table->foreignId('district_id')->nullable()->after('name_en')->constrained('districts')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('schools', 'area_id')) {
            Schema::table('schools', function (Blueprint $table): void {
                $table->foreignId('area_id')->nullable()->after('district_id')->constrained('areas')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('schools', 'neighborhood_id')) {
            Schema::table('schools', function (Blueprint $table): void {
                $table->foreignId('neighborhood_id')->nullable()->after('area_id')->constrained('neighborhoods')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('trip_histories', 'start_district_id')) {
            Schema::table('trip_histories', function (Blueprint $table): void {
                $table->foreignId('start_district_id')->nullable()->after('start_longitude')->constrained('districts')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('trip_histories', 'start_area_id')) {
            Schema::table('trip_histories', function (Blueprint $table): void {
                $table->foreignId('start_area_id')->nullable()->after('start_district_id')->constrained('areas')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('trip_histories', 'start_neighborhood_id')) {
            Schema::table('trip_histories', function (Blueprint $table): void {
                $table->foreignId('start_neighborhood_id')->nullable()->after('start_area_id')->constrained('neighborhoods')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('trip_histories', 'start_neighborhood_id')) {
            Schema::table('trip_histories', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('start_neighborhood_id');
                $table->dropConstrainedForeignId('start_area_id');
                $table->dropConstrainedForeignId('start_district_id');
            });
        }

        if (Schema::hasColumn('schools', 'neighborhood_id')) {
            Schema::table('schools', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('neighborhood_id');
                $table->dropConstrainedForeignId('area_id');
                $table->dropConstrainedForeignId('district_id');
            });
        }
    }
};
