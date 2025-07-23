<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('weekly_events')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->jsonb('responses')->default('{}');
            $table->float('completion_score', 3, 2)->default(0);
            $table->timestamps();

            // Prevent duplicate participation
            $table->unique(['event_id', 'user_id']);

            $table->index(['event_id', 'completion_score']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_participations');
    }
};
