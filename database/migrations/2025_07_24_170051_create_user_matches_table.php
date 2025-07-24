<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration safely adds missing columns to existing user_matches table
     */
    public function up(): void
    {
        // Check if table exists, if not create it
        if (!Schema::hasTable('user_matches')) {
            Schema::create('user_matches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user1_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('user2_id')->constrained('users')->onDelete('cascade');
                $table->decimal('compatibility_score', 5, 2)->index();
                $table->decimal('match_quality_score', 5, 2)->nullable();
                $table->timestamp('matched_at')->index();
                $table->timestamp('last_activity_at')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->json('match_context')->nullable();
                $table->text('conversation_starter')->nullable();
                $table->timestamps();

                $table->unique(['user1_id', 'user2_id']);
                $table->index(['user1_id', 'is_active']);
                $table->index(['user2_id', 'is_active']);
                $table->index(['compatibility_score', 'is_active']);
                $table->index(['matched_at', 'is_active']);
                $table->index(['last_activity_at']);
            });
        } else {
            // Table exists, add missing columns safely
            Schema::table('user_matches', function (Blueprint $table) {
                // Add columns that might be missing
                if (!Schema::hasColumn('user_matches', 'match_quality_score')) {
                    $table->decimal('match_quality_score', 5, 2)->nullable()->after('compatibility_score');
                }

                if (!Schema::hasColumn('user_matches', 'last_activity_at')) {
                    $table->timestamp('last_activity_at')->nullable()->index()->after('matched_at');
                }

                if (!Schema::hasColumn('user_matches', 'match_context')) {
                    $table->json('match_context')->nullable()->after('is_active');
                }

                if (!Schema::hasColumn('user_matches', 'conversation_starter')) {
                    $table->text('conversation_starter')->nullable()->after('match_context');
                }
            });

            // Add missing indexes safely
            try {
                Schema::table('user_matches', function (Blueprint $table) {
                    $table->index(['compatibility_score', 'is_active'], 'idx_compatibility_active');
                    $table->index(['matched_at', 'is_active'], 'idx_matched_active');
                    $table->index(['last_activity_at'], 'idx_last_activity');
                });
            } catch (\Exception $e) {
                // Indexes might already exist, ignore error
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only drop columns we added, don't drop the whole table
        if (Schema::hasTable('user_matches')) {
            Schema::table('user_matches', function (Blueprint $table) {
                if (Schema::hasColumn('user_matches', 'match_quality_score')) {
                    $table->dropColumn('match_quality_score');
                }
                if (Schema::hasColumn('user_matches', 'last_activity_at')) {
                    $table->dropColumn('last_activity_at');
                }
                if (Schema::hasColumn('user_matches', 'match_context')) {
                    $table->dropColumn('match_context');
                }
                if (Schema::hasColumn('user_matches', 'conversation_starter')) {
                    $table->dropColumn('conversation_starter');
                }
            });
        }
    }
};
