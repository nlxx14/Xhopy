<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Star, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { CustomerAddress } from '@modules/account/types';
import EmptyState from '@shared/components/EmptyState.vue';
import Heading from '@shared/components/Heading.vue';
import InputError from '@shared/components/InputError.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import {
    CustomDropdown,
    type CustomDropdownOption,
} from '@shared/components/ui/custom-dropdown';
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
import { RadioGroup, RadioGroupItem } from '@shared/components/ui/radio-group';
import {
    defaultMethod as setDefaultRoute,
    destroy as destroyAddressRoute,
    index as addressesIndex,
    store as storeAddressRoute,
    update as updateAddressRoute,
} from '@/routes/addresses';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Address book',
                href: addressesIndex(),
            },
        ],
    },
});

const props = withDefaults(
    defineProps<{
        addresses: CustomerAddress[];
        maxAddresses?: number;
        allowedLabels?: string[];
    }>(),
    {
        maxAddresses: 10,
        allowedLabels: () => ['Home', 'Office', 'Other'],
    },
);

const countryOptions: CustomDropdownOption[] = [
    {
        value: 'PH',
        label: 'Philippines (PH)',
    },
];

const dialogOpen = ref(false);
const editingAddress = ref<CustomerAddress | null>(null);

const form = useForm({
    label: 'Home',
    recipient_name: '',
    phone: '',
    line_1: '',
    line_2: '',
    barangay: '',
    city: '',
    province: '',
    postal_code: '',
    country_code: 'PH',
    delivery_notes: '',
    is_default_shipping: false,
    is_default_billing: false,
});

const canAddMore = computed(() => props.addresses.length < props.maxAddresses);

function openCreateDialog(): void {
    editingAddress.value = null;
    form.reset();
    form.clearErrors();
    form.label = 'Home';
    form.country_code = 'PH';
    form.is_default_shipping = props.addresses.length === 0;
    form.is_default_billing = props.addresses.length === 0;
    dialogOpen.value = true;
}

function openEditDialog(address: CustomerAddress): void {
    editingAddress.value = address;
    form.clearErrors();
    form.label = address.label;
    form.recipient_name = address.recipientName;
    form.phone = address.phone;
    form.line_1 = address.line1;
    form.line_2 = address.line2 ?? '';
    form.barangay = address.barangay ?? '';
    form.city = address.city;
    form.province = address.province;
    form.postal_code = address.postalCode;
    form.country_code = address.countryCode;
    form.delivery_notes = address.deliveryNotes ?? '';
    form.is_default_shipping = address.isDefaultShipping;
    form.is_default_billing = address.isDefaultBilling;
    dialogOpen.value = true;
}

function submitForm(): void {
    const target = editingAddress.value;

    if (target) {
        form.put(updateAddressRoute.url(target.id), {
            preserveScroll: true,
            onSuccess: () => {
                dialogOpen.value = false;
            },
        });
    } else {
        form.post(storeAddressRoute.url(), {
            preserveScroll: true,
            onSuccess: () => {
                dialogOpen.value = false;
                form.reset();
            },
        });
    }
}

function handleSetDefault(
    address: CustomerAddress,
    type: 'shipping' | 'billing' | 'both',
): void {
    router.patch(
        setDefaultRoute.url(address.id),
        { type },
        { preserveScroll: true },
    );
}

function handleDelete(address: CustomerAddress): void {
    router.delete(destroyAddressRoute.url(address.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Address book" />

    <h1 class="sr-only">Address book</h1>

    <div class="space-y-6">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <Heading
                variant="small"
                title="Saved addresses"
                description="Manage your shipping and billing addresses for faster checkout"
            />

            <Button
                type="button"
                class="h-11 gap-2 rounded-xl"
                :disabled="!canAddMore"
                data-test="add-address-button"
                @click="openCreateDialog"
            >
                <Plus class="size-4" aria-hidden="true" />
                <span>Add address</span>
            </Button>
        </div>

        <div v-if="addresses.length > 0" class="grid grid-cols-1 gap-4">
            <article
                v-for="address in addresses"
                :key="address.id"
                class="flex flex-col justify-between gap-4 rounded-2xl border border-border bg-card p-5 shadow-2xs"
            >
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            variant="secondary"
                            class="px-2.5 py-0.5 text-xs font-semibold"
                        >
                            {{ address.label }}
                        </Badge>
                        <Badge
                            v-if="address.isDefaultShipping"
                            class="bg-primary text-xs text-primary-foreground"
                        >
                            Default Shipping
                        </Badge>
                        <Badge
                            v-if="address.isDefaultBilling"
                            variant="outline"
                            class="border-primary/45 bg-brand-subtle/40 text-xs font-semibold text-brand-foreground"
                        >
                            Default Billing
                        </Badge>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-foreground">
                            {{ address.recipientName }}
                            <span
                                class="ml-2 font-normal text-muted-foreground"
                            >
                                {{ address.phone }}
                            </span>
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ address.formattedSummary }}
                        </p>
                        <p
                            v-if="address.deliveryNotes"
                            class="mt-1 text-xs text-muted-foreground italic"
                        >
                            Note: {{ address.deliveryNotes }}
                        </p>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-t border-border/70 pt-3"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="!address.isDefaultShipping"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-9 gap-1.5 text-xs"
                            @click="handleSetDefault(address, 'shipping')"
                        >
                            <Star class="size-3.5" aria-hidden="true" />
                            <span>Set default shipping</span>
                        </Button>
                        <Button
                            v-if="!address.isDefaultBilling"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-9 gap-1.5 text-xs"
                            @click="handleSetDefault(address, 'billing')"
                        >
                            <Star class="size-3.5" aria-hidden="true" />
                            <span>Set default billing</span>
                        </Button>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-9 gap-1.5 text-xs"
                            @click="openEditDialog(address)"
                        >
                            <Pencil class="size-3.5" aria-hidden="true" />
                            <span>Edit</span>
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-9 gap-1.5 text-xs text-destructive hover:text-destructive"
                            @click="handleDelete(address)"
                        >
                            <Trash2 class="size-3.5" aria-hidden="true" />
                            <span>Remove</span>
                        </Button>
                    </div>
                </div>
            </article>
        </div>

        <EmptyState
            v-else
            :icon="MapPin"
            title="No saved addresses yet"
            description="Add a shipping and billing address so it is ready when you check out."
            action-label="Add your first address"
            @action="openCreateDialog"
        />

        <!-- Add / Edit Address Dialog -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            editingAddress ? 'Edit address' : 'Add new address'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Enter a verified recipient and Philippine delivery
                        address.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitForm">
                    <fieldset class="w-full min-w-0 space-y-2">
                        <legend class="text-sm font-medium text-foreground">
                            Address label
                        </legend>
                        <RadioGroup
                            v-model="form.label"
                            aria-label="Address label"
                            class="grid grid-cols-3 gap-2"
                        >
                            <label
                                v-for="option in allowedLabels"
                                :key="option"
                                :for="`address-label-${option}`"
                                :class="[
                                    'flex min-h-11 cursor-pointer items-center gap-2.5 rounded-xl border px-3 py-2 text-sm transition-all',
                                    form.label === option
                                        ? 'border-primary/45 bg-brand-subtle/45 font-semibold text-foreground shadow-2xs'
                                        : 'border-border/70 bg-secondary/25 hover:bg-secondary/60',
                                ]"
                            >
                                <RadioGroupItem
                                    :id="`address-label-${option}`"
                                    :value="option"
                                />
                                <span>{{ option }}</span>
                            </label>
                        </RadioGroup>
                        <InputError :message="form.errors.label" />
                    </fieldset>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="recipient_name">Recipient name</Label>
                            <Input
                                id="recipient_name"
                                v-model="form.recipient_name"
                                required
                                autocomplete="name"
                                placeholder="Juan Dela Cruz"
                            />
                            <InputError :message="form.errors.recipient_name" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="address_phone">Phone number</Label>
                            <Input
                                id="address_phone"
                                v-model="form.phone"
                                type="tel"
                                required
                                autocomplete="tel"
                                placeholder="+63 917 555 0199"
                            />
                            <InputError :message="form.errors.phone" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="line_1">Street address</Label>
                        <Input
                            id="line_1"
                            v-model="form.line_1"
                            required
                            autocomplete="address-line1"
                            placeholder="House/Unit No., Building, Street"
                        />
                        <InputError :message="form.errors.line_1" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="line_2">
                                Subdivision / Floor (optional)
                            </Label>
                            <Input
                                id="line_2"
                                v-model="form.line_2"
                                autocomplete="address-line2"
                                placeholder="Tower B, 5th Floor"
                            />
                            <InputError :message="form.errors.line_2" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="barangay">Barangay (optional)</Label>
                            <Input
                                id="barangay"
                                v-model="form.barangay"
                                placeholder="San Lorenzo"
                            />
                            <InputError :message="form.errors.barangay" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="city">City / Municipality</Label>
                            <Input
                                id="city"
                                v-model="form.city"
                                required
                                autocomplete="address-level2"
                                placeholder="Makati City"
                            />
                            <InputError :message="form.errors.city" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="province">Province / Region</Label>
                            <Input
                                id="province"
                                v-model="form.province"
                                required
                                autocomplete="address-level1"
                                placeholder="Metro Manila"
                            />
                            <InputError :message="form.errors.province" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="postal_code">Postal code</Label>
                            <Input
                                id="postal_code"
                                v-model="form.postal_code"
                                required
                                autocomplete="postal-code"
                                placeholder="1223"
                            />
                            <InputError :message="form.errors.postal_code" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="country_code">Country</Label>
                            <CustomDropdown
                                id="country_code"
                                v-model="form.country_code"
                                :options="countryOptions"
                                aria-label="Country"
                                class="w-full"
                            />
                            <InputError :message="form.errors.country_code" />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="delivery_notes">
                            Delivery notes (optional)
                        </Label>
                        <Input
                            id="delivery_notes"
                            v-model="form.delivery_notes"
                            placeholder="Landmark or lobby drop-off instructions"
                        />
                        <InputError :message="form.errors.delivery_notes" />
                    </div>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <label
                            for="is_default_shipping"
                            class="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-xl border border-border/70 bg-secondary/25 px-3 py-2 text-xs font-medium"
                        >
                            <Checkbox
                                id="is_default_shipping"
                                :model-value="form.is_default_shipping"
                                @update:model-value="
                                    form.is_default_shipping = $event === true
                                "
                            />
                            <span>Default shipping address</span>
                        </label>

                        <label
                            for="is_default_billing"
                            class="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-xl border border-border/70 bg-secondary/25 px-3 py-2 text-xs font-medium"
                        >
                            <Checkbox
                                id="is_default_billing"
                                :model-value="form.is_default_billing"
                                @update:model-value="
                                    form.is_default_billing = $event === true
                                "
                            />
                            <span>Default billing address</span>
                        </label>
                    </div>

                    <DialogFooter class="gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="h-11"
                            @click="dialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            class="h-11"
                            :disabled="form.processing"
                            data-test="save-address-button"
                        >
                            {{
                                editingAddress
                                    ? 'Update address'
                                    : 'Save address'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
