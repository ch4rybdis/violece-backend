<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('question_set_id')->constrained()->onDelete('cascade');
            $table->foreignId('question_id')->constrained('psychological_questions')->onDelete('cascade');
            $table->foreignId('selected_option_id')->constrained('question_options')->onDelete('cascade');
            $table->integer('response_time_seconds')->nullable(); // Analytics için
            $table->timestamps();

            // Bir kullanıcı aynı soruya sadece bir kez cevap verebilir
            $table->unique(['user_id', 'question_id']);

            $table->index(['user_id', 'question_set_id']);
            $table->index(['question_id', 'selected_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_responses');
    }
};
