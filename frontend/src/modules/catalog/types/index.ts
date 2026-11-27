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
    stockQuantity?: number;
    imageUrl?: string | null;
    imageAlt: string;
    colorImages?: Record<string, string>;
    availableColors?: string[];
    availableSizes?: string[];
    variantStocks?: Record<string, number> | null;
    selectedColor?: string | null;
    selectedSize?: string | null;
    soldCount?: number;
    isWishlisted?: boolean;
    addedAtDiff?: string;
    ratingAverage?: number | null;
    ratingCount?: number | null;
}

export interface ProductImage {
    id: number;
    url: string | null;
    alt: string;
    colorName?: string | null;
}

export interface ProductReview {
    id: number;
    rating: number;
    title: string | null;
    comment: string | null;
    isVerifiedPurchase: boolean;
    authorName: string;
    createdAtFormatted: string;
    createdAtDiff: string;
}

export interface ProductDetail extends ProductSummary {
    shortDescription?: string | null;
    description?: string | null;
    images: ProductImage[];
    reviews?: ProductReview[];
    canReview?: boolean;
    userReview?: ProductReview | null;
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
    onSale: boolean;
    minRating: number | null;
    sort: string;
    perPage: number;
}

export interface FilterableCategory {
    id: number | string;
    name: string;
    slug: string;
    productCount?: number | null;
}
