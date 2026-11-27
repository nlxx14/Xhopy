export interface CartSummary {
    itemCount: number;
    subtotalFormatted?: string | null;
}

export interface CartLineItem {
    id: number;
    productId: number;
    name: string;
    slug: string;
    sku: string;
    categoryName: string;
    unitPriceFormatted: string;
    lineTotalFormatted: string;
    quantity: number;
    maxQuantity: number;
    availableStock: number;
    isAvailable: boolean;
    availabilityLabel: string;
    imageUrl: string | null;
    imageAlt: string;
    selectedColor?: string | null;
    selectedSize?: string | null;
    availableColors?: string[];
    availableSizes?: string[];
    variantStocks?: Record<string, number> | null;
}

export interface CartDetail {
    items: CartLineItem[];
    itemCount: number;
    currency: string;
    subtotalCents: number;
    subtotalFormatted: string;
    hasUnavailableItems: boolean;
}
