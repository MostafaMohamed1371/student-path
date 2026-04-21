<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('city');
            $table->string('number')->unique();
            $table->string('color');
            $table->unsignedInteger('capacity');
            $table->string('fuel_type');
            $table->string('status');
            $table->boolean('annual_status')->default(true);
            $table->boolean('insurance')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buses');
    }
};
