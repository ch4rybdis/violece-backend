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
        // Check if event_participations table exists and add any missing columns
        if (Schema::hasTable('event_participations')) {
            // Only add columns that might be missing, skip constraints and indexes
            if (!Schema::hasColumn('event_participations', 'status')) {
                Schema::table('event_participations', function (Blueprint $table) {
                    $table->string('status')->default('joined')->after('user_id');
                });
            }

            if (!Schema::hasColumn('event_participations', 'response_data')) {
                Schema::table('event_participations', function (Blueprint $table) {
                    $table->json('response_data')->nullable()->after(Schema::hasColumn('event_participations', 'status') ? 'status' : 'user_id');
                });
            }

            if (!Schema::hasColumn('event_participations', 'completed_at')) {
                Schema::table('event_participations', function (Blueprint $table) {
                    $table->timestamp('completed_at')->nullable()->after(Schema::hasColumn('event_participations', 'response_data') ? 'response_data' : (Schema::hasColumn('event_participations', 'status') ? 'status' : 'user_id'));
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need for down method
    }
};
