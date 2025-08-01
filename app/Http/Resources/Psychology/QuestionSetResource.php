<?php

namespace App\Http\Resources\Psychology;

use Illuminate\Http\Resources\Json\JsonResource;

class QuestionSetResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'type' => $this->type,
            'scenario' => [
                'title' => $this->scenario_title,
                'description' => $this->scenario_description,
                'video_url' => $this->scenario_video_url,
                'duration' => (int) ($this->video_duration ?? 20),
                'thumbnail_url' => $this->scenario_thumbnail_url,
            ],
            'options' => $this->options->map(function ($option) {
                return [
                    'id' => (int) $option->id,
                    'text' => $option->option_text,
                    'video_url' => $option->option_video_url,
                    'duration' => (int) ($option->video_duration ?? 0),
                    'thumbnail_url' => $option->option_thumbnail_url,
                    'order' => (int) $option->display_order,
                ];
            })->values()->toArray(),
            'display_order' => (int) $this->display_order,
            'estimated_duration' => (int) (($this->video_duration ?? 20) + 15),
        ];
    }
}
