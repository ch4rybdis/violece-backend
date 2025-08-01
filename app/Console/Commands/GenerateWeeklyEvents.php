<?php

namespace App\Console\Commands;

use App\Models\Events\WeeklyEvent;
use App\Services\Events\EventGeneratorService;
use Illuminate\Console\Command;

class GenerateWeeklyEvents extends Command
{
    protected $signature = 'events:generate {count=1}';
    protected $description = 'Generate weekly events for the platform';

    protected $eventGenerator;

    public function __construct(EventGeneratorService $eventGenerator)
    {
        parent::__construct();
        $this->eventGenerator = $eventGenerator;
    }

    public function handle()
    {
        $count = (int) $this->argument('count');

        $this->info("Generating {$count} weekly events...");

        $eventTypes = [
            WeeklyEvent::TYPE_PERSONALITY_QUIZ,
            WeeklyEvent::TYPE_SCENARIO_CHALLENGE,
            WeeklyEvent::TYPE_VALUES_ALIGNMENT,
            WeeklyEvent::TYPE_LIFESTYLE_MATCHING,
        ];

        $eventsGenerated = 0;

        for ($i = 0; $i < $count; $i++) {
            $type = $eventTypes[$i % count($eventTypes)];

            switch ($type) {
                case WeeklyEvent::TYPE_PERSONALITY_QUIZ:
                    $event = $this->eventGenerator->createPersonalityQuiz(
                        "Weekly Personality Discovery",
                        "Learn more about yourself and find compatible matches based on psychological traits.",
                        [
                            'theme' => 'relationships',
                            'starts_at' => now()->addDays(1),
                            'ends_at' => now()->addDays(4),
                        ]
                    );
                    break;

                case WeeklyEvent::TYPE_SCENARIO_CHALLENGE:
                    $event = $this->eventGenerator->createScenarioChallenge(
                        "Relationship Dilemmas",
                        "How would you handle these challenging relationship scenarios? Find matches who think like you.",
                        [
                            'scenario_type' => 'relationship',
                            'starts_at' => now()->addDays(1),
                            'ends_at' => now()->addDays(4),
                        ]
                    );
                    break;

                case WeeklyEvent::TYPE_VALUES_ALIGNMENT:
                    $event = $this->eventGenerator->createValuesAlignment(
                        "Core Values Explorer",
                        "Discover what matters most to you and find others who share your fundamental values.",
                        [
                            'value_categories' => ['ethics', 'lifestyle', 'future'],
                            'starts_at' => now()->addDays(1),
                            'ends_at' => now()->addDays(4),
                        ]
                    );
                    break;

                case WeeklyEvent::TYPE_LIFESTYLE_MATCHING:
                    $event = $this->eventGenerator->createLifestyleMatching(
                        "Lifestyle Compatibility",
                        "Daily routines, habits, and preferences - find someone who fits your lifestyle.",
                        [
                            'focus_areas' => ['daily_routine', 'leisure', 'health'],
                            'starts_at' => now()->addDays(1),
                            'ends_at' => now()->addDays(4),
                        ]
                    );
                    break;
            }

            $this->info("Generated event: {$event->title} (ID: {$event->id})");
            $eventsGenerated++;
        }

        $this->info("Successfully generated {$eventsGenerated} events.");

        return 0;
    }
}
