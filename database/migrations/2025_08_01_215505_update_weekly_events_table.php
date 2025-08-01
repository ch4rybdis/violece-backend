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
        // Check if weekly_events table exists and add any missing columns
        if (Schema::hasTable('weekly_events')) {
            Schema::table('weekly_events', function (Blueprint $table) {
                if (!Schema::hasColumn('weekly_events', 'event_type')) {
                    $table->string('event_type')->default('personality_quiz')->after('id');
                }

                if (!Schema::hasColumn('weekly_events', 'title')) {
                    $table->string('title')->default('Weekly Event')->after('event_type');
                }

                if (!Schema::hasColumn('weekly_events', 'description')) {
                    $table->text('description')->nullable()->after('title');
                }

                if (!Schema::hasColumn('weekly_events', 'event_data')) {
                    $table->json('event_data')->nullable()->after('description');
                }

                if (!Schema::hasColumn('weekly_events', 'starts_at')) {
                    $table->timestamp('starts_at')->nullable()->after('event_data');
                }

                if (!Schema::hasColumn('weekly_events', 'ends_at')) {
                    $table->timestamp('ends_at')->nullable()->after('starts_at');
                }

                if (!Schema::hasColumn('weekly_events', 'max_participants')) {
                    $table->integer('max_participants')->default(1000)->after('ends_at');
                }

                if (!Schema::hasColumn('weekly_events', 'status')) {
                    $table->string('status')->default('scheduled')->after('max_participants');
                }

                // Add index if it doesn't exist
                if (!Schema::hasIndex('weekly_events', ['status', 'starts_at', 'ends_at'])) {
                    $table->index(['status', 'starts_at', 'ends_at']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Since this is just adding columns, we don't need to do anything in the down method
    }
};
