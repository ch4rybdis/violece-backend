<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('version', 10)->default('1.0');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('total_questions')->default(0);
            $table->integer('estimated_duration_minutes')->default(5);
            $table->timestamps();

            $table->index(['is_active', 'version']);
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_sets');
    }
};
