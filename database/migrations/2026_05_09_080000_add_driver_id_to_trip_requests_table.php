<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table): void {
            $table->foreignId('driver_id')
                ->nullable()
                ->after('student_id')
                ->constrained('drivers')
                ->nullOnDelete();

            $table->index(['driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table): void {
            $table->dropIndex(['driver_id', 'status']);
            $table->dropConstrainedForeignId('driver_id');
        });
    }
};
