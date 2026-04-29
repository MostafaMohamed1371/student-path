<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('bus_number', 64)->nullable();
            $table->string('route_title')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('students_count')->default(0);
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->dateTime('start_time')->index();
            $table->dateTime('end_time')->nullable();
            $table->string('status', 32)->default('PRESENT');
            $table->text('note')->nullable();
            $table->json('students_preview')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_histories');
    }
};

