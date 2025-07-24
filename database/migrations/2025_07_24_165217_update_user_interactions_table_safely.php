<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Only add columns that don't exist
     */
    public function up(): void
    {
        // Check each column individually and add only if missing

        try {
            // Check interaction_context column
            if (!Schema::hasColumn('user_interactions', 'interaction_context')) {
                Schema::table('user_interactions', function (Blueprint $table) {
                    $table->json('interaction_context')->nullable();
                });
                echo "Added interaction_context column\n";
            } else {
                echo "interaction_context column already exists\n";
            }
        } catch (\Exception $e) {
            echo "Error with interaction_context: " . $e->getMessage() . "\n";
        }

        try {
            // Check is_mutual column
            if (!Schema::hasColumn('user_interactions', 'is_mutual')) {
                Schema::table('user_interactions', function (Blueprint $table) {
                    $table->boolean('is_mutual')->default(false);
                });
                echo "Added is_mutual column\n";
            } else {
                echo "is_mutual column already exists\n";
            }
        } catch (\Exception $e) {
            echo "Error with is_mutual: " . $e->getMessage() . "\n";
        }

        try {
            // Check processed_at column
            if (!Schema::hasColumn('user_interactions', 'processed_at')) {
                Schema::table('user_interactions', function (Blueprint $table) {
                    $table->timestamp('processed_at')->nullable();
                });
                echo "Added processed_at column\n";
            } else {
                echo "processed_at column already exists\n";
            }
        } catch (\Exception $e) {
            echo "Error with processed_at: " . $e->getMessage() . "\n";
        }

        // Add indexes safely (ignore errors if they exist)
        try {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_user_interactions_mutual ON user_interactions(is_mutual)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_user_interactions_processed ON user_interactions(processed_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_user_interactions_type_mutual ON user_interactions(interaction_type, is_mutual)');
            echo "Added indexes successfully\n";
        } catch (\Exception $e) {
            echo "Index creation info: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't want to drop columns in production
        echo "Down migration skipped for safety\n";
    }
};
