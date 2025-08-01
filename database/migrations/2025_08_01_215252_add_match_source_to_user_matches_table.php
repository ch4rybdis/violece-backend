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
        Schema::table('user_matches', function (Blueprint $table) {
            // Add columns for match source tracking if they don't exist
            if (!Schema::hasColumn('user_matches', 'match_source')) {
                $table->string('match_source')->default('swipe')->after('compatibility_score');
            }

            if (!Schema::hasColumn('user_matches', 'match_source_id')) {
                $table->unsignedBigInteger('match_source_id')->nullable()->after('match_source');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_matches', function (Blueprint $table) {
            $table->dropColumn(['match_source', 'match_source_id']);
        });
    }
};
