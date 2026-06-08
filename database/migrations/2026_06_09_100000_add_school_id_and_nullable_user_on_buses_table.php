<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buses', function (Blueprint $table): void {
            $table->foreignId('school_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        if (Schema::hasColumn('buses', 'driver_id')) {
            $rows = DB::table('buses')
                ->join('drivers', 'drivers.id', '=', 'buses.driver_id')
                ->whereNull('buses.school_id')
                ->select('buses.id as bus_id', 'drivers.school_id as school_id')
                ->get();

            foreach ($rows as $row) {
                DB::table('buses')->where('id', $row->bus_id)->update([
                    'school_id' => $row->school_id,
                ]);
            }
        }

        Schema::table('buses', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
        });

        Schema::table('buses', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
        });

        Schema::table('buses', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique('user_id');
        });

        Schema::table('buses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('school_id');
        });
    }
};
