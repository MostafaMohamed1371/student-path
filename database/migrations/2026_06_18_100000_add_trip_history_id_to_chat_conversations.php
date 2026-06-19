<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->foreignId('trip_history_id')->nullable()->after('trip_request_id')->constrained('trip_histories')->nullOnDelete();
            $table->index(['conversation_type', 'trip_history_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropIndex(['conversation_type', 'trip_history_id', 'status']);
            $table->dropConstrainedForeignId('trip_history_id');
        });
    }
};
