<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->foreignId('school_id')->nullable()->change();
            $table->string('first_name')->nullable()->change();
            $table->string('father_name')->nullable()->change();
            $table->string('grandfather_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
            $table->unsignedSmallInteger('age')->nullable()->change();
            $table->string('id_card_number')->nullable()->change();
            $table->string('license_number')->nullable()->change();
            $table->string('primary_phone', 20)->nullable()->change();
            $table->string('emergency_phone', 20)->nullable()->change();
            $table->string('residential_address')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->foreignId('school_id')->nullable(false)->change();
            $table->string('first_name')->nullable(false)->change();
            $table->string('father_name')->nullable(false)->change();
            $table->string('grandfather_name')->nullable(false)->change();
            $table->string('last_name')->nullable(false)->change();
            $table->unsignedSmallInteger('age')->nullable(false)->change();
            $table->string('id_card_number')->nullable(false)->change();
            $table->string('license_number')->nullable(false)->change();
            $table->string('primary_phone', 20)->nullable(false)->change();
            $table->string('emergency_phone', 20)->nullable(false)->change();
            $table->string('residential_address')->nullable(false)->change();
        });
    }
};
