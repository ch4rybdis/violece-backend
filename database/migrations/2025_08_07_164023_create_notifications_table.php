<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if notifications table already exists
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->json('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // Add indexes safely (only if they don't exist)
        $this->addIndexSafely('notifications', ['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
        $this->addIndexSafely('notifications', ['read_at'], 'notifications_read_at_index');
        $this->addIndexSafely('notifications', ['created_at'], 'notifications_created_at_index');
        $this->addIndexSafely('notifications', ['type'], 'notifications_type_index');
    }

    /**
     * Add index safely - only if it doesn't exist
     */
    private function addIndexSafely(string $table, array $columns, string $indexName): void
    {
        try {
            // Check if index exists
            $indexExists = DB::select("
                SELECT 1
                FROM pg_indexes
                WHERE tablename = ? AND indexname = ?
            ", [$table, $indexName]);

            if (empty($indexExists)) {
                Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
                    $blueprint->index($columns, $indexName);
                });
            }
        } catch (\Exception $e) {
            // Index might already exist, ignore the error
            \Log::info("Index $indexName might already exist: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
