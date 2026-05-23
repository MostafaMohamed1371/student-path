<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversation_user_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('pinned_at')->nullable();
            $table->boolean('is_muted')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'chat_conversation_id']);
        });

        Schema::create('user_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['blocker_id', 'blocked_id']);
            $table->index(['blocked_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('chat_conversation_user_settings');
    }
};
