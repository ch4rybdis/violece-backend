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
        Schema::create('event_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('weekly_events')->onDelete('cascade');
            $table->foreignId('user1_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user2_id')->constrained('users')->onDelete('cascade');
            $table->float('compatibility_score');
            $table->json('match_reasons')->nullable();
            $table->boolean('is_notified')->default(false);
            $table->timestamp('notified_at')->nullable();
            $table->boolean('user1_accepted')->default(false);
            $table->boolean('user2_accepted')->default(false);
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            // Ensure unique pairs within an event
            $table->unique(['event_id', 'user1_id', 'user2_id']);

            // Indexes for queries
            $table->index(['event_id', 'compatibility_score']);
            $table->index(['user1_id', 'created_at']);
            $table->index(['user2_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_matches');
    }
};
