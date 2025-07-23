<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('email')->unique();
            $table->string('phone', 20)->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Profile Information
            $table->string('first_name', 100);
            $table->date('birth_date');
            $table->tinyInteger('gender'); // 1: male, 2: female, 3: non-binary
            $table->json('preference_gender')->default('[1,2,3]');

            // Geographic Location (PostGIS)
            $table->geometry('location', 'POINT', 4326)->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->integer('max_distance')->default(50000); // meters

            // Violece Psychological Profile
            $table->jsonb('psychological_traits')->default('{}');
            $table->jsonb('questionnaire_responses')->default('{}');
            $table->json('compatibility_vector')->default('[]');

            // Profile Content
            $table->json('profile_photos')->default('[]');
            $table->text('bio')->nullable();
            $table->json('interests')->default('[]');
            $table->float('profile_completion_score', 3, 2)->default(0);

            // Activity Metrics
            $table->timestamp('last_active_at')->nullable();
            $table->integer('total_swipes')->default(0);
            $table->integer('total_matches')->default(0);
            $table->integer('response_time_avg')->default(0); // seconds

            // Premium Features
            $table->boolean('is_premium')->default(false);
            $table->timestamp('premium_expires_at')->nullable();

            // Account Status
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('deletion_requested_at')->nullable();

            $table->rememberToken();
            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'last_active_at']);
            $table->index(['birth_date', 'gender']);
            $table->index(['is_premium', 'premium_expires_at']);
        });

        // PostGIS spatial index
        DB::statement('CREATE INDEX users_location_gist_idx ON users USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
