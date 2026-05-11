<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delay_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_history_id')->constrained('trip_histories')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason_type', 32);
            $table->unsignedInteger('delay_duration_minutes');
            $table->text('note')->nullable();
            $table->decimal('driver_lat', 10, 7);
            $table->decimal('driver_lng', 10, 7);
            $table->timestamps();

            $table->index(['trip_history_id', 'created_at']);
            $table->index(['driver_id', 'created_at']);
            $table->index(['reason_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delay_alerts');
    }
};
