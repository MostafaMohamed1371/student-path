<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transport_routes', 'trip_type')) {
            Schema::table('transport_routes', function (Blueprint $table): void {
                $table->string('trip_type', 32)->nullable()->after('shift_period');
                $table->string('start_address')->nullable()->after('trip_type');
                $table->decimal('start_latitude', 10, 7)->nullable()->after('start_address');
                $table->decimal('start_longitude', 10, 7)->nullable()->after('start_latitude');
            });
        }

        if ($this->hasUniqueIndex('transport_routes', 'transport_routes_driver_id_shift_period_unique')) {
            Schema::table('transport_routes', function (Blueprint $table): void {
                $table->dropForeign(['driver_id']);
                $table->dropUnique(['driver_id', 'shift_period']);
                $table->foreign('driver_id')->references('id')->on('drivers')->cascadeOnDelete();
            });
        }

        if (! $this->hasUniqueIndex('transport_routes', 'transport_routes_driver_id_trip_type_unique')) {
            Schema::table('transport_routes', function (Blueprint $table): void {
                $table->unique(['driver_id', 'trip_type']);
            });
        }
    }

    public function down(): void
    {
        if ($this->hasUniqueIndex('transport_routes', 'transport_routes_driver_id_trip_type_unique')) {
            Schema::table('transport_routes', function (Blueprint $table): void {
                $table->dropForeign(['driver_id']);
                $table->dropUnique(['driver_id', 'trip_type']);
                $table->foreign('driver_id')->references('id')->on('drivers')->cascadeOnDelete();
                $table->unique(['driver_id', 'shift_period']);
            });
        }

        if (Schema::hasColumn('transport_routes', 'trip_type')) {
            Schema::table('transport_routes', function (Blueprint $table): void {
                $table->dropColumn(['trip_type', 'start_address', 'start_latitude', 'start_longitude']);
            });
        }
    }

    private function hasUniqueIndex(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = Schema::getConnection()->select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($row): bool => ($row->name ?? '') === $indexName);
        }

        $indexes = Schema::getConnection()->select(
            'SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?',
            [$indexName],
        );

        return $indexes !== [];
    }
};
