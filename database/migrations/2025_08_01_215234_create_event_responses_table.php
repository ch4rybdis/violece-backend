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
        Schema::create('event_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participation_id')->constrained('event_participations')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('event_questions')->onDelete('cascade');
            $table->string('response_value');
            $table->json('response_metadata')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->timestamps();

            // Unique to prevent duplicate responses to the same question
            $table->unique(['participation_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_responses');
    }
};
