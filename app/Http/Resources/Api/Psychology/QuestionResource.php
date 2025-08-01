<?php

namespace App\Http\Resources\Api\Psychology;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform psychology questions for API responses
 * Standardizes video scenario data for mobile app consumption
 */
class QuestionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'type' => $this->type,
            'scenario' => [
                'title' => $this->scenario_title,
                'description' => $this->scenario_description,
                'video_url' => $this->formatVideoUrl($this->scenario_video_url),
                'duration' => (int) ($this->video_duration ?? 20), // Default 20s
                'thumbnail_url' => $this->formatThumbnailUrl($this->scenario_thumbnail_url),
                'loop' => true, // All scenario videos loop
            ],
            'options' => OptionResource::collection($this->whenLoaded('options')),
            'display_order' => (int) $this->display_order,
            'estimated_duration' => $this->calculateEstimatedDuration(),
            'metadata' => [
                'is_active' => (bool) $this->is_active,
                'created_at' => $this->created_at?->toISOString(),
                'traits_measured' => $this->getTraitsMeasured(),
            ],
        ];
    }

    /**
     * Format video URL for CDN or local storage
     */
    private function formatVideoUrl(?string $videoUrl): ?string
    {
        if (!$videoUrl) {
            return null;
        }

        // If already a full URL, return as-is
        if (str_starts_with($videoUrl, 'http')) {
            return $videoUrl;
        }

        // For local storage, prepend base URL
        return url($videoUrl);
    }

    /**
     * Format thumbnail URL with fallback
     */
    private function formatThumbnailUrl(?string $thumbnailUrl): string
    {
        if ($thumbnailUrl) {
            return $this->formatVideoUrl($thumbnailUrl);
        }

        // Fallback to default scenario thumbnail
        return url('/images/defaults/scenario-thumbnail.jpg');
    }

    /**
     * Calculate total estimated duration including all options
     */
    private function calculateEstimatedDuration(): int
    {
        $scenarioDuration = $this->video_duration ?? 20;
        $reviewTime = 10; // Time to review options
        $selectionTime = 5; // Time to make selection

        return $scenarioDuration + $reviewTime + $selectionTime;
    }

    /**
     * Extract traits that this question measures from options
     */
    private function getTraitsMeasured(): array
    {
        if (!$this->relationLoaded('options')) {
            return [];
        }

        $traits = [];
        foreach ($this->options as $option) {
            $traitImpacts = json_decode($option->trait_impacts, true) ?? [];
            $traits = array_merge($traits, array_keys($traitImpacts));
        }

        return array_unique($traits);
    }
}
