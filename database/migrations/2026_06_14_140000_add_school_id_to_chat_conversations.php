<?php

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Chat\ChatSchoolSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->foreignId('school_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index('school_id');
        });

        $support = app(ChatSchoolSupport::class);

        ChatConversation::query()
            ->with('user')
            ->whereNull('school_id')
            ->orderBy('id')
            ->chunkById(100, function ($conversations) use ($support): void {
                foreach ($conversations as $conversation) {
                    $user = $conversation->user;
                    if ($user === null) {
                        continue;
                    }

                    $schoolId = $support->schoolIdForAppUser($user);
                    if ($schoolId === null) {
                        continue;
                    }

                    $updates = ['school_id' => $schoolId];

                    if ($conversation->participant_id === null) {
                        $staffId = $support->defaultStaffUserForSchool($schoolId)?->id;
                        if ($staffId !== null) {
                            $updates['participant_id'] = $staffId;
                        }
                    }

                    $conversation->update($updates);
                }
            });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('school_id');
        });
    }
};
