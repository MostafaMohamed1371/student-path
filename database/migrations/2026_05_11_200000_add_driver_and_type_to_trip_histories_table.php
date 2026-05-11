<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_histories', function (Blueprint $table): void {
            $table->foreignId('driver_id')->nullable()->after('school_id')->constrained('drivers')->nullOnDelete();
            $table->string('trip_type', 32)->nullable()->after('route_title');
        });
    }

    public function down(): void
    {
        Schema::table('trip_histories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('driver_id');
            $table->dropColumn('trip_type');
        });
    }
};
