<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->string('conversation_type', 32)->default('support')->after('user_id');
            $table->foreignId('trip_request_id')->nullable()->after('participant_id')->constrained('trip_requests')->nullOnDelete();
            $table->timestamp('participant_last_read_at')->nullable()->after('staff_last_read_at');

            $table->index(['conversation_type', 'user_id', 'status']);
            $table->index(['conversation_type', 'participant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropIndex(['conversation_type', 'user_id', 'status']);
            $table->dropIndex(['conversation_type', 'participant_id', 'status']);
            $table->dropConstrainedForeignId('trip_request_id');
            $table->dropColumn(['conversation_type', 'participant_last_read_at']);
        });
    }
};
