<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('psychological_questions')->onDelete('cascade');
            $table->string('option_key', 50); // Mobile app'teki option key
            $table->integer('order_sequence');
            $table->string('text', 300);
            $table->string('visual_content')->nullable(); // Mobile'daki video/image key
            $table->jsonb('trait_impacts')->default('{}'); // Bu seçeneğin trait'lere etkisi
            $table->timestamps();

            $table->index(['question_id', 'order_sequence']);
            $table->unique(['question_id', 'option_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};
