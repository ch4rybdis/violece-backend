<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Jobs\AnalyzeConversationForDateSuggestion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CheckForDateSuggestionOpportunity implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(MessageSent $event)
    {
        // Get the match from the message
        $match = $event->message->match;

        // Queue the analysis job with a 30-second delay
        // This gives time for a conversation to develop
        AnalyzeConversationForDateSuggestion::dispatch($match)
            ->delay(now()->addSeconds(30));
    }
}
