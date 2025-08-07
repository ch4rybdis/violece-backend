<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add current_chat_id to track which chat user is currently viewing
            // This prevents push notifications when user is actively in that chat
            $table->bigInteger('current_chat_id')->nullable()->index()->after('response_time_avg');

            // Add notification_preferences as JSON to store user's notification settings
            $table->json('notification_preferences')->default('{}')->after('current_chat_id');

            // Add FCM token for Firebase Cloud Messaging (push notifications)
            $table->string('fcm_token')->nullable()->after('notification_preferences');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['current_chat_id']); // Drop index first
            $table->dropColumn([
                'current_chat_id',
                'notification_preferences',
                'fcm_token'
            ]);
        });
    }
};
