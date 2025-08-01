<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('match_id')->constrained()->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');

            // Message Content
            $table->text('message_text')->nullable();
            $table->tinyInteger('message_type')->default(1); // 1: text, 2: image, 3: gif, 4: voice
            $table->jsonb('message_metadata')->default('{}');

            // Delivery Tracking
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            // Message Status
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users');

            $table->timestamps();

            // Performance indexes
            $table->index(['match_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
            $table->index(['match_id', 'read_at'], 'messages_unread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
