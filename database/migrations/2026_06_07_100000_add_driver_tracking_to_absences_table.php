<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absences', function (Blueprint $table): void {
            $table->foreignId('driver_id')->nullable()->after('student_id')->constrained('drivers')->nullOnDelete();
            $table->foreignId('transport_route_id')->nullable()->after('driver_id')->constrained('transport_routes')->nullOnDelete();
            $table->timestamp('driver_notified_at')->nullable()->after('notes');
            $table->timestamp('school_notified_at')->nullable()->after('driver_notified_at');

            $table->index(['driver_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('transport_route_id');
            $table->dropConstrainedForeignId('driver_id');
            $table->dropColumn(['driver_notified_at', 'school_notified_at']);
        });
    }
};
