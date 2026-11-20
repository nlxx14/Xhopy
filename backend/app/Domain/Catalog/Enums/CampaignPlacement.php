<?php

namespace App\Domain\Catalog\Enums;

enum CampaignPlacement: string
{
    case DailyInterestFeatured = 'daily_interest_featured';
    case DailyInterestTile = 'daily_interest_tile';
}
