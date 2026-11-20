<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\CampaignPlacement;
use App\Domain\Catalog\Models\CampaignContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignContent>
 */
class CampaignContentFactory extends Factory
{
    protected $model = CampaignContent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'placement' => CampaignPlacement::DailyInterestTile,
            'tag' => fake()->words(2, true),
            'title' => fake()->sentence(5),
            'summary' => fake()->sentence(12),
            'cta_label' => 'Browse products',
            'href' => '/products',
            'image_path' => null,
            'image_alt' => null,
            'sort_order' => 0,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function featuredBanner(): static
    {
        return $this->state(fn (): array => ['placement' => CampaignPlacement::DailyInterestFeatured]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
