<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\CampaignPlacement;
use Database\Factories\CampaignContentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Editorial / campaign content (the "Daily Interest" banner and tiles).
 *
 * @property int $id
 * @property CampaignPlacement $placement
 * @property string $tag
 * @property string $title
 * @property string $summary
 * @property string $cta_label
 * @property string $href
 * @property string|null $image_path
 * @property string|null $image_alt
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
#[UseFactory(CampaignContentFactory::class)]
class CampaignContent extends Model
{
    /** @use HasFactory<CampaignContentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'placement',
        'tag',
        'title',
        'summary',
        'cta_label',
        'href',
        'image_path',
        'image_alt',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'placement' => CampaignPlacement::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Content that is switched on and inside its optional schedule window.
     *
     * @param  Builder<CampaignContent>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query
            ->where('campaign_contents.is_active', true)
            ->where(fn (Builder $window) => $window
                ->whereNull('campaign_contents.starts_at')
                ->orWhere('campaign_contents.starts_at', '<=', now()))
            ->where(fn (Builder $window) => $window
                ->whereNull('campaign_contents.ends_at')
                ->orWhere('campaign_contents.ends_at', '>', now()));
    }
}
