export interface ProductSummary {
    id: number | string;
    name: string;
    slug: string;
    sku?: string | null;
    categoryName?: string | null;
    categorySlug?: string | null;
    sellerName?: string | null;
    unitPrice: number;
    priceFormatted: string;
    originalPrice?: number | null;
    originalPriceFormatted?: string | null;
    discountLabel?: string | null;
    currency: string;
    inStock: boolean;
    stockLabel: string;
    imageUrl?: string | null;
    imageAlt: string;
    isWishlisted?: boolean;
    addedAtDiff?: string;
    ratingAverage?: number | null;
    ratingCount?: number | null;
}

export interface ProductImage {
    id: number;
    url: string | null;
    alt: string;
}

export interface ProductDetail extends ProductSummary {
    shortDescription?: string | null;
    description?: string | null;
    images: ProductImage[];
}

export interface PaginationMeta {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
}

export interface SortOption {
    value: string;
    label: string;
}

export interface CatalogFilterState {
    q: string;
    category: string | null;
    minPrice: string | null;
    maxPrice: string | null;
    inStock: boolean;
    sort: string;
    perPage: number;
}

export interface FilterableCategory {
    id: number | string;
    name: string;
    slug: string;
    productCount?: number | null;
}
