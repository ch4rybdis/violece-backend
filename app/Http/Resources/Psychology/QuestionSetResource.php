<?php

// app/Http/Resources/Psychology/QuestionSetResource.php

namespace App\Http\Resources\Psychology;

use Illuminate\Http\Resources\Json\JsonResource;

class QuestionSetResource extends JsonResource
{
        public function toArray($request)
        {
                return [
                'id' => $this->id,
                'type' => $this->type,
                'scenario' => [
                'title' => $this->scenario_title,
                'description' => $this->scenario_description,
                'video_url' => $this->scenario_video_url,
                'duration' => $this->video_duration,
                'thumbnail_url' => $this->scenario_thumbnail_url
                ],
                'options' => $this->options->map(function ($option) {
                return [
                'id' => $option->id,
                'text' => $option->option_text,
                'video_url' => $option->option_video_url,
                'duration' => $option->video_duration,
                'thumbnail_url' => $option->option_thumbnail_url,
                'order' => $option->display_order
                ];
                }),
                'display_order' => $this->display_order,
                'estimated_duration' => ($this->video_duration ?? 20) + 15 // Video + choice time
                ];
                }
        }
