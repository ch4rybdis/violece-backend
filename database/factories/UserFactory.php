<?php

/**
 * Update database/factories/UserFactory.php
 * Fix gender to use integers instead of strings
 */

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Gender constants (assuming 1=male, 2=female based on your User model)
        $genders = [1, 2]; // 1=male, 2=female
        $selectedGender = fake()->randomElement($genders);

        // Preference gender (opposite of selected gender)
        $preferenceGender = $selectedGender === 1 ? [2] : [1]; // If male(1), prefer female(2)

        return [
            'uuid' => Str::uuid()->toString(),
            'first_name' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->phoneNumber(),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'birth_date' => fake()->dateTimeBetween('-40 years', '-18 years')->format('Y-m-d'),
            'gender' => $selectedGender, // Use integer instead of string
            'preference_gender' => json_encode($preferenceGender), // Use integer array
            'preference_age_min' => 18,
            'preference_age_max' => 35,
            'max_distance' => fake()->numberBetween(10, 100),
            'bio' => fake()->sentence(10),
            'interests' => json_encode(fake()->randomElements([
                'travel', 'music', 'sports', 'reading', 'cooking',
                'movies', 'art', 'fitness', 'gaming', 'photography'
            ], 3)),
            'profile_photos' => json_encode([
                '/images/profiles/' . fake()->uuid() . '.jpg'
            ]),
            'profile_completion_score' => fake()->randomFloat(2, 60, 100),
            'last_active_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'is_premium' => fake()->boolean(20), // 20% chance of premium
            'premium_expires_at' => fake()->boolean(20) ? fake()->dateTimeBetween('now', '+1 year') : null,
            'is_verified' => fake()->boolean(80), // 80% verified
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'phone_verified_at' => null,
            'is_verified' => false,
        ]);
    }

    /**
     * Indicate that the user is premium.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_premium' => true,
            'premium_expires_at' => fake()->dateTimeBetween('now', '+1 year'),
        ]);
    }

    /**
     * Indicate that the user is male.
     */
    public function male(): static
    {
        return $this->state(fn (array $attributes) => [
            'gender' => 1, // 1 = male
            'preference_gender' => json_encode([2]), // prefer female
        ]);
    }

    /**
     * Indicate that the user is female.
     */
    public function female(): static
    {
        return $this->state(fn (array $attributes) => [
            'gender' => 2, // 2 = female
            'preference_gender' => json_encode([1]), // prefer male
        ]);
    }

    /**
     * Create user with complete profile for testing.
     */
    public function withCompleteProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'bio' => fake()->paragraph(3),
            'profile_completion_score' => 100.0,
            'is_verified' => true,
            'interests' => json_encode([
                'travel', 'music', 'sports', 'reading', 'cooking'
            ]),
            'profile_photos' => json_encode([
                '/images/profiles/' . fake()->uuid() . '.jpg',
                '/images/profiles/' . fake()->uuid() . '.jpg',
                '/images/profiles/' . fake()->uuid() . '.jpg'
            ]),
        ]);
    }
}
