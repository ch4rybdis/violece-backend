<?php

// tests/Feature/Matching/CompatibilityTest.php

namespace Tests\Feature\Matching;

use Tests\TestCase;
use App\Models\User;
use App\Models\Psychology\UserPsychologicalProfile;
use App\Services\Matching\CompatibilityScoringService;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;
    protected CompatibilityScoringService $compatibilityService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
        $this->compatibilityService = app(CompatibilityScoringService::class);

        Sanctum::actingAs($this->user1);
    }

    /** @test */
    public function it_can_get_potential_matches()
    {
        // Create psychological profiles
        UserPsychologicalProfile::factory()->create([
            'user_id' => $this->user1->id,
            'is_active' => true
        ]);

        UserPsychologicalProfile::factory()->create([
            'user_id' => $this->user2->id,
            'is_active' => true
        ]);

        $response = $this->getJson('/api/matching/potential-matches');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'matches' => [
                        '*' => [
                            'user' => [
                                'id',
                                'name',
                                'age',
                                'photos',
                                'bio',
                                'location'
                            ],
                            'compatibility' => [
                                'total_score',
                                'component_scores',
                                'detailed_analysis'
                            ],
                            'match_reasons'
                        ]
                    ],
                    'total_found',
                    'algorithm_version'
                ]
            ]);
    }

    /** @test */
    public function it_calculates_compatibility_scores_correctly()
    {
        // Create profiles with similar traits (should score high)
        $profile1 = UserPsychologicalProfile::factory()->create([
            'user_id' => $this->user1->id,
            'openness_score' => 75,
            'conscientiousness_score' => 80,
            'extraversion_score' => 60,
            'agreeableness_score' => 85,
            'neuroticism_score' => 30,
            'primary_attachment_style' => 'secure',
            'is_active' => true
        ]);

        $profile2 = UserPsychologicalProfile::factory()->create([
            'user_id' => $this->user2->id,
            'openness_score' => 78,
            'conscientiousness_score' => 75,
            'extraversion_score' => 65,
            'agreeableness_score' => 82,
            'neuroticism_score' => 25,
            'primary_attachment_style' => 'secure',
            'is_active' => true
        ]);

        $compatibility = $this->compatibilityService->calculateCompatibilityScore($this->user1, $this->user2);

        $this->assertIsArray($compatibility);
        $this->assertArrayHasKey('total_score', $compatibility);
        $this->assertArrayHasKey('component_scores', $compatibility);
        $this->assertArrayHasKey('detailed_analysis', $compatibility);

        // High compatibility expected due to similar traits and secure attachment
        $this->assertGreaterThan(70, $compatibility['total_score']);
        $this->assertLessThanOrEqual(99, $compatibility['total_score']);
    }

    /** @test */
    public function it_penalizes_problematic_pairings()
    {
        // Create anxious-avoidant pairing (should score lower)
        UserPsychologicalProfile::factory()->create([
            'user_id' => $this->user1->id,
            'primary_attachment_style' => 'anxious',
            'anxious_attachment_score' => 85,
            'neuroticism_score' => 75,
            'is_active' => true
        ]);

        UserPsychologicalProfile::factory()->create([
            'user_id' => $this->user2->id,
            'primary_attachment_style' => 'avoidant',
            'avoidant_attachment_score' => 80,
            'neuroticism_score' => 70,
            'is_active' => true
        ]);

        $compatibility = $this->compatibilityService->calculateCompatibilityScore($this->user1, $this->user2);

        // Should score lower due to anxious-avoidant pairing and high neuroticism
        $this->assertLessThan(60, $compatibility['total_score']);
        $this->assertGreaterThan(0, $compatibility['total_score']);
    }

    /** @test */
    public function it_can_get_detailed_compatibility_analysis()
    {
        UserPsychologicalProfile::factory()->create([
            'user_id' => $this->user1->id,
            'is_active' => true
        ]);

        UserPsychologicalProfile::factory()->create([
            'user_id' => $this->user2->id,
            'is_active' => true
        ]);

        $response = $this->getJson("/api/matching/compatibility/{$this->user2->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'users' => [
                        'current_user',
                        'target_user'
                    ],
                    'compatibility_analysis' => [
                        'total_score',
                        'component_scores' => [
                            'personality_similarity',
                            'attachment_compatibility',
                            'behavioral_patterns',
                            'values_alignment',
                            'complementarity_bonus'
                        ],
                        'detailed_analysis' => [
                            'strongest_connections',
                            'potential_challenges',
                            'relationship_style_prediction'
                        ]
                    ],
                    'recommendations'
                ]
            ]);
    }

    /** @test */
    public function it_requires_authentication_for_matching_endpoints()
    {
        // Remove authentication
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/matching/potential-matches');
        $response->assertStatus(401);

        $response = $this->getJson("/api/matching/compatibility/{$this->user2->id}");
        $response->assertStatus(401);
    }
}
