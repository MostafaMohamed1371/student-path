<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('preferences');
            $table->timestamps();
        });

        Schema::create('user_fcm_topic_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 191);
            $table->foreignId('trip_history_id')->nullable()->constrained('trip_histories')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'topic']);
            $table->index('trip_history_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_fcm_topic_subscriptions');
        Schema::dropIfExists('user_notification_preferences');
    }
};
