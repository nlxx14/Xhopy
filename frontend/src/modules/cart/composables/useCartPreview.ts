import { computed, ref } from 'vue';

/**
 * Session-only cart preview shared across Inertia page visits.
 *
 * The server-authoritative cart (prices, stock, persistence) arrives in Batch 5. Until then this
 * only reflects what the shopper clicked in this browser session and never claims a total.
 */
export interface CartPreviewProduct {
    name: string;
    priceFormatted: string;
}

const itemCount = ref(0);
const lastAdded = ref<CartPreviewProduct | null>(null);
const dialogOpen = ref(false);

export function useCartPreview() {
    return {
        itemCount: computed(() => itemCount.value),
        lastAdded: computed(() => lastAdded.value),
        dialogOpen,
        add(product: CartPreviewProduct): void {
            itemCount.value += 1;
            lastAdded.value = {
                name: product.name,
                priceFormatted: product.priceFormatted,
            };
        },
        open(): void {
            dialogOpen.value = true;
        },
    };
}
