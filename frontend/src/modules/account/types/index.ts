export type { Auth, User, UserRole } from '@shared/types/auth';

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};

export type CustomerAddress = {
    id: number;
    label: string;
    recipientName: string;
    phone: string;
    line1: string;
    line2: string | null;
    barangay: string | null;
    city: string;
    province: string;
    postalCode: string;
    countryCode: string;
    deliveryNotes: string | null;
    isDefaultShipping: boolean;
    isDefaultBilling: boolean;
    formattedSummary: string;
};

export type BrowserSession = {
    id: string;
    ipAddress: string | null;
    device: string;
    isCurrent: boolean;
    lastActiveDiff: string;
};

export type SecurityAuditEvent = {
    id: number;
    event: string;
    label: string;
    ipAddress: string | null;
    occurredAtDiff: string;
};

export type OrderSummary = {
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
    canCancel: boolean;
};

export type OrderItemReviewSummary = {
    id: number;
    rating: number;
    title: string | null;
    comment: string | null;
    updatedAtDiff: string;
};

export type OrderLineItemSnapshot = {
    id: number;
    productId: number | null;
    productName: string;
    productSlug: string;
    sku: string;
    quantity: number;
    unitPriceFormatted: string;
    lineTotalFormatted: string;
    canReview?: boolean;
    review?: OrderItemReviewSummary | null;
};

export type OrderTimelineEntry = {
    id: number;
    fromStatus: string | null;
    toStatus: string;
    toStatusLabel: string;
    note: string | null;
    occurredAtDiff: string;
};

export type OrderPaymentInfo = {
    provider: string;
    providerReference: string | null;
    checkoutUrl: string | null;
    status: string;
};

export type OrderDetail = OrderSummary & {
    subtotalFormatted: string;
    discountFormatted: string;
    shippingFormatted: string;
    shippingAddress: {
        recipientName: string;
        phone: string;
        line1: string;
        line2: string | null;
        barangay: string | null;
        city: string;
        province: string;
        postalCode: string;
        countryCode: string;
        notes: string | null;
        formattedSummary: string;
    };
    items: OrderLineItemSnapshot[];
    timeline: OrderTimelineEntry[];
    payment: OrderPaymentInfo | null;
};
