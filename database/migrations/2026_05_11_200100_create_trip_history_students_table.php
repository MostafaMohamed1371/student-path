<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_history_students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_history_id')->constrained('trip_histories')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 32)->default('IDLE');
            $table->timestamp('boarding_time')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamps();

            $table->unique(['trip_history_id', 'student_id']);
            $table->index(['trip_history_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_history_students');
    }
};
