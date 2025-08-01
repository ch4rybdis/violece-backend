<?php

namespace App\Http\Resources\Api\Psychology;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform question options for API responses
 * Handles video responses and trait impact data
 */
class OptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'text' => $this->option_text,
            'video' => [
                'url' => $this->formatVideoUrl($this->option_video_url),
                'duration' => (int) ($this->video_duration ?? 15), // Default 15s for options
                'thumbnail_url' => $this->formatThumbnailUrl($this->option_thumbnail_url),
                'autoplay' => false, // Options don't autoplay
                'muted' => true, // Start muted
            ],
            'display_order' => (int) $this->display_order,
            'trait_impacts' => $this->getTraitImpacts(),
            'psychological_weights' => $this->getPsychologicalWeights(),
            'description' => $this->option_description, // Optional extended description
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

        // Generate thumbnail from video if available
        if ($this->option_video_url) {
            // In production, this would generate/cache thumbnails
            return url("/images/thumbnails/option-{$this->id}.jpg");
        }

        // Ultimate fallback
        return url('/images/defaults/option-thumbnail.jpg');
    }

    /**
     * Get parsed trait impacts as structured data
     */
    private function getTraitImpacts(): array
    {
        $impacts = json_decode($this->trait_impacts, true) ?? [];

        // Standardize the format
        $standardized = [];
        foreach ($impacts as $trait => $impact) {
            $standardized[$trait] = [
                'value' => (float) $impact,
                'direction' => $impact > 0 ? 'positive' : ($impact < 0 ? 'negative' : 'neutral'),
                'strength' => $this->getImpactStrength($impact),
            ];
        }

        return $standardized;
    }

    /**
     * Get psychological weights for compatibility scoring
     * Hidden from client but useful for admin/analytics
     */
    private function getPsychologicalWeights(): array
    {
        // Only show weights to admin users or in debug mode
        if (!$this->shouldShowWeights()) {
            return [];
        }

        $impacts = json_decode($this->trait_impacts, true) ?? [];

        return [
            'big_five' => [
                'openness' => $impacts['openness'] ?? 0,
                'conscientiousness' => $impacts['conscientiousness'] ?? 0,
                'extraversion' => $impacts['extraversion'] ?? 0,
                'agreeableness' => $impacts['agreeableness'] ?? 0,
                'neuroticism' => $impacts['neuroticism'] ?? 0,
            ],
            'attachment' => [
                'secure' => $impacts['secure_attachment'] ?? 0,
                'anxious' => $impacts['anxious_attachment'] ?? 0,
                'avoidant' => $impacts['avoidant_attachment'] ?? 0,
            ],
            'behavioral' => [
                'spontaneity' => $impacts['spontaneity'] ?? 0,
                'routine_preference' => $impacts['routine_preference'] ?? 0,
                'social_energy' => $impacts['social_energy'] ?? 0,
                'conflict_style' => $impacts['conflict_style'] ?? 0,
            ],
        ];
    }

    /**
     * Determine impact strength category
     */
    private function getImpactStrength(float $impact): string
    {
        $abs = abs($impact);

        if ($abs >= 2.0) return 'very_strong';
        if ($abs >= 1.5) return 'strong';
        if ($abs >= 1.0) return 'moderate';
        if ($abs >= 0.5) return 'mild';

        return 'minimal';
    }

    /**
     * Check if psychological weights should be visible
     */
    private function shouldShowWeights(): bool
    {
        // Show weights to admin users
        if (auth()->check() && auth()->user()->hasRole('admin')) {
            return true;
        }

        // Show in debug mode for development
        if (config('app.debug') && app()->environment('local')) {
            return true;
        }

        return false;
    }
}
