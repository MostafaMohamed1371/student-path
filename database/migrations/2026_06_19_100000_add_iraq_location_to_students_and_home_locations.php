<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('students', 'district_id')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->foreignId('district_id')->nullable()->after('school_id')->constrained('districts')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('students', 'area_id')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->foreignId('area_id')->nullable()->after('district_id')->constrained('areas')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('students', 'neighborhood_id')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->foreignId('neighborhood_id')->nullable()->after('area_id')->constrained('neighborhoods')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('home_locations', 'district_id')) {
            Schema::table('home_locations', function (Blueprint $table): void {
                $table->foreignId('district_id')->nullable()->after('user_id')->constrained('districts')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('home_locations', 'area_id')) {
            Schema::table('home_locations', function (Blueprint $table): void {
                $table->foreignId('area_id')->nullable()->after('district_id')->constrained('areas')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('home_locations', 'neighborhood_id')) {
            Schema::table('home_locations', function (Blueprint $table): void {
                $table->foreignId('neighborhood_id')->nullable()->after('area_id')->constrained('neighborhoods')->nullOnDelete();
            });
        }

        Schema::table('home_locations', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('home_locations', 'neighborhood_id')) {
            Schema::table('home_locations', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('neighborhood_id');
                $table->dropConstrainedForeignId('area_id');
                $table->dropConstrainedForeignId('district_id');
            });
        }

        if (Schema::hasColumn('students', 'neighborhood_id')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('neighborhood_id');
                $table->dropConstrainedForeignId('area_id');
                $table->dropConstrainedForeignId('district_id');
            });
        }
    }
};
