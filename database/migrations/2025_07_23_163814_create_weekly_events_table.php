<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('event_type', 50); // personality_quiz, scenario_challenge, values_alignment
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->jsonb('event_data')->default('{}');

            // Event Scheduling
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->integer('max_participants')->default(1000);

            // Event Status
            $table->enum('status', ['scheduled', 'active', 'completed', 'cancelled'])->default('scheduled');

            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index(['event_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_events');
    }
};
