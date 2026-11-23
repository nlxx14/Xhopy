<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    CreditCard,
    MapPin,
    MessageSquarePlus,
    ShieldCheck,
    Star,
    XCircle,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import type {
    OrderDetail,
    OrderLineItemSnapshot,
} from '@modules/account/types';
import Heading from '@shared/components/Heading.vue';
import InputError from '@shared/components/InputError.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@shared/components/ui/dialog';
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import {
    cancel as cancelOrderRoute,
    index as ordersIndex,
} from '@/routes/orders';
import { show as showProduct } from '@/routes/products';

const props = defineProps<{
    order: OrderDetail;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Orders',
                href: ordersIndex(),
            },
        ],
    },
});

const reviewDialogOpen = ref(false);
const activeReviewItem = ref<OrderLineItemSnapshot | null>(null);

const reviewForm = useForm({
    rating: 5,
    title: '',
    comment: '',
});

const starLabels: Record<number, string> = {
    1: 'Poor',
    2: 'Fair',
    3: 'Good',
    4: 'Very Good',
    5: 'Excellent',
};

const unreviewedDeliveredItems = computed(() =>
    props.order.items.filter((item) => Boolean(item.canReview) && !item.review),
);

onMounted(() => {
    if (
        typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).get('review') === '1'
    ) {
        const target =
            unreviewedDeliveredItems.value[0] ??
            props.order.items.find((item) => Boolean(item.canReview)) ??
            null;
        if (target) {
            openReviewDialog(target);
        }
    }
});

function handleCancelOrder(): void {
    router.post(
        cancelOrderRoute.url(props.order.orderNumber),
        {},
        { preserveScroll: true },
    );
}

function openReviewDialog(item: OrderLineItemSnapshot): void {
    activeReviewItem.value = item;
    reviewForm.rating = item.review?.rating ?? 5;
    reviewForm.title = item.review?.title ?? '';
    reviewForm.comment = item.review?.comment ?? '';
    reviewForm.clearErrors();
    reviewDialogOpen.value = true;
}

function submitReview(): void {
    const productId = activeReviewItem.value?.productId;

    if (typeof productId !== 'number') {
        return;
    }

    reviewForm.post(`/products/${productId}/reviews`, {
        preserveScroll: true,
        onSuccess: () => {
            reviewDialogOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Order ${order.orderNumber} — XHopy`" />

    <div class="mx-auto max-w-5xl space-y-8 px-4 py-6">
        <div
            class="flex flex-wrap items-center justify-between gap-4 border-b border-border pb-6"
        >
            <div class="space-y-1">
                <Link
                    :href="ordersIndex.url()"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                >
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    <span>Back to My Orders</span>
                </Link>
                <Heading
                    :title="`Order ${order.orderNumber}`"
                    :description="`Placed on ${order.placedAtFormatted} (${order.placedAtDiff})`"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Badge
                    :variant="
                        order.status === 'cancelled' ? 'outline' : 'default'
                    "
                    class="px-3 py-1 text-xs font-semibold"
                >
                    {{ order.statusLabel }}
                </Badge>
                <Badge variant="secondary" class="px-3 py-1 text-xs">
                    Payment: {{ order.paymentStatusLabel }}
                </Badge>

                <Button
                    v-if="order.canCancel"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-9 gap-1.5 rounded-xl text-xs text-destructive hover:text-destructive"
                    data-test="cancel-order-button"
                    @click="handleCancelOrder"
                >
                    <XCircle class="size-3.5" aria-hidden="true" />
                    <span>Cancel Order</span>
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- Left Column: Line Item Snapshots & Status Timeline -->
            <div class="space-y-6 lg:col-span-8">
                <!-- Post-Delivery Review Request Prompt Banner -->
                <section
                    v-if="
                        order.status === 'delivered' &&
                        unreviewedDeliveredItems.length > 0
                    "
                    aria-labelledby="post-delivery-review-prompt-heading"
                    class="flex flex-col justify-between gap-4 rounded-2xl border border-amber-500/40 bg-amber-500/10 p-5 sm:flex-row sm:items-center"
                >
                    <div class="space-y-1">
                        <div
                            class="inline-flex items-center gap-1.5 text-xs font-semibold tracking-wider text-amber-500 uppercase"
                        >
                            <Star
                                class="size-3.5 fill-amber-500 text-amber-500"
                                aria-hidden="true"
                            />
                            <span>Order Delivered — Share Your Feedback</span>
                        </div>
                        <h2
                            id="post-delivery-review-prompt-heading"
                            class="text-base font-bold text-foreground"
                        >
                            How was your delivered purchase?
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Your package has been delivered. Rate
                            <strong class="text-foreground">{{
                                unreviewedDeliveredItems[0]?.productName
                            }}</strong>
                            <template
                                v-if="unreviewedDeliveredItems.length > 1"
                            >
                                and
                                {{ unreviewedDeliveredItems.length - 1 }} other
                                delivered
                                {{
                                    unreviewedDeliveredItems.length - 1 === 1
                                        ? 'item'
                                        : 'items'
                                }}
                            </template>
                            to help other shoppers.
                        </p>
                    </div>

                    <Button
                        type="button"
                        class="h-10 shrink-0 gap-1.5 rounded-xl text-xs font-semibold"
                        data-test="prompt-rate-delivered-button"
                        @click="
                            unreviewedDeliveredItems[0] &&
                            openReviewDialog(unreviewedDeliveredItems[0])
                        "
                    >
                        <Star
                            class="size-3.5 fill-current"
                            aria-hidden="true"
                        />
                        <span>Rate Delivered Item Now</span>
                    </Button>
                </section>

                <section
                    aria-labelledby="order-items-heading"
                    class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-2xs"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2
                            id="order-items-heading"
                            class="text-base font-bold text-foreground"
                        >
                            Purchased Items ({{ order.itemCount }})
                        </h2>
                        <span
                            v-if="order.status === 'delivered'"
                            class="text-xs font-medium text-emerald-500"
                        >
                            Delivered — eligible for verified rating &amp;
                            feedback
                        </span>
                        <span
                            v-else-if="order.status !== 'cancelled'"
                            class="text-xs text-muted-foreground"
                        >
                            Rating &amp; feedback unlock after successful
                            delivery
                        </span>
                    </div>

                    <ul class="divide-y divide-border/70">
                        <li
                            v-for="item in order.items"
                            :key="item.id"
                            class="space-y-3 py-4 text-sm"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-4"
                            >
                                <div class="space-y-0.5">
                                    <Link
                                        v-if="item.productSlug"
                                        :href="
                                            showProduct.url(item.productSlug)
                                        "
                                        class="font-semibold text-foreground hover:text-primary hover:underline"
                                    >
                                        {{ item.productName }}
                                    </Link>
                                    <p
                                        v-else
                                        class="font-semibold text-foreground"
                                    >
                                        {{ item.productName }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        SKU: {{ item.sku }} · Qty
                                        {{ item.quantity }} ×
                                        {{ item.unitPriceFormatted }}
                                    </p>
                                    <div
                                        v-if="
                                            item.selectedColor ||
                                            item.selectedSize
                                        "
                                        class="flex flex-wrap gap-1.5 pt-1"
                                    >
                                        <span
                                            v-if="item.selectedColor"
                                            class="rounded-md border border-border bg-secondary/60 px-2 py-0.5 text-[11px] font-medium text-foreground"
                                        >
                                            Color: {{ item.selectedColor }}
                                        </span>
                                        <span
                                            v-if="item.selectedSize"
                                            class="rounded-md border border-border bg-secondary/60 px-2 py-0.5 text-[11px] font-medium text-foreground"
                                        >
                                            Size: {{ item.selectedSize }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="font-bold text-foreground">
                                        {{ item.lineTotalFormatted }}
                                    </span>

                                    <Button
                                        v-if="item.canReview"
                                        type="button"
                                        :variant="
                                            item.review ? 'outline' : 'default'
                                        "
                                        size="sm"
                                        class="h-8 gap-1.5 rounded-xl text-xs font-semibold"
                                        :data-test="`review-item-${item.productId}`"
                                        @click="openReviewDialog(item)"
                                    >
                                        <Star
                                            :class="[
                                                'size-3.5',
                                                item.review &&
                                                    'fill-amber-500 text-amber-500',
                                            ]"
                                            aria-hidden="true"
                                        />
                                        <span>
                                            {{
                                                item.review
                                                    ? 'Edit Feedback'
                                                    : 'Rate & Review'
                                            }}
                                        </span>
                                    </Button>
                                </div>
                            </div>

                            <!-- Existing customer feedback preview -->
                            <div
                                v-if="item.review"
                                class="rounded-xl border border-border/70 bg-secondary/30 p-3 text-xs"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="inline-flex items-center gap-0.5 text-amber-500"
                                            :aria-label="`Your rating: ${item.review.rating} out of 5 stars`"
                                        >
                                            <Star
                                                v-for="star in 5"
                                                :key="star"
                                                :class="[
                                                    'size-3.5',
                                                    star <= item.review.rating
                                                        ? 'fill-amber-500 text-amber-500'
                                                        : 'text-muted-foreground/30',
                                                ]"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <span
                                            class="font-semibold text-foreground"
                                        >
                                            {{ item.review.rating }}/5
                                        </span>
                                        <span
                                            v-if="item.review.title"
                                            class="font-semibold text-foreground"
                                        >
                                            — {{ item.review.title }}
                                        </span>
                                    </div>

                                    <span
                                        class="inline-flex items-center gap-1 text-[11px] text-emerald-500"
                                    >
                                        <CheckCircle2
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                        <span
                                            >Verified Review ({{
                                                item.review.updatedAtDiff
                                            }})</span
                                        >
                                    </span>
                                </div>

                                <p
                                    v-if="item.review.comment"
                                    class="mt-1.5 leading-relaxed text-muted-foreground"
                                >
                                    {{ item.review.comment }}
                                </p>
                            </div>
                        </li>
                    </ul>

                    <dl class="space-y-2 border-t border-border pt-4 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-muted-foreground">Subtotal</dt>
                            <dd class="font-semibold text-foreground">
                                {{ order.subtotalFormatted }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted-foreground">Discount</dt>
                            <dd class="font-semibold text-foreground">
                                {{ order.discountFormatted }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-muted-foreground">Shipping</dt>
                            <dd class="font-semibold text-foreground">
                                {{ order.shippingFormatted }}
                            </dd>
                        </div>
                        <div
                            class="flex items-center justify-between border-t border-border pt-3 text-base font-bold text-foreground"
                        >
                            <dt>Total</dt>
                            <dd>{{ order.totalFormatted }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- Status History Timeline -->
                <section
                    aria-labelledby="order-timeline-heading"
                    class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-2xs"
                >
                    <div class="flex items-center gap-2">
                        <Clock class="size-4 text-primary" aria-hidden="true" />
                        <h2
                            id="order-timeline-heading"
                            class="text-base font-bold text-foreground"
                        >
                            Order Status Timeline
                        </h2>
                    </div>

                    <ul class="space-y-3">
                        <li
                            v-for="entry in order.timeline"
                            :key="entry.id"
                            class="flex items-start justify-between gap-3 rounded-xl border border-border/70 bg-secondary/25 px-4 py-3 text-sm"
                        >
                            <div>
                                <p class="font-semibold text-foreground">
                                    {{ entry.toStatusLabel }}
                                </p>
                                <p
                                    v-if="entry.note"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ entry.note }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                {{ entry.occurredAtDiff }}
                            </span>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- Right Column: Shipping Address Snapshot & Payment Verification -->
            <div class="space-y-6 lg:col-span-4">
                <section
                    aria-labelledby="order-shipping-heading"
                    class="space-y-3 rounded-2xl border border-border bg-card p-5 shadow-2xs"
                >
                    <div class="flex items-center gap-2">
                        <MapPin
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        <h2
                            id="order-shipping-heading"
                            class="text-sm font-bold text-foreground"
                        >
                            Shipping Address Snapshot
                        </h2>
                    </div>

                    <div class="space-y-1 text-sm">
                        <p class="font-semibold text-foreground">
                            {{ order.shippingAddress.recipientName }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ order.shippingAddress.phone }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ order.shippingAddress.formattedSummary }}
                        </p>
                        <p
                            v-if="order.shippingAddress.notes"
                            class="pt-1 text-xs text-muted-foreground italic"
                        >
                            Note: {{ order.shippingAddress.notes }}
                        </p>
                    </div>
                </section>

                <section
                    aria-labelledby="order-payment-heading"
                    class="space-y-3 rounded-2xl border border-border bg-card p-5 shadow-2xs"
                >
                    <div class="flex items-center gap-2">
                        <CreditCard
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        <h2
                            id="order-payment-heading"
                            class="text-sm font-bold text-foreground"
                        >
                            Payment Verification
                        </h2>
                    </div>

                    <div class="space-y-2 text-xs text-muted-foreground">
                        <p>
                            Method:
                            <strong class="text-foreground">{{
                                order.paymentMethod === 'cash_on_delivery'
                                    ? 'Cash on Delivery (COD)'
                                    : 'PayMongo Online Checkout'
                            }}</strong>
                        </p>
                        <p v-if="order.payment?.providerReference">
                            Reference:
                            <code
                                class="rounded bg-secondary px-1.5 py-0.5 text-[11px] text-foreground"
                            >
                                {{ order.payment.providerReference }}
                            </code>
                        </p>
                    </div>

                    <div
                        class="flex items-center gap-2 rounded-xl bg-secondary/50 p-3 text-xs text-muted-foreground"
                    >
                        <ShieldCheck
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span>
                            Online payments are marked paid only after
                            cryptographic webhook signature verification.
                        </span>
                    </div>
                </section>
            </div>
        </div>

        <!-- Rating & Feedback Dialog -->
        <Dialog v-model:open="reviewDialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <MessageSquarePlus
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        <span>Rate &amp; Review Purchased Item</span>
                    </DialogTitle>
                    <DialogDescription v-if="activeReviewItem">
                        Share your verified customer experience for
                        <strong class="text-foreground">{{
                            activeReviewItem.productName
                        }}</strong
                        >.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4 py-2" @submit.prevent="submitReview">
                    <div class="space-y-2">
                        <Label>Overall Rating</Label>
                        <div class="flex items-center gap-2">
                            <div
                                class="flex items-center gap-1"
                                role="radiogroup"
                                aria-label="Select a star rating from 1 to 5"
                            >
                                <button
                                    v-for="star in 5"
                                    :key="star"
                                    type="button"
                                    class="inline-flex size-10 items-center justify-center rounded-xl border border-border/70 bg-secondary/30 transition-colors hover:border-amber-500/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    :aria-label="`Rate ${star} out of 5 stars`"
                                    :aria-checked="reviewForm.rating === star"
                                    role="radio"
                                    @click="reviewForm.rating = star"
                                >
                                    <Star
                                        :class="[
                                            'size-5 transition-transform',
                                            star <= reviewForm.rating
                                                ? 'scale-105 fill-amber-500 text-amber-500'
                                                : 'text-muted-foreground/40',
                                        ]"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                            <span
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                {{ reviewForm.rating }}/5 —
                                {{ starLabels[reviewForm.rating] }}
                            </span>
                        </div>
                        <InputError :message="reviewForm.errors.rating" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="order-review-title"
                            >Headline (Optional)</Label
                        >
                        <Input
                            id="order-review-title"
                            v-model="reviewForm.title"
                            maxlength="120"
                            placeholder="Summarize your experience in a few words"
                        />
                        <InputError :message="reviewForm.errors.title" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="order-review-comment"
                            >Your Feedback (Optional)</Label
                        >
                        <textarea
                            id="order-review-comment"
                            v-model="reviewForm.comment"
                            rows="4"
                            maxlength="1500"
                            placeholder="What did you like about the build quality, performance, or value?"
                            class="flex min-h-24 w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-2xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <InputError :message="reviewForm.errors.comment" />
                    </div>

                    <DialogFooter class="gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 rounded-xl"
                            @click="reviewDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            class="h-10 rounded-xl"
                            :disabled="reviewForm.processing"
                        >
                            {{
                                reviewForm.processing
                                    ? 'Saving...'
                                    : 'Submit Verified Review'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
