<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_qicard_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('IQD');
            $table->uuid('request_id')->unique();
            $table->string('payment_id')->nullable()->unique();
            $table->string('status', 32)->default('pending');
            $table->text('form_url')->nullable();
            $table->json('gateway_create_response')->nullable();
            $table->json('gateway_status_response')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_qicard_payments');
    }
};
