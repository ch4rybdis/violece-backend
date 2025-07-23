<?php

// app/Http/Controllers/Api/Psychology/QuestionnaireController.php

namespace App\Http\Controllers\Api\Psychology;

use App\Http\Controllers\Controller;
use App\Models\Psychology\PsychologyQuestion;
use App\Models\Psychology\PsychologyAnswer;
use App\Models\Psychology\UserPsychologicalProfile;
use App\Services\Psychology\PsychologicalScoringService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class QuestionnaireController extends Controller
{
    protected PsychologicalScoringService $scoringService;

    public function __construct(PsychologicalScoringService $scoringService)
    {
        $this->middleware('auth:sanctum');
        $this->scoringService = $scoringService;
    }

    /**
     * Get questionnaire questions for psychological profiling
     * Based on academic research: Big Five + attachment theory scenarios
     */
    public function getQuestions(): JsonResponse
    {
        try {
            $questions = PsychologyQuestion::with(['options' => function($query) {
                $query->orderBy('display_order');
            }])
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get();

            // Transform for frontend consumption
            $transformedQuestions = $questions->map(function ($question) {
                return [
                    'id' => $question->id,
                    'type' => $question->type,
                    'scenario' => [
                        'title' => $question->scenario_title,
                        'description' => $question->scenario_description,
                        'video_url' => $question->scenario_video_url,
                        'duration' => $question->video_duration ?? 20 // Default 20s
                    ],
                    'options' => $question->options->map(function ($option) {
                        return [
                            'id' => $option->id,
                            'text' => $option->option_text,
                            'video_url' => $option->option_video_url,
                            'duration' => $option->video_duration ?? 15,
                            // Don't expose weights to frontend for security
                            'order' => $option->display_order
                        ];
                    })
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'questions' => $transformedQuestions,
                    'total_questions' => $questions->count(),
                    'estimated_duration' => $questions->count() * 35 // Avg 35s per question
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve questionnaire questions',
                'error_code' => 'QUESTIONNAIRE_FETCH_ERROR'
            ], 500);
        }
    }

    /**
     * Submit questionnaire responses and generate psychological profile
     * Implements academic research-based scoring algorithms
     */
    public function submitQuestionnaire(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'responses' => 'required|array|min:1',
            'responses.*.question_id' => 'required|exists:psychology_questions,id',
            'responses.*.option_id' => 'required|exists:psychology_question_options,id',
            'responses.*.response_time' => 'nullable|integer|min:1|max:300000' // Max 5 minutes
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid questionnaire responses',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::user();
            $responses = $request->input('responses');

            // Store individual responses
            foreach ($responses as $response) {
                PsychologyAnswer::create([
                    'user_id' => $user->id,
                    'question_id' => $response['question_id'],
                    'option_id' => $response['option_id'],
                    'response_time_ms' => $response['response_time'] ?? null,
                    'answered_at' => now()
                ]);
            }

            // Generate psychological profile using academic scoring
            $profile = $this->scoringService->generateProfile($user->id, $responses);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Questionnaire completed successfully',
                'data' => [
                    'profile_id' => $profile->id,
                    'big_five_scores' => [
                        'openness' => $profile->openness_score,
                        'conscientiousness' => $profile->conscientiousness_score,
                        'extraversion' => $profile->extraversion_score,
                        'agreeableness' => $profile->agreeableness_score,
                        'neuroticism' => $profile->neuroticism_score
                    ],
                    'attachment_style' => [
                        'primary' => $profile->primary_attachment_style,
                        'secure_score' => $profile->secure_attachment_score,
                        'anxious_score' => $profile->anxious_attachment_score,
                        'avoidant_score' => $profile->avoidant_attachment_score
                    ],
                    'compatibility_keywords' => $profile->compatibility_keywords,
                    'profile_strength' => $profile->profile_strength,
                    'completion_date' => $profile->created_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process questionnaire responses',
                'error_code' => 'QUESTIONNAIRE_PROCESSING_ERROR'
            ], 500);
        }
    }

    /**
     * Get current user's psychological profile
     */
    public function getProfile(): JsonResponse
    {
        try {
            $user = Auth::user();
            $profile = UserPsychologicalProfile::where('user_id', $user->id)
                ->where('is_active', true)
                ->latest()
                ->first();

            if (!$profile) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No psychological profile found. Please complete the questionnaire first.',
                    'error_code' => 'PROFILE_NOT_FOUND'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'profile' => [
                        'id' => $profile->id,
                        'big_five' => [
                            'openness' => [
                                'score' => $profile->openness_score,
                                'percentile' => $this->scoringService->calculatePercentile('openness', $profile->openness_score),
                                'description' => $this->scoringService->getTraitDescription('openness', $profile->openness_score)
                            ],
                            'conscientiousness' => [
                                'score' => $profile->conscientiousness_score,
                                'percentile' => $this->scoringService->calculatePercentile('conscientiousness', $profile->conscientiousness_score),
                                'description' => $this->scoringService->getTraitDescription('conscientiousness', $profile->conscientiousness_score)
                            ],
                            'extraversion' => [
                                'score' => $profile->extraversion_score,
                                'percentile' => $this->scoringService->calculatePercentile('extraversion', $profile->extraversion_score),
                                'description' => $this->scoringService->getTraitDescription('extraversion', $profile->extraversion_score)
                            ],
                            'agreeableness' => [
                                'score' => $profile->agreeableness_score,
                                'percentile' => $this->scoringService->calculatePercentile('agreeableness', $profile->agreeableness_score),
                                'description' => $this->scoringService->getTraitDescription('agreeableness', $profile->agreeableness_score)
                            ],
                            'neuroticism' => [
                                'score' => $profile->neuroticism_score,
                                'percentile' => $this->scoringService->calculatePercentile('neuroticism', $profile->neuroticism_score),
                                'description' => $this->scoringService->getTraitDescription('neuroticism', $profile->neuroticism_score)
                            ]
                        ],
                        'attachment' => [
                            'primary_style' => $profile->primary_attachment_style,
                            'secure_score' => $profile->secure_attachment_score,
                            'anxious_score' => $profile->anxious_attachment_score,
                            'avoidant_score' => $profile->avoidant_attachment_score,
                            'style_description' => $this->scoringService->getAttachmentDescription($profile->primary_attachment_style)
                        ],
                        'compatibility' => [
                            'keywords' => $profile->compatibility_keywords,
                            'ideal_partner_traits' => $this->scoringService->generateIdealPartnerTraits($profile),
                            'relationship_style' => $this->scoringService->getPredictedRelationshipStyle($profile)
                        ],
                        'meta' => [
                            'profile_strength' => $profile->profile_strength,
                            'created_at' => $profile->created_at->toISOString(),
                            'is_complete' => true
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve psychological profile',
                'error_code' => 'PROFILE_FETCH_ERROR'
            ], 500);
        }
    }
}






