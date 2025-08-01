<?php

namespace App\Console\Commands;

use App\Models\Events\WeeklyEvent;
use App\Services\Events\EventMatchmakingService;
use Illuminate\Console\Command;

class ProcessEventMatches extends Command
{
    protected $signature = 'events:process-matches {event_id?}';
    protected $description = 'Process matches for completed events';

    protected $matchmakingService;

    public function __construct(EventMatchmakingService $matchmakingService)
    {
        parent::__construct();
        $this->matchmakingService = $matchmakingService;
    }

    public function handle()
    {
        $eventId = $this->argument('event_id');

        if ($eventId) {
            // Process specific event
            $event = WeeklyEvent::find($eventId);

            if (!$event) {
                $this->error("Event with ID {$eventId} not found!");
                return 1;
            }

            $this->processEvent($event);
        } else {
            // Process all events that have ended but not completed
            $events = WeeklyEvent::where('status', '!=', WeeklyEvent::STATUS_COMPLETED)
                ->where('ends_at', '<', now())
                ->get();

            $this->info("Found {$events->count()} events to process.");

            foreach ($events as $event) {
                $this->processEvent($event);
            }
        }

        return 0;
    }

    protected function processEvent(WeeklyEvent $event)
    {
        $this->info("Processing matches for event: {$event->title} (ID: {$event->id})");

        // Mark event as processing
        if ($event->status !== WeeklyEvent::STATUS_PROCESSING) {
            $event->update(['status' => WeeklyEvent::STATUS_PROCESSING]);
        }

        // Generate matches
        $matches = $this->matchmakingService->processEventMatches($event);

        $this->info("Generated {$matches->count()} potential matches.");

        // Mark event as completed
        $event->update(['status' => WeeklyEvent::STATUS_COMPLETED]);

        $this->info("Event processing completed.");
    }
}
