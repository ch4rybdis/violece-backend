<?php

namespace Tests\Feature\Matching;

use Tests\TestCase;
use App\Models\User;
use App\Models\Dating\UserMatch;
use App\Models\Dating\UserInteraction;
use App\Models\Psychology\UserPsychologicalProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class SwipeActionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;
    protected User $user3;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users with psychology profiles
        $this->user1 = User::factory()->create([
            'subscription_type' => 'free'
        ]);

        $this->user2 = User::factory()->create([
            'subscription_type' => 'free'
        ]);

        $this->user3 = User::factory()->create([
            'subscription_type' => 'premium',
            'subscription_expires_at' => now()->addMonth()
        ]);

        // Create psychological profiles for all users
        UserPsychologicalProfile::factory()->create(['user_id' => $this->user1->id]);
        UserPsychologicalProfile::factory()->create(['user_id' => $this->user2->id]);
        UserPsychologicalProfile::factory()->create(['user_id' => $this->user3->id]);
    }

    /** @test */
    public function it_can_like_a_user_successfully()
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson("/api/matching/like/{$this->user2->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'liked',
                    'matched',
                    'remaining_likes'
                ]
            ]);

        $this->assertDatabaseHas('user_interactions', [
            'user_id' => $this->user1->id,
            'target_user_id' => $this->user2->id,
            'interaction_type' => UserInteraction::TYPE_LIKE
        ]);
    }

    /** @test */
    public function it_creates_match_on_mutual_like()
    {
        Sanctum::actingAs($this->user1);

        // User2 likes User1 first
        UserInteraction::create([
            'user_id' => $this->user2->id,
            'target_user_id' => $this->user1->id,
            'interaction_type' => UserInteraction::TYPE_LIKE
        ]);

        // User1 likes User2 back
        $response = $this->postJson("/api/matching/like/{$this->user2->id}");

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'liked' => true,
                    'matched' => true
                ]
            ]);

        $this->assertDatabaseHas('user_matches', [
            'user1_id' => min($this->user1->id, $this->user2->id),
            'user2_id' => max($this->user1->id, $this->user2->id),
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_can_pass_on_a_user()
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson("/api/matching/pass/{$this->user2->id}");

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => ['passed' => true]
            ]);

        $this->assertDatabaseHas('user_interactions', [
            'user_id' => $this->user1->id,
            'target_user_id' => $this->user2->id,
            'interaction_type' => UserInteraction::TYPE_PASS
        ]);
    }

    /** @test */
    public function it_requires_premium_for_super_likes()
    {
        Sanctum::actingAs($this->user1); // Free user

        $response = $this->postJson("/api/matching/super-like/{$this->user2->id}");

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'PREMIUM_REQUIRED'
            ]);
    }

    /** @test */
    public function premium_user_can_super_like()
    {
        Sanctum::actingAs($this->user3); // Premium user

        $response = $this->postJson("/api/matching/super-like/{$this->user1->id}");

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => ['super_liked' => true]
            ]);

        $this->assertDatabaseHas('user_interactions', [
            'user_id' => $this->user3->id,
            'target_user_id' => $this->user1->id,
            'interaction_type' => UserInteraction::TYPE_SUPER_LIKE
        ]);
    }

    /** @test */
    public function it_enforces_daily_like_limits_for_free_users()
    {
        Sanctum::actingAs($this->user1); // Free user

        // Create 20 like interactions (free limit)
        for ($i = 0; $i < 20; $i++) {
            $targetUser = User::factory()->create();
            UserInteraction::create([
                'user_id' => $this->user1->id,
                'target_user_id' => $targetUser->id,
                'interaction_type' => UserInteraction::TYPE_LIKE,
                'created_at' => now()
            ]);
        }

        $response = $this->postJson("/api/matching/like/{$this->user2->id}");

        $response->assertStatus(429)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'DAILY_LIMIT_EXCEEDED'
            ]);
    }

    /** @test */
    public function it_prevents_duplicate_interactions()
    {
        Sanctum::actingAs($this->user1);

        // First interaction
        $this->postJson("/api/matching/like/{$this->user2->id}")
            ->assertOk();

        // Duplicate interaction
        $response = $this->postJson("/api/matching/like/{$this->user2->id}");

        $response->assertStatus(409)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'INTERACTION_EXISTS'
            ]);
    }

    /** @test */
    public function it_cannot_interact_with_self()
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson("/api/matching/like/{$this->user1->id}");

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'INVALID_TARGET_USER'
            ]);
    }

    /** @test */
    public function it_can_fetch_user_matches()
    {
        Sanctum::actingAs($this->user1);

        // Create a match
        UserMatch::create([
            'user1_id' => min($this->user1->id, $this->user2->id),
            'user2_id' => max($this->user1->id, $this->user2->id),
            'compatibility_score' => 75.5,
            'matched_at' => now(),
            'last_activity_at' => now()
        ]);

        $response = $this->getJson('/api/matching/matches');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'matches' => [
                        '*' => [
                            'match_id',
                            'user',
                            'compatibility_score',
                            'matched_at'
                        ]
                    ],
                    'pagination',
                    'stats'
                ]
            ]);
    }

    /** @test */
    public function it_can_fetch_user_stats()
    {
        Sanctum::actingAs($this->user1);

        // Create some interactions
        UserInteraction::create([
            'user_id' => $this->user1->id,
            'target_user_id' => $this->user2->id,
            'interaction_type' => UserInteraction::TYPE_LIKE
        ]);

        $response = $this->getJson('/api/matching/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'interaction_stats',
                    'daily_limits',
                    'match_stats'
                ]
            ]);
    }

    /** @test */
    public function it_calculates_interaction_statistics_correctly()
    {
        // Create various interactions
        UserInteraction::create([
            'user_id' => $this->user1->id,
            'target_user_id' => $this->user2->id,
            'interaction_type' => UserInteraction::TYPE_LIKE,
            'is_mutual' => true
        ]);

        UserInteraction::create([
            'user_id' => $this->user1->id,
            'target_user_id' => $this->user3->id,
            'interaction_type' => UserInteraction::TYPE_PASS
        ]);

        $stats = UserInteraction::getUserStats($this->user1->id);

        $this->assertEquals(2, $stats['total_interactions']);
        $this->assertEquals(1, $stats['likes_given']);
        $this->assertEquals(1, $stats['passes_given']);
        $this->assertEquals(1, $stats['mutual_likes']);
        $this->assertEquals(100, $stats['like_success_rate']);
    }

    /** @test */
    public function it_requires_authentication_for_all_endpoints()
    {
        $endpoints = [
            ['POST', "/api/matching/like/{$this->user2->id}"],
            ['POST', "/api/matching/pass/{$this->user2->id}"],
            ['POST', "/api/matching/super-like/{$this->user2->id}"],
            ['GET', '/api/matching/matches'],
            ['GET', '/api/matching/stats']
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            $response->assertStatus(401);
        }
    }
}
