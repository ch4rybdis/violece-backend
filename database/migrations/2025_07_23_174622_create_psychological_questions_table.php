<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psychological_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_set_id')->constrained()->onDelete('cascade');
            $table->integer('order_sequence');
            $table->string('content_key', 100); // Mobile app'teki key
            $table->string('category', 50); // lifestyle, social, decision_making, etc.
            $table->string('title', 200);
            $table->text('scenario_text');
            $table->string('video_filename')->nullable(); // Mobile bundle'daki video
            $table->string('image_filename')->nullable(); // Fallback image
            $table->jsonb('psychological_weights')->default('{}'); // Trait scoring weights
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index(['question_set_id', 'order_sequence']);
            $table->index(['category', 'is_required']);
            $table->unique(['question_set_id', 'content_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psychological_questions');
    }
};
