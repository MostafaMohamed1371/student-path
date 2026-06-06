<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_neighborhood', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('neighborhood_id')->constrained('neighborhoods')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['driver_id', 'neighborhood_id']);
        });

        Schema::create('transport_route_neighborhood', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transport_route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->foreignId('neighborhood_id')->constrained('neighborhoods')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['transport_route_id', 'neighborhood_id'], 'transport_route_neighborhood_unique');
        });

        if (Schema::hasColumn('drivers', 'neighborhood_id')) {
            foreach (DB::table('drivers')->whereNotNull('neighborhood_id')->orderBy('id')->get() as $row) {
                DB::table('driver_neighborhood')->insertOrIgnore([
                    'driver_id' => $row->id,
                    'neighborhood_id' => $row->neighborhood_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('drivers', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('neighborhood_id');
            });
        }

        if (Schema::hasColumn('transport_routes', 'neighborhood_id')) {
            foreach (DB::table('transport_routes')->whereNotNull('neighborhood_id')->orderBy('id')->get() as $row) {
                DB::table('transport_route_neighborhood')->insertOrIgnore([
                    'transport_route_id' => $row->id,
                    'neighborhood_id' => $row->neighborhood_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('transport_routes', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('neighborhood_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('transport_routes', function (Blueprint $table): void {
            $table->foreignId('neighborhood_id')->nullable()->after('area_id')->constrained('neighborhoods')->nullOnDelete();
        });

        Schema::table('drivers', function (Blueprint $table): void {
            $table->foreignId('neighborhood_id')->nullable()->after('area_id')->constrained('neighborhoods')->nullOnDelete();
        });

        foreach (DB::table('transport_route_neighborhood')->orderBy('id')->get() as $row) {
            DB::table('transport_routes')
                ->where('id', $row->transport_route_id)
                ->whereNull('neighborhood_id')
                ->update(['neighborhood_id' => $row->neighborhood_id]);
        }

        foreach (DB::table('driver_neighborhood')->orderBy('id')->get() as $row) {
            DB::table('drivers')
                ->where('id', $row->driver_id)
                ->whereNull('neighborhood_id')
                ->update(['neighborhood_id' => $row->neighborhood_id]);
        }

        Schema::dropIfExists('transport_route_neighborhood');
        Schema::dropIfExists('driver_neighborhood');
    }
};
