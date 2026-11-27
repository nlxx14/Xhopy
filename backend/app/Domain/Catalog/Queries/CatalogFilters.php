<?php

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Enums\ProductSort;

/**
 * Validated, bounded catalog listing criteria.
 */
final readonly class CatalogFilters
{
    public const DEFAULT_PER_PAGE = 12;

    public const PER_PAGE_OPTIONS = [12, 24, 48];

    public function __construct(
        public string $query = '',
        public ?string $category = null,
        public ?string $minPrice = null,
        public ?string $maxPrice = null,
        public bool $inStockOnly = false,
        public bool $onSaleOnly = false,
        public ?int $minRating = null,
        public ProductSort $sort = ProductSort::Featured,
        public int $perPage = self::DEFAULT_PER_PAGE,
        public int $page = 1,
    ) {}

    /**
     * @param  array<string, mixed>  $validated  Output of CatalogIndexRequest::validated()
     */
    public static function fromValidated(array $validated): self
    {
        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        return new self(
            query: trim((string) ($validated['q'] ?? '')),
            category: isset($validated['category']) && $validated['category'] !== ''
                ? (string) $validated['category']
                : null,
            minPrice: isset($validated['min_price']) ? self::normalizeAmount($validated['min_price']) : null,
            maxPrice: isset($validated['max_price']) ? self::normalizeAmount($validated['max_price']) : null,
            inStockOnly: (bool) ($validated['in_stock'] ?? false),
            onSaleOnly: (bool) ($validated['on_sale'] ?? false),
            minRating: isset($validated['min_rating']) ? (int) $validated['min_rating'] : null,
            sort: ProductSort::tryFrom((string) ($validated['sort'] ?? '')) ?? ProductSort::Featured,
            perPage: in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE,
            page: max(1, (int) ($validated['page'] ?? 1)),
        );
    }

    /**
     * Whether any narrowing criterion (beyond sort/pagination) is active.
     */
    public function isFiltered(): bool
    {
        return $this->query !== ''
            || $this->category !== null
            || $this->minPrice !== null
            || $this->maxPrice !== null
            || $this->inStockOnly
            || $this->onSaleOnly
            || $this->minRating !== null;
    }

    private static function normalizeAmount(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
