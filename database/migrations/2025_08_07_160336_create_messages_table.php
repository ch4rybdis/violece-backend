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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('match_id')->constrained('user_matches')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->text('content')->nullable();
            $table->string('type')->default('text');
            $table->json('meta')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Indexes for performance
            $table->index(['match_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
            $table->index(['is_deleted', 'created_at']);
        });

        // Add columns to user_matches table to track read status
        Schema::table('user_matches', function (Blueprint $table) {
            $table->timestamp('user1_last_read_at')->nullable()->after('last_activity_at');
            $table->timestamp('user2_last_read_at')->nullable()->after('user1_last_read_at');
            $table->timestamp('last_message_at')->nullable()->after('user2_last_read_at');
            $table->boolean('has_interaction')->default(false)->after('last_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_matches', function (Blueprint $table) {
            $table->dropColumn(['user1_last_read_at', 'user2_last_read_at', 'last_message_at', 'has_interaction']);
        });

        Schema::dropIfExists('messages');
    }
};
