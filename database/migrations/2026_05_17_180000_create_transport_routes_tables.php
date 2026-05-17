<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->string('name');
            $table->string('shift_period', 16)->nullable();
            $table->string('trip_type', 32)->nullable();
            $table->string('start_address')->nullable();
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->unique(['driver_id', 'trip_type']);
            $table->index(['school_id', 'shift_period', 'status']);
        });

        Schema::create('transport_route_students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transport_route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('distance_from_school_km', 8, 2)->nullable();
            $table->timestamps();

            $table->unique('student_id');
            $table->index(['transport_route_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_route_students');
        Schema::dropIfExists('transport_routes');
    }
};
