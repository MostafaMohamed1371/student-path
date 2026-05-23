<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->foreignId('participant_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('post_id')->nullable()->after('participant_id');
        });

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->string('message_type', 32)->default('text')->after('user_id');
            $table->json('meta')->nullable()->after('body');
            $table->timestamp('read_at')->nullable()->after('meta');
            $table->text('body')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropColumn(['message_type', 'meta', 'read_at']);
            $table->text('body')->nullable(false)->change();
        });

        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('participant_id');
            $table->dropColumn('post_id');
        });
    }
};
