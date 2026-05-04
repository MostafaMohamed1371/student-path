<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_complaints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category_id', 64);
            $table->text('details');
            $table->json('attachments')->nullable();
            $table->string('complaint_number', 32)->nullable()->unique();
            $table->string('status', 32)->default('RECEIVED');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_complaints');
    }
};
