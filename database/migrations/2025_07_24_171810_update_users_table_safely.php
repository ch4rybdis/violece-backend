<?php

/**
 * Create new migration to add missing columns
 * Run: php artisan make:migration add_missing_columns_to_users_table
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add missing columns that might not exist
            if (!Schema::hasColumn('users', 'last_active_at')) {
                $table->timestamp('last_active_at')->nullable()->after('profile_completion_score');
            }

            if (!Schema::hasColumn('users', 'preference_age_min')) {
                $table->integer('preference_age_min')->default(18)->after('preference_gender');
            }

            if (!Schema::hasColumn('users', 'preference_age_max')) {
                $table->integer('preference_age_max')->default(99)->after('preference_age_min');
            }

            if (!Schema::hasColumn('users', 'max_distance')) {
                $table->integer('max_distance')->default(50)->after('location_updated_at');
            }
        });

        // Add indexes for better performance
        try {
            Schema::table('users', function (Blueprint $table) {
                if (!$this->indexExists('users', 'users_is_active_gender_index')) {
                    $table->index(['is_active', 'gender'], 'users_is_active_gender_index');
                }

                if (!$this->indexExists('users', 'users_preference_age_index')) {
                    $table->index(['preference_age_min', 'preference_age_max'], 'users_preference_age_index');
                }

                if (!$this->indexExists('users', 'users_last_active_at_index')) {
                    $table->index('last_active_at', 'users_last_active_at_index');
                }
            });
        } catch (\Exception $e) {
            // Indexes might already exist, ignore
        }

        // Ensure PostGIS location column exists and has proper index
        try {
            $hasLocationColumn = Schema::hasColumn('users', 'location');
            $isGeometry = false;

            if ($hasLocationColumn) {
                // Check if it's already a geometry column
                $result = DB::select("
                    SELECT data_type, udt_name
                    FROM information_schema.columns
                    WHERE table_name = 'users' AND column_name = 'location'
                ");
                $isGeometry = !empty($result) && $result[0]->udt_name === 'geometry';
            }

            if (!$hasLocationColumn) {
                // Add PostGIS geometry column
                DB::statement('ALTER TABLE users ADD COLUMN location GEOMETRY(POINT, 4326)');
            } elseif (!$isGeometry) {
                // Convert existing text column to geometry
                DB::statement('ALTER TABLE users ALTER COLUMN location TYPE GEOMETRY(POINT, 4326) USING ST_GeomFromText(location, 4326)');
            }

            // Add spatial index if it doesn't exist
            $indexExists = DB::select("
                SELECT 1 FROM pg_indexes
                WHERE tablename = 'users'
                AND indexname = 'users_location_gist_idx'
            ");

            if (empty($indexExists)) {
                DB::statement('CREATE INDEX users_location_gist_idx ON users USING GIST (location)');
            }

        } catch (\Exception $e) {
            \Log::warning('PostGIS setup failed: ' . $e->getMessage());

            // Fallback: ensure we have at least a text location column
            if (!Schema::hasColumn('users', 'location')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->text('location')->nullable();
                    $table->index('location');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'gender']);
            $table->dropIndex(['preference_age_min', 'preference_age_max']);
            $table->dropIndex(['last_active_at']);

            if (Schema::hasColumn('users', 'last_active_at')) {
                $table->dropColumn('last_active_at');
            }
            if (Schema::hasColumn('users', 'preference_age_min')) {
                $table->dropColumn('preference_age_min');
            }
            if (Schema::hasColumn('users', 'preference_age_max')) {
                $table->dropColumn('preference_age_max');
            }
            if (Schema::hasColumn('users', 'max_distance')) {
                $table->dropColumn('max_distance');
            }
        });
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $result = DB::select("
                SELECT 1 FROM pg_indexes
                WHERE tablename = ? AND indexname = ?
            ", [$table, $index]);

            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }
};
