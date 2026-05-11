<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_history_id')->constrained('trip_histories')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emergency_type', 24)->default('SOS');
            $table->string('status', 24)->default('TRIGGERED');
            $table->decimal('driver_lat', 10, 7);
            $table->decimal('driver_lng', 10, 7);
            $table->timestamp('triggered_at');
            $table->timestamp('stopped_at')->nullable();
            $table->text('stop_reason')->nullable();
            $table->decimal('final_lat', 10, 7)->nullable();
            $table->decimal('final_lng', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['trip_history_id', 'status']);
            $table->index(['driver_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_alerts');
    }
};
