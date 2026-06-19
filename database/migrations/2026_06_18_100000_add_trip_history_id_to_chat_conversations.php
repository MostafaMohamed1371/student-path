<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string TRIP_HISTORY_INDEX = 'chat_conv_type_trip_hist_status_idx';

    public function up(): void
    {
        if (! Schema::hasColumn('chat_conversations', 'trip_history_id')) {
            Schema::table('chat_conversations', function (Blueprint $table): void {
                $table->foreignId('trip_history_id')->nullable()->after('trip_request_id')->constrained('trip_histories')->nullOnDelete();
            });
        }

        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->index(['conversation_type', 'trip_history_id', 'status'], self::TRIP_HISTORY_INDEX);
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropIndex(self::TRIP_HISTORY_INDEX);
            $table->dropConstrainedForeignId('trip_history_id');
        });
    }
};
