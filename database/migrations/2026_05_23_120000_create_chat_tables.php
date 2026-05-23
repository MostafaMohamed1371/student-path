<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('open');
            $table->string('subject')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('user_last_read_at')->nullable();
            $table->timestamp('staff_last_read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('last_message_at');
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['chat_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
