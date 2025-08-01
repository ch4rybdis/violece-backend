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
        Schema::create('event_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('weekly_events')->onDelete('cascade');
            $table->string('question_type')->default('multiple_choice');
            $table->string('question_text');
            $table->json('options')->nullable();
            $table->json('psychological_weights')->nullable();
            $table->integer('display_order');
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index(['event_id', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_questions');
    }
};
