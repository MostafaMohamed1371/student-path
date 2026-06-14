<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_conversation_user_settings')) {
            return;
        }

        Schema::create('chat_conversation_user_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('pinned_at')->nullable();
            $table->boolean('is_muted')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'chat_conversation_id'], 'ccus_user_conversation_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversation_user_settings');
    }
};
