<?php

// tests/Feature/Psychology/QuestionnaireTest.php

namespace Tests\Feature\Psychology;

use Tests\TestCase;
use App\Models\User;
use App\Models\Psychology\PsychologyQuestion;
use App\Models\Psychology\PsychologyQuestionOption;
use App\Models\Psychology\UserPsychologicalProfile;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class QuestionnaireTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->seedTestQuestions();
    }

    /** @test */
    public function it_can_get_questionnaire_questions()
    {
        $response = $this->getJson('/api/psychology/questionnaire/questions');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'questions' => [
                        '*' => [
                            'id',
                            'type',
                            'scenario' => [
                                'title',
                                'description',
                                'video_url',
                                'duration'
                            ],
                            'options' => [
                                '*' => [
                                    'id',
                                    'text',
                                    'video_url',
                                    'duration',
                                    'order'
                                ]
                            ]
                        ]
                    ],
                    'total_questions',
                    'estimated_duration'
                ]
            ]);

        $this->assertEquals('success', $response->json('status'));
        $this->assertGreaterThan(0, $response->json('data.total_questions'));
    }

    /** @test */
    public function it_can_submit_questionnaire_responses()
    {
        $question = PsychologyQuestion::with('options')->first();
        $option = $question->options->first();

        $responses = [
            [
                'question_id' => $question->id,
                'option_id' => $option->id,
                'response_time' => 5000
            ]
        ];

        $response = $this->postJson('/api/psychology/questionnaire/submit', [
            'responses' => $responses
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'profile_id',
                    'big_five_scores' => [
                        'openness',
                        'conscientiousness',
                        'extraversion',
                        'agreeableness',
                        'neuroticism'
                    ],
                    'attachment_style' => [
                        'primary',
                        'secure_score',
                        'anxious_score',
                        'avoidant_score'
                    ]
                ]
            ]);

        $this->assertEquals('success', $response->json('status'));

        // Verify profile was created
        $this->assertDatabaseHas('user_psychological_profiles', [
            'user_id' => $this->user->id,
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_validates_questionnaire_submission_data()
    {
        // Missing responses
        $response = $this->postJson('/api/psychology/questionnaire/submit', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['responses']);

        // Invalid question ID
        $response = $this->postJson('/api/psychology/questionnaire/submit', [
            'responses' => [
                [
                    'question_id' => 99999,
                    'option_id' => 1,
                    'response_time' => 5000
                ]
            ]
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['responses.0.question_id']);
    }

    /** @test */
    public function it_can_get_user_psychological_profile()
    {
        // Create a profile for the user
        UserPsychologicalProfile::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true
        ]);

        $response = $this->getJson('/api/psychology/profile');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'profile' => [
                        'id',
                        'big_five' => [
                            'openness' => [
                                'score',
                                'percentile',
                                'description'
                            ],
                            'conscientiousness' => [
                                'score',
                                'percentile',
                                'description'
                            ],
                            // ... other traits
                        ],
                        'attachment' => [
                            'primary_style',
                            'secure_score',
                            'anxious_score',
                            'avoidant_score',
                            'style_description'
                        ],
                        'compatibility' => [
                            'keywords',
                            'ideal_partner_traits',
                            'relationship_style'
                        ]
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_when_no_profile_exists()
    {
        $response = $this->getJson('/api/psychology/profile');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'PROFILE_NOT_FOUND'
            ]);
    }

    /** @test */
    public function it_calculates_trait_scores_correctly()
    {
        $question = PsychologyQuestion::with('options')->first();

        // Get option with high openness weight
        $highOpennessOption = $question->options->first();

        $responses = [
            [
                'question_id' => $question->id,
                'option_id' => $highOpennessOption->id,
                'response_time' => 5000
            ]
        ];

        $this->postJson('/api/psychology/questionnaire/submit', [
            'responses' => $responses
        ]);

        $profile = UserPsychologicalProfile::where('user_id', $this->user->id)->first();

        // Verify that openness score reflects the high weight
        $this->assertGreaterThan(50, $profile->openness_score);
        $this->assertIsFloat($profile->openness_score);
        $this->assertGreaterThanOrEqual(0, $profile->openness_score);
        $this->assertLessThanOrEqual(100, $profile->openness_score);
    }

    private function seedTestQuestions()
    {
        $question = PsychologyQuestion::create([
            'type' => 'visual_choice',
            'scenario_title' => 'Test Scenario',
            'scenario_description' => 'Test scenario description',
            'scenario_video_url' => '/test/video.mp4',
            'video_duration' => 20,
            'display_order' => 1,
            'is_active' => true
        ]);

        PsychologyQuestionOption::create([
            'question_id' => $question->id,
            'option_text' => 'Test option 1',
            'option_video_url' => '/test/option1.mp4',
            'video_duration' => 15,
            'display_order' => 1,
            'psychological_weights' => json_encode([
                'openness' => 2.0,
                'conscientiousness' => 0.5,
                'extraversion' => 1.0,
                'agreeableness' => 0.8,
                'neuroticism' => -0.5
            ]),
            'attachment_weights' => json_encode([
                'secure' => 1.5,
                'anxious' => 0.2,
                'avoidant' => 0.1
            ])
        ]);

        PsychologyQuestionOption::create([
            'question_id' => $question->id,
            'option_text' => 'Test option 2',
            'option_video_url' => '/test/option2.mp4',
            'video_duration' => 15,
            'display_order' => 2,
            'psychological_weights' => json_encode([
                'openness' => -1.0,
                'conscientiousness' => 1.8,
                'extraversion' => -0.5,
                'agreeableness' => 1.2,
                'neuroticism' => -0.8
            ]),
            'attachment_weights' => json_encode([
                'secure' => 1.0,
                'anxious' => -0.3,
                'avoidant' => 0.5
            ])
        ]);
    }
}
