<?php

namespace App\Jobs;

use App\Events\Dating\DateSuggested;
use App\Models\Dating\UserMatch;
use App\Notifications\Dating\DateSuggestionNotification;
use App\Services\Dating\ConversationAnalyzerService;
use App\Services\Dating\DateSuggestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeConversationForDateSuggestion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $match;

    public function __construct(UserMatch $match)
    {
        $this->match = $match;
    }

    public function handle(ConversationAnalyzerService $analyzerService, DateSuggestionService $suggestionService)
    {
        try {
            // Check if the match still exists and is active
            if (!$this->match || !$this->match->is_active) {
                return;
            }

            // Check if conditions are met for a date suggestion
            if (!$analyzerService->shouldSuggestDate($this->match)) {
                return;
            }

            // Generate the date suggestion
            $suggestion = $suggestionService->generateSuggestion($this->match);

            // Broadcast event for real-time updates
            broadcast(new DateSuggested($suggestion));

            // Notify both users
            $this->match->user1->notify(new DateSuggestionNotification($suggestion));
            $this->match->user2->notify(new DateSuggestionNotification($suggestion));

            Log::info('Date suggestion created', [
                'match_id' => $this->match->id,
                'suggestion_id' => $suggestion->id,
                'activity' => $suggestion->activity_name
            ]);
        } catch (\Exception $e) {
            Log::error('Error analyzing conversation for date suggestion', [
                'match_id' => $this->match->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
