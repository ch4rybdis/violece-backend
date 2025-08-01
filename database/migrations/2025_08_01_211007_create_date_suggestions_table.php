<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDateSuggestionsTable extends Migration
{
    public function up()
    {
        Schema::create('date_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('user_matches')->onDelete('cascade');
            $table->string('activity_type');
            $table->string('activity_name');
            $table->text('activity_description')->nullable();
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->decimal('venue_latitude', 10, 7)->nullable();
            $table->decimal('venue_longitude', 10, 7)->nullable();
            $table->integer('suggested_day'); // 0-6 for days of week
            $table->string('suggested_time');
            $table->text('compatibility_reason')->nullable();
            $table->boolean('is_accepted')->default(false);
            $table->boolean('is_rejected')->default(false);
            $table->timestamp('response_at')->nullable();
            $table->timestamps();

            // Index for quick lookups
            $table->index(['match_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('date_suggestions');
    }
}
