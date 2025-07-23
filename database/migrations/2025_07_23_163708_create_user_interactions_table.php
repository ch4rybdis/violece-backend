<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('target_user_id')->constrained('users')->onDelete('cascade');
            $table->tinyInteger('interaction_type'); // 1: pass, 2: like, 3: super_like
            $table->jsonb('interaction_context')->default('{}');
            $table->timestamps();

            // Prevent duplicate interactions
            $table->unique(['user_id', 'target_user_id']);

            // Performance indexes
            $table->index(['user_id', 'target_user_id']);
            $table->index(['target_user_id', 'interaction_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_interactions');
    }
};
