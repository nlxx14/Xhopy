<?php

namespace App\Domain\Catalog\Enums;

/**
 * Allowlist of public product sort orders. Never sort by raw client input.
 */
enum ProductSort: string
{
    case Featured = 'featured';
    case Newest = 'newest';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case NameAsc = 'name_asc';

    public function label(): string
    {
        return match ($this) {
            self::Featured => 'Featured',
            self::Newest => 'Newest arrivals',
            self::PriceAsc => 'Price: low to high',
            self::PriceDesc => 'Price: high to low',
            self::NameAsc => 'Name: A to Z',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $sort): array => ['value' => $sort->value, 'label' => $sort->label()],
            self::cases(),
        );
    }
}
