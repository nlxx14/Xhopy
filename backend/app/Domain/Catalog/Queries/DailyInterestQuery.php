<?php

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Enums\CampaignPlacement;
use App\Domain\Catalog\Models\CampaignContent;
use App\Domain\Catalog\Support\ProductPresenter;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves the live "Daily Interest" module from campaign content.
 * Returns null when no featured banner is live so the storefront hides the whole module.
 */
final class DailyInterestQuery
{
    private const MAX_TILES = 4;

    public function __construct(private readonly ProductPresenter $presenter) {}

    /**
     * @return array{featured: array<string, mixed>, tiles: list<array<string, mixed>>}|null
     */
    public function current(): ?array
    {
        $featured = $this->live(CampaignPlacement::DailyInterestFeatured, 1)->first();

        if ($featured === null) {
            return null;
        }

        return [
            'featured' => $this->presenter->campaign($featured),
            'tiles' => array_values(
                $this->live(CampaignPlacement::DailyInterestTile, self::MAX_TILES)
                    ->map(fn (CampaignContent $tile): array => $this->presenter->campaign($tile))
                    ->all(),
            ),
        ];
    }

    /**
     * @return Collection<int, CampaignContent>
     */
    private function live(CampaignPlacement $placement, int $limit): Collection
    {
        return CampaignContent::query()
            ->live()
            ->where('campaign_contents.placement', $placement)
            ->orderBy('campaign_contents.sort_order')
            ->orderBy('campaign_contents.id')
            // Over-fetch slightly, then drop entries whose destination is not a safe link.
            ->limit($limit * 3)
            ->get()
            ->filter(fn (CampaignContent $content): bool => ProductPresenter::isSafeHref($content->href))
            ->take($limit)
            ->values();
    }
}
