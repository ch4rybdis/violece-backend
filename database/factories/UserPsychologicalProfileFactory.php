<?php

// database/factories/UserPsychologicalProfileFactory.php

namespace Database\Factories\Psychology;

use App\Models\Psychology\UserPsychologicalProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserPsychologicalProfileFactory extends Factory
{
    protected $model = UserPsychologicalProfile::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'openness_score' => $this->faker->numberBetween(10, 90),
            'conscientiousness_score' => $this->faker->numberBetween(10, 90),
            'extraversion_score' => $this->faker->numberBetween(10, 90),
            'agreeableness_score' => $this->faker->numberBetween(10, 90),
            'neuroticism_score' => $this->faker->numberBetween(10, 90),
            'primary_attachment_style' => $this->faker->randomElement(['secure', 'anxious', 'avoidant', 'mixed']),
            'secure_attachment_score' => $this->faker->numberBetween(20, 80),
            'anxious_attachment_score' => $this->faker->numberBetween(20, 80),
            'avoidant_attachment_score' => $this->faker->numberBetween(20, 80),
            'compatibility_keywords' => [
                $this->faker->randomElement(['creative', 'reliable', 'social', 'compassionate', 'stable']),
                $this->faker->randomElement(['adventurous', 'organized', 'empathetic', 'independent'])
            ],
            'profile_strength' => $this->faker->randomFloat(2, 0.6, 1.0),
            'raw_response_data' => json_encode([]),
            'algorithm_version' => '1.0.0',
            'is_active' => true,
        ];
    }

    public function secure()
    {
        return $this->state(function (array $attributes) {
            return [
                'primary_attachment_style' => 'secure',
                'secure_attachment_score' => $this->faker->numberBetween(70, 95),
                'anxious_attachment_score' => $this->faker->numberBetween(10, 40),
                'avoidant_attachment_score' => $this->faker->numberBetween(10, 40),
            ];
        });
    }

    public function anxious()
    {
        return $this->state(function (array $attributes) {
            return [
                'primary_attachment_style' => 'anxious',
                'secure_attachment_score' => $this->faker->numberBetween(10, 40),
                'anxious_attachment_score' => $this->faker->numberBetween(70, 95),
                'avoidant_attachment_score' => $this->faker->numberBetween(10, 40),
            ];
        });
    }

    public function avoidant()
    {
        return $this->state(function (array $attributes) {
            return [
                'primary_attachment_style' => 'avoidant',
                'secure_attachment_score' => $this->faker->numberBetween(10, 40),
                'anxious_attachment_score' => $this->faker->numberBetween(10, 40),
                'avoidant_attachment_score' => $this->faker->numberBetween(70, 95),
            ];
        });
    }

    public function highCompatibility()
    {
        return $this->state(function (array $attributes) {
            return [
                'openness_score' => $this->faker->numberBetween(70, 85),
                'conscientiousness_score' => $this->faker->numberBetween(75, 90),
                'extraversion_score' => $this->faker->numberBetween(60, 80),
                'agreeableness_score' => $this->faker->numberBetween(80, 95),
                'neuroticism_score' => $this->faker->numberBetween(10, 30),
                'primary_attachment_style' => 'secure',
                'secure_attachment_score' => $this->faker->numberBetween(80, 95),
                'profile_strength' => $this->faker->randomFloat(2, 0.8, 1.0),
            ];
        });
    }
}
