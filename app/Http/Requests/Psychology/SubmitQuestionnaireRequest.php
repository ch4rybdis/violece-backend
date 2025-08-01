<?php

namespace App\Http\Requests\Psychology;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Psychology\PsychologyQuestion;
use App\Models\Psychology\QuestionOption;

/**
 * Validation for psychology questionnaire submission
 * Ensures data integrity and prevents manipulation
 */
class SubmitQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only authenticated users can submit questionnaires
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'responses' => 'required|array|min:1',
            'responses.*.question_id' => [
                'required',
                'integer',
                Rule::exists('psychological_questions', 'id')->where('is_active', true),
            ],
            'responses.*.option_id' => [
                'required',
                'integer',
                'exists:question_options,id',
            ],
            'responses.*.response_time' => [
                'required',
                'integer',
                'min:1000', // Minimum 1 second
                'max:300000', // Maximum 5 minutes per question
            ],
            'questionnaire_version' => 'sometimes|string|max:10',
            'start_time' => 'sometimes|date|before_or_equal:now',
            'completion_time' => 'sometimes|date|after:start_time|before_or_equal:now',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateResponseIntegrity($validator);
            $this->validateQuestionOptionRelationship($validator);
            $this->validateCompleteness($validator);
            $this->validateReasonableResponseTimes($validator);
        });
    }

    /**
     * Validate that responses contain valid data structure
     */
    private function validateResponseIntegrity($validator): void
    {
        $responses = $this->input('responses', []);

        // Check for duplicate question responses
        $questionIds = collect($responses)->pluck('question_id')->filter();
        if ($questionIds->count() !== $questionIds->unique()->count()) {
            $validator->errors()->add('responses', 'Duplicate responses for the same question are not allowed.');
        }

        // Validate response times aren't suspiciously fast
        $avgResponseTime = collect($responses)->avg('response_time');
        if ($avgResponseTime < 2000) { // Less than 2 seconds average
            $validator->errors()->add('responses', 'Response times appear too fast. Please take time to consider each question.');
        }
    }

    /**
     * Validate that question-option relationships are correct
     */
    private function validateQuestionOptionRelationship($validator): void
    {
        $responses = $this->input('responses', []);

        foreach ($responses as $index => $response) {
            if (!isset($response['question_id']) || !isset($response['option_id'])) {
                continue;
            }

            $option = QuestionOption::where('id', $response['option_id'])
                ->where('question_id', $response['question_id'])
                ->first();

            if (!$option) {
                $validator->errors()->add(
                    "responses.{$index}.option_id",
                    'The selected option does not belong to the specified question.'
                );
            }
        }
    }

    /**
     * Validate questionnaire completeness
     */
    private function validateCompleteness($validator): void
    {
        $responses = $this->input('responses', []);
        $responseQuestionIds = collect($responses)->pluck('question_id');

        // Get all active questions
        $activeQuestions = PsychologyQuestion::where('is_active', true)
            ->pluck('id');

        // Check if user has already completed questionnaire
        $user = auth()->user();
        if ($user->psychologicalProfile && $user->psychologicalProfile->is_complete) {
            $validator->errors()->add('responses', 'You have already completed the questionnaire.');
        }

        // For a complete submission, ensure all questions are answered
        if ($this->input('completion_type') === 'complete') {
            $missingQuestions = $activeQuestions->diff($responseQuestionIds);
            if ($missingQuestions->isNotEmpty()) {
                $validator->errors()->add(
                    'responses',
                    'Incomplete questionnaire. Missing responses for questions: ' .
                    $missingQuestions->implode(', ')
                );
            }
        }
    }

    /**
     * Validate response times are reasonable
     */
    private function validateReasonableResponseTimes($validator): void
    {
        $responses = $this->input('responses', []);
        $totalResponseTime = collect($responses)->sum('response_time');

        // Total time should be reasonable (not too fast, not impossibly long)
        $minExpectedTime = count($responses) * 3000; // 3 seconds minimum per question
        $maxExpectedTime = count($responses) * 180000; // 3 minutes maximum per question

        if ($totalResponseTime < $minExpectedTime) {
            $validator->errors()->add(
                'responses',
                'Total response time seems too fast. Please take adequate time to consider each question.'
            );
        }

        if ($totalResponseTime > $maxExpectedTime) {
            $validator->errors()->add(
                'responses',
                'Session timeout. Please retake the questionnaire in a shorter timeframe.'
            );
        }

        // Check for individual questions with unreasonable times
        foreach ($responses as $index => $response) {
            $responseTime = $response['response_time'] ?? 0;

            if ($responseTime < 1000) { // Less than 1 second
                $validator->errors()->add(
                    "responses.{$index}.response_time",
                    'Response time too fast for question consideration.'
                );
            }

            if ($responseTime > 300000) { // More than 5 minutes
                $validator->errors()->add(
                    "responses.{$index}.response_time",
                    'Response time too long - session may have timed out.'
                );
            }
        }
    }

    public function messages(): array
    {
        return [
            'responses.required' => 'Questionnaire responses are required.',
            'responses.array' => 'Responses must be provided as a list.',
            'responses.min' => 'At least one question response is required.',
            'responses.*.question_id.required' => 'Question ID is required for each response.',
            'responses.*.question_id.exists' => 'Invalid question ID provided.',
            'responses.*.option_id.required' => 'Option selection is required for each response.',
            'responses.*.option_id.exists' => 'Invalid option ID provided.',
            'responses.*.response_time.required' => 'Response time is required for analytics.',
            'responses.*.response_time.integer' => 'Response time must be a valid number.',
            'responses.*.response_time.min' => 'Response time too short.',
            'responses.*.response_time.max' => 'Response time too long.',
            'questionnaire_version.string' => 'Questionnaire version must be a string.',
            'start_time.date' => 'Start time must be a valid date.',
            'start_time.before_or_equal' => 'Start time cannot be in the future.',
            'completion_time.date' => 'Completion time must be a valid date.',
            'completion_time.after' => 'Completion time must be after start time.',
            'completion_time.before_or_equal' => 'Completion time cannot be in the future.',
        ];
    }

    /**
     * Get validated and sanitized response data
     */
    public function getValidatedResponses(): array
    {
        $responses = $this->validated()['responses'];

        // Add metadata to each response
        return collect($responses)->map(function ($response) {
            return [
                'question_id' => (int) $response['question_id'],
                'option_id' => (int) $response['option_id'],
                'response_time' => (int) $response['response_time'],
                'submitted_at' => now(),
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
            ];
        })->toArray();
    }

    /**
     * Calculate questionnaire analytics
     */
    public function getAnalytics(): array
    {
        $responses = $this->input('responses', []);
        $responseTimes = collect($responses)->pluck('response_time');

        return [
            'total_questions' => count($responses),
            'total_time' => $responseTimes->sum(),
            'average_response_time' => round($responseTimes->avg(), 2),
            'fastest_response' => $responseTimes->min(),
            'slowest_response' => $responseTimes->max(),
            'median_response_time' => $responseTimes->median(),
            'completion_rate' => 100, // Since this is a completed submission
            'questionnaire_version' => $this->input('questionnaire_version', '1.0'),
            'submission_timestamp' => now()->toISOString(),
        ];
    }
}
