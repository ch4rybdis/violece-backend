<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('user_matches', function (Blueprint $table) {
            $table->foreignId('user1_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user2_id')->constrained('users')->onDelete('cascade');
            $table->float('compatibility_score')->default(0);
            $table->timestamp('matched_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('match_context')->nullable();
            $table->string('conversation_starter')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->float('match_quality_score')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('user_matches', function (Blueprint $table) {
            $table->dropColumn([
                'user1_id',
                'user2_id',
                'compatibility_score',
                'matched_at',
                'is_active',
                'match_context',
                'conversation_starter',
                'last_activity_at',
                'match_quality_score'
            ]);
        });
    }

};
