<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_driver_replacements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_trip_id')
                ->constrained('trip_histories')
                ->cascadeOnDelete();
            $table->date('service_date');
            $table->foreignId('replacement_driver_id')
                ->constrained('drivers')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['template_trip_id', 'service_date']);
            $table->index(['service_date', 'replacement_driver_id'], 'trip_drv_replacements_date_driver_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_driver_replacements');
    }
};
