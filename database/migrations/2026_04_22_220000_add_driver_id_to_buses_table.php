<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buses', function (Blueprint $table): void {
            $table->foreignId('driver_id')->nullable()->after('user_id')->unique()->constrained('drivers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('driver_id');
        });
    }
};
