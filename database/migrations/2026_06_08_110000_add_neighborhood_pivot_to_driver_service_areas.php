<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_service_area_neighborhood', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_service_area_id')->constrained('driver_service_areas')->cascadeOnDelete();
            $table->foreignId('neighborhood_id')->constrained('neighborhoods')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['driver_service_area_id', 'neighborhood_id'], 'driver_service_area_neighborhood_unique');
        });

        if (Schema::hasColumn('driver_service_areas', 'neighborhood_id')) {
            foreach (DB::table('driver_service_areas')->whereNotNull('neighborhood_id')->orderBy('id')->get() as $row) {
                DB::table('driver_service_area_neighborhood')->insertOrIgnore([
                    'driver_service_area_id' => $row->id,
                    'neighborhood_id' => $row->neighborhood_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('driver_service_areas', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('neighborhood_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('driver_service_areas', function (Blueprint $table): void {
            if (! Schema::hasColumn('driver_service_areas', 'neighborhood_id')) {
                $table->foreignId('neighborhood_id')->nullable()->after('area_id')->constrained('neighborhoods')->nullOnDelete();
            }
        });

        foreach (DB::table('driver_service_area_neighborhood')->orderBy('id')->get() as $row) {
            DB::table('driver_service_areas')
                ->where('id', $row->driver_service_area_id)
                ->whereNull('neighborhood_id')
                ->update(['neighborhood_id' => $row->neighborhood_id]);
        }

        Schema::dropIfExists('driver_service_area_neighborhood');
    }
};
