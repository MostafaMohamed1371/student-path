<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('father_name');
            $table->string('grandfather_name');
            $table->string('last_name');
            $table->unsignedSmallInteger('age');
            $table->string('id_card_number');
            $table->string('license_number');
            $table->string('primary_phone', 20);
            $table->string('emergency_phone', 20);
            $table->string('residential_address');
            $table->string('status', 32)->default('active');
            $table->string('id_card_image')->nullable();
            $table->string('license_image')->nullable();
            $table->string('non_conviction_certificate')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
