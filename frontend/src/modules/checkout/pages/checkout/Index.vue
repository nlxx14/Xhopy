<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CreditCard, Lock, MapPin, ShieldCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { CustomerAddress } from '@modules/account';
import type { CartDetail, CartSummary } from '@modules/cart';
import type { PaymentMethodOption } from '@modules/checkout/types';
import {
    StorefrontLayout,
    type AnnouncementBanner,
    type CategorySummary,
} from '@modules/storefront';
import Breadcrumbs from '@shared/components/Breadcrumbs.vue';
import InputError from '@shared/components/InputError.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@shared/components/ui/radio-group';
import { Spinner } from '@shared/components/ui/spinner';
import type { BreadcrumbItem } from '@shared/types';
import { home } from '@/routes';
import { index as addressesIndex } from '@/routes/addresses';
import { index as cartIndex } from '@/routes/cart';
import {
    index as checkoutIndex,
    store as placeOrderRoute,
} from '@/routes/checkout';
import { index as productsIndex } from '@/routes/products';

const NEW_ADDRESS_OPTION = '__new__';

const props = withDefaults(
    defineProps<{
        announcement?: AnnouncementBanner | null;
        categories: CategorySummary[];
        cartSummary: CartSummary;
        cart: CartDetail;
        savedAddresses: CustomerAddress[];
        idempotencyKey: string;
        paymentMethods: PaymentMethodOption[];
    }>(),
    {
        announcement: null,
    },
);

const defaultSaved =
    props.savedAddresses.find((a) => a.isDefaultShipping) ??
    props.savedAddresses[0] ??
    null;

const selectedAddressChoice = ref<string>(
    defaultSaved ? String(defaultSaved.id) : NEW_ADDRESS_OPTION,
);

const form = useForm({
    idempotency_key: props.idempotencyKey,
    payment_method: props.paymentMethods[0]?.value ?? 'paymongo_checkout',
    address_id: defaultSaved ? defaultSaved.id : (null as number | null),
    recipient_name: '',
    phone: '',
    line_1: '',
    line_2: '',
    barangay: '',
    city: '',
    province: '',
    postal_code: '',
    country_code: 'PH',
    shipping_notes: '',
});

const isUsingNewAddress = computed(
    () => selectedAddressChoice.value === NEW_ADDRESS_OPTION,
);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Home', href: home.url() },
    { title: 'Shopping Cart', href: cartIndex.url() },
    { title: 'Checkout', href: checkoutIndex.url() },
]);

function handleAddressChoiceChange(next: unknown): void {
    const value =
        typeof next === 'string' && next !== '' ? next : NEW_ADDRESS_OPTION;
    selectedAddressChoice.value = value;
    form.address_id = value === NEW_ADDRESS_OPTION ? null : Number(value);
}

function handleSearch(query: string): void {
    router.get(
        productsIndex.url({
            query: { q: query || undefined },
        }),
    );
}

function handleSelectCategory(slug: string | null): void {
    router.get(
        productsIndex.url({
            query: { category: slug || undefined },
        }),
    );
}

function submitOrder(): void {
    form.address_id = isUsingNewAddress.value
        ? null
        : Number(selectedAddressChoice.value);

    form.post(placeOrderRoute.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Checkout — XHopy" />

    <StorefrontLayout
        :announcement="announcement"
        :categories="categories"
        :cart="cartSummary"
        @search="handleSearch"
        @select-category="handleSelectCategory"
        @open-cart="() => router.get(cartIndex.url())"
    >
        <div
            class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 sm:py-10 lg:px-8"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-6"
            >
                <div class="space-y-2">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                    <h1
                        class="text-2xl font-extrabold tracking-tight text-foreground sm:text-3xl"
                    >
                        Secure Checkout
                    </h1>
                </div>

                <Link
                    :href="cartIndex.url()"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                >
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    <span>Return to cart</span>
                </Link>
            </div>

            <form
                class="grid grid-cols-1 gap-8 lg:grid-cols-12"
                @submit.prevent="submitOrder"
            >
                <div class="space-y-8 lg:col-span-7">
                    <!-- 1. Shipping Address Section -->
                    <section
                        aria-labelledby="checkout-shipping-heading"
                        class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-2xs"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <MapPin
                                    class="size-5 text-primary"
                                    aria-hidden="true"
                                />
                                <h2
                                    id="checkout-shipping-heading"
                                    class="text-lg font-bold text-foreground"
                                >
                                    1. Shipping Address
                                </h2>
                            </div>

                            <Link
                                :href="addressesIndex.url()"
                                class="text-xs font-semibold text-primary hover:underline"
                            >
                                Manage address book
                            </Link>
                        </div>

                        <RadioGroup
                            v-if="savedAddresses.length > 0"
                            :model-value="selectedAddressChoice"
                            aria-label="Choose a shipping address"
                            class="space-y-2"
                            @update:model-value="handleAddressChoiceChange"
                        >
                            <label
                                v-for="address in savedAddresses"
                                :key="address.id"
                                :for="`checkout-address-${address.id}`"
                                :class="[
                                    'flex cursor-pointer items-start gap-3 rounded-xl border p-4 text-sm transition-all',
                                    selectedAddressChoice === String(address.id)
                                        ? 'border-primary/50 bg-brand-subtle/40 shadow-2xs'
                                        : 'border-border/70 hover:bg-secondary/50',
                                ]"
                            >
                                <RadioGroupItem
                                    :id="`checkout-address-${address.id}`"
                                    :value="String(address.id)"
                                    class="mt-0.5"
                                />
                                <div class="min-w-0 flex-1 space-y-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="font-semibold text-foreground"
                                        >
                                            {{ address.recipientName }}
                                        </span>
                                        <Badge
                                            variant="secondary"
                                            class="text-[11px]"
                                        >
                                            {{ address.label }}
                                        </Badge>
                                        <Badge
                                            v-if="address.isDefaultShipping"
                                            class="bg-primary text-[11px] text-primary-foreground"
                                        >
                                            Default
                                        </Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        {{ address.phone }} ·
                                        {{ address.formattedSummary }}
                                    </p>
                                </div>
                            </label>

                            <label
                                for="checkout-address-new"
                                :class="[
                                    'flex cursor-pointer items-center gap-3 rounded-xl border p-4 text-sm transition-all',
                                    isUsingNewAddress
                                        ? 'border-primary/50 bg-brand-subtle/40 font-semibold shadow-2xs'
                                        : 'border-border/70 hover:bg-secondary/50',
                                ]"
                            >
                                <RadioGroupItem
                                    id="checkout-address-new"
                                    :value="NEW_ADDRESS_OPTION"
                                />
                                <span>Ship to a different address</span>
                            </label>
                        </RadioGroup>

                        <!-- Inline Address Fields (when no saved addresses exist or "different address" selected) -->
                        <div
                            v-if="isUsingNewAddress"
                            class="space-y-4 border-t border-border/70 pt-4"
                        >
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="grid gap-1.5">
                                    <Label for="checkout_recipient_name">
                                        Recipient name
                                    </Label>
                                    <Input
                                        id="checkout_recipient_name"
                                        v-model="form.recipient_name"
                                        required
                                        placeholder="Juan Dela Cruz"
                                    />
                                    <InputError
                                        :message="form.errors.recipient_name"
                                    />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label for="checkout_phone">
                                        Phone number
                                    </Label>
                                    <Input
                                        id="checkout_phone"
                                        v-model="form.phone"
                                        type="tel"
                                        required
                                        placeholder="+63 917 555 0199"
                                    />
                                    <InputError :message="form.errors.phone" />
                                </div>
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="checkout_line_1">
                                    Street address
                                </Label>
                                <Input
                                    id="checkout_line_1"
                                    v-model="form.line_1"
                                    required
                                    placeholder="House/Unit No., Building, Street"
                                />
                                <InputError :message="form.errors.line_1" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="grid gap-1.5">
                                    <Label for="checkout_line_2">
                                        Subdivision / Floor (optional)
                                    </Label>
                                    <Input
                                        id="checkout_line_2"
                                        v-model="form.line_2"
                                        placeholder="Tower B, 5th Floor"
                                    />
                                    <InputError :message="form.errors.line_2" />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label for="checkout_barangay">
                                        Barangay (optional)
                                    </Label>
                                    <Input
                                        id="checkout_barangay"
                                        v-model="form.barangay"
                                        placeholder="San Lorenzo"
                                    />
                                    <InputError
                                        :message="form.errors.barangay"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div class="grid gap-1.5">
                                    <Label for="checkout_city">City</Label>
                                    <Input
                                        id="checkout_city"
                                        v-model="form.city"
                                        required
                                        placeholder="Makati City"
                                    />
                                    <InputError :message="form.errors.city" />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label for="checkout_province">
                                        Province
                                    </Label>
                                    <Input
                                        id="checkout_province"
                                        v-model="form.province"
                                        required
                                        placeholder="Metro Manila"
                                    />
                                    <InputError
                                        :message="form.errors.province"
                                    />
                                </div>

                                <div class="grid gap-1.5">
                                    <Label for="checkout_postal_code">
                                        Postal code
                                    </Label>
                                    <Input
                                        id="checkout_postal_code"
                                        v-model="form.postal_code"
                                        required
                                        placeholder="1223"
                                    />
                                    <InputError
                                        :message="form.errors.postal_code"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-1.5 pt-2">
                            <Label for="checkout_shipping_notes">
                                Delivery instructions (optional)
                            </Label>
                            <Input
                                id="checkout_shipping_notes"
                                v-model="form.shipping_notes"
                                placeholder="Gate code, landmark, or lobby drop-off instructions"
                            />
                            <InputError :message="form.errors.shipping_notes" />
                        </div>
                    </section>

                    <!-- 2. Payment Method Section -->
                    <section
                        aria-labelledby="checkout-payment-heading"
                        class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-2xs"
                    >
                        <div class="flex items-center gap-2.5">
                            <CreditCard
                                class="size-5 text-primary"
                                aria-hidden="true"
                            />
                            <h2
                                id="checkout-payment-heading"
                                class="text-lg font-bold text-foreground"
                            >
                                2. Payment Method
                            </h2>
                        </div>

                        <RadioGroup
                            v-model="form.payment_method"
                            aria-label="Choose a payment method"
                            class="space-y-2"
                        >
                            <label
                                v-for="method in paymentMethods"
                                :key="method.value"
                                :for="`payment-method-${method.value}`"
                                :class="[
                                    'flex cursor-pointer items-start gap-3 rounded-xl border p-4 text-sm transition-all',
                                    form.payment_method === method.value
                                        ? 'border-primary/50 bg-brand-subtle/40 shadow-2xs'
                                        : 'border-border/70 hover:bg-secondary/50',
                                ]"
                            >
                                <RadioGroupItem
                                    :id="`payment-method-${method.value}`"
                                    :value="method.value"
                                    class="mt-0.5"
                                />
                                <div class="space-y-1">
                                    <p class="font-semibold text-foreground">
                                        {{ method.label }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ method.description }}
                                    </p>
                                </div>
                            </label>
                        </RadioGroup>

                        <InputError :message="form.errors.payment_method" />
                    </section>
                </div>

                <!-- Order Summary Column -->
                <aside class="lg:col-span-5">
                    <div
                        class="sticky top-28 space-y-5 rounded-2xl border border-border bg-card p-6 shadow-2xs"
                    >
                        <h2
                            class="text-lg font-bold tracking-tight text-foreground"
                        >
                            Order Review ({{ cart.itemCount }}
                            {{ cart.itemCount === 1 ? 'item' : 'items' }})
                        </h2>

                        <ul
                            class="divide-y divide-border/70 border-y border-border py-2"
                        >
                            <li
                                v-for="item in cart.items"
                                :key="item.id"
                                class="flex items-center justify-between gap-3 py-3 text-sm"
                            >
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate font-semibold text-foreground"
                                    >
                                        {{ item.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Qty {{ item.quantity }} ×
                                        {{ item.unitPriceFormatted }}
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 font-semibold text-foreground"
                                >
                                    {{ item.lineTotalFormatted }}
                                </span>
                            </li>
                        </ul>

                        <dl class="space-y-2.5 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-muted-foreground">Subtotal</dt>
                                <dd class="font-semibold text-foreground">
                                    {{ cart.subtotalFormatted }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-muted-foreground">Shipping</dt>
                                <dd class="font-semibold text-foreground">
                                    ₱0.00
                                </dd>
                            </div>
                            <div
                                class="flex items-center justify-between border-t border-border pt-3 text-base font-bold text-foreground"
                            >
                                <dt>Order Total</dt>
                                <dd>{{ cart.subtotalFormatted }}</dd>
                            </div>
                        </dl>

                        <Button
                            type="submit"
                            size="lg"
                            class="h-12 w-full gap-2 rounded-xl text-sm font-semibold"
                            :disabled="
                                form.processing || cart.hasUnavailableItems
                            "
                            data-test="place-order-button"
                        >
                            <Spinner v-if="form.processing" />
                            <Lock v-else class="size-4" aria-hidden="true" />
                            <span>
                                Place Order ({{ cart.subtotalFormatted }})
                            </span>
                        </Button>

                        <div
                            class="flex items-center gap-2 rounded-xl bg-secondary/50 p-3 text-xs text-muted-foreground"
                        >
                            <ShieldCheck
                                class="size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span>
                                Stock is atomically reserved and prices are
                                snapshot-locked on the server when your order is
                                placed.
                            </span>
                        </div>
                    </div>
                </aside>
            </form>
        </div>
    </StorefrontLayout>
</template>
