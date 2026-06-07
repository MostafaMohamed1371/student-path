<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_service_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('neighborhood_id')->nullable()->constrained('neighborhoods')->nullOnDelete();
            $table->unsignedBigInteger('monthly_subscription_price')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['driver_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_service_areas');
    }
};
