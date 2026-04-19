<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('code');
            $table->string('purpose', 32)->index();
            // dateTime avoids MySQL strict-mode issues with multiple nullable TIMESTAMP columns.
            $table->dateTime('expires_at')->index();
            $table->dateTime('resend_available_at');
            $table->dateTime('verified_at')->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestamps();

            $table->index(['phone', 'purpose', 'verified_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
