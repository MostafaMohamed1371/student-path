<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->timestamp('deleted_at')->nullable()->after('staff_last_read_at');
            $table->foreignId('deleted_by_user_id')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('chat_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->text('details')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index(['chat_conversation_id', 'created_at']);
            $table->index(['reporter_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_reports');

        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deleted_by_user_id');
            $table->dropColumn('deleted_at');
        });
    }
};
