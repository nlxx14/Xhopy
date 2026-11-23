export interface AdminMetrics {
    paidRevenueFormatted: string;
    totalOrders: number;
    pendingOrders: number;
    activeProducts: number;
    lowStockProducts: number;
}

export interface StockMovementEntry {
    id: number;
    productName: string;
    sku: string;
    type: string;
    typeLabel: string;
    quantityDelta: number;
    previousQuantity: number;
    resultingQuantity: number;
    reason: string | null;
    actorName: string;
    occurredAtDiff: string;
}

export interface AdminAuditEntry {
    id: number;
    action: string;
    summary: string;
    actorName: string;
    actorRole: string;
    ipAddress: string | null;
    occurredAtDiff: string;
}

export interface AdminProductRow {
    id: number;
    categoryId: number;
    categoryName: string;
    categorySlug: string;
    name: string;
    slug: string;
    sku: string;
    shortDescription: string | null;
    description: string | null;
    price: string;
    compareAtPrice: string | null;
    priceFormatted: string;
    stockQuantity: number;
    ledgerStockSum: number;
    isLedgerBalanced: boolean;
    status: 'draft' | 'active' | 'archived';
    isFeatured: boolean;
    featuredRank: number | null;
    imageUrl?: string | null;
    colorImages?: Record<string, string>;
    availableColors?: string[];
    availableSizes?: string[];
    variantStocks?: Record<string, number> | null;
}

export interface AdminCategoryRow {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sortOrder: number;
    isActive: boolean;
    productCount: number;
}

export interface AdminOrderRow {
    id: number;
    orderNumber: string;
    status: string;
    statusLabel: string;
    paymentStatus: string;
    paymentStatusLabel: string;
    paymentMethod: string;
    currency: string;
    totalFormatted: string;
    itemCount: number;
    placedAtFormatted: string;
    placedAtDiff: string;
    customerName: string;
    customerEmail: string;
    shippingCity: string;
    shippingProvince: string;
    allowedTransitions: Array<{ value: string; label: string }>;
    itemsPreview?: Array<{
        id: number;
        productName: string;
        quantity: number;
        selectedColor?: string | null;
        selectedSize?: string | null;
    }>;
}

export interface AdminCustomerRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: 'customer' | 'staff' | 'admin';
    roleLabel: string;
    emailVerified: boolean;
    addressCount: number;
    joinedAtFormatted: string;
}
