<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    FolderPlus,
    PackagePlus,
    Pencil,
    RefreshCw,
    Upload,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { AdminCategoryRow, AdminProductRow } from '@modules/admin/types';
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
import { dashboard as adminDashboard } from '@/routes/admin';
import { store as storeCategoryRoute } from '@/routes/admin/categories';
import { reconcile as reconcileInventoryRoute } from '@/routes/admin/inventory';
import {
    bulkStatus as bulkStatusRoute,
    importMethod as importProductsRoute,
    index as adminProductsIndex,
    stock as adjustStockRoute,
    store as storeProductRoute,
    update as updateProductRoute,
} from '@/routes/admin/products';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Back-Office', href: adminDashboard() },
            { title: 'Catalog & Inventory', href: adminProductsIndex() },
        ],
    },
});

const props = defineProps<{
    products: AdminProductRow[];
    categories: AdminCategoryRow[];
    canRunAdminOnlyActions: boolean;
}>();

const selectedIds = ref<number[]>([]);
const bulkTargetStatus = ref<'active' | 'draft' | 'archived'>('active');

const productDialogOpen = ref(false);
const editingProduct = ref<AdminProductRow | null>(null);

const categoryDialogOpen = ref(false);
const stockDialogOpen = ref(false);
const stockTargetProduct = ref<AdminProductRow | null>(null);
const importDialogOpen = ref(false);

const categoryOptions = computed<CustomDropdownOption[]>(() =>
    props.categories.map((cat) => ({
        value: String(cat.id),
        label: cat.name,
    })),
);

const statusOptions: CustomDropdownOption[] = [
    { value: 'active', label: 'Active (Published)' },
    { value: 'draft', label: 'Draft' },
    { value: 'archived', label: 'Archived' },
];

const productForm = useForm({
    category_id: props.categories[0]?.id ? String(props.categories[0].id) : '',
    name: '',
    sku: '',
    short_description: '',
    description: '',
    price: '0.00',
    compare_at_price: '',
    stock_quantity: 0,
    status: 'active',
    is_featured: false,
});

const categoryForm = useForm({
    name: '',
    slug: '',
    description: '',
    sort_order: 0,
    is_active: true,
});

const stockForm = useForm({
    quantity_delta: 5,
    type: 'restock',
    reason: '',
});

const importForm = useForm({
    csv_data:
        'sku,name,category_slug,price,stock_quantity,status,short_description\n',
});

function toggleSelection(productId: number, checked: boolean): void {
    if (checked) {
        if (!selectedIds.value.includes(productId)) {
            selectedIds.value = [...selectedIds.value, productId];
        }
    } else {
        selectedIds.value = selectedIds.value.filter((id) => id !== productId);
    }
}

function openCreateProduct(): void {
    editingProduct.value = null;
    productForm.reset();
    productForm.clearErrors();
    productForm.category_id = props.categories[0]?.id
        ? String(props.categories[0].id)
        : '';
    productForm.status = 'active';
    productDialogOpen.value = true;
}

function openEditProduct(product: AdminProductRow): void {
    editingProduct.value = product;
    productForm.clearErrors();
    productForm.category_id = String(product.categoryId);
    productForm.name = product.name;
    productForm.sku = product.sku;
    productForm.short_description = product.shortDescription ?? '';
    productForm.description = product.description ?? '';
    productForm.price = product.price;
    productForm.compare_at_price = product.compareAtPrice ?? '';
    productForm.stock_quantity = product.stockQuantity;
    productForm.status = product.status;
    productForm.is_featured = product.isFeatured;
    productDialogOpen.value = true;
}

function submitProduct(): void {
    const target = editingProduct.value;
    if (target) {
        productForm.put(updateProductRoute.url(target.id), {
            preserveScroll: true,
            onSuccess: () => {
                productDialogOpen.value = false;
            },
        });
    } else {
        productForm.post(storeProductRoute.url(), {
            preserveScroll: true,
            onSuccess: () => {
                productDialogOpen.value = false;
            },
        });
    }
}

function submitCategory(): void {
    categoryForm.post(storeCategoryRoute.url(), {
        preserveScroll: true,
        onSuccess: () => {
            categoryDialogOpen.value = false;
            categoryForm.reset();
        },
    });
}

function openAdjustStock(product: AdminProductRow): void {
    stockTargetProduct.value = product;
    stockForm.reset();
    stockForm.clearErrors();
    stockForm.quantity_delta = 5;
    stockForm.type = 'restock';
    stockForm.reason = 'Scheduled warehouse restock';
    stockDialogOpen.value = true;
}

function submitAdjustStock(): void {
    if (!stockTargetProduct.value) {
        return;
    }

    stockForm.post(adjustStockRoute.url(stockTargetProduct.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            stockDialogOpen.value = false;
        },
    });
}

function handleReconcileInventory(): void {
    router.post(reconcileInventoryRoute.url(), {}, { preserveScroll: true });
}

function handleBulkUpdateStatus(): void {
    if (selectedIds.value.length === 0) {
        return;
    }

    router.post(
        bulkStatusRoute.url(),
        {
            product_ids: selectedIds.value,
            status: bulkTargetStatus.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            },
        },
    );
}

function submitCsvImport(): void {
    importForm.post(importProductsRoute.url(), {
        preserveScroll: true,
        onSuccess: () => {
            importDialogOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="Catalog & Inventory Management — XHopy Admin" />

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-6">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <Heading
                title="Catalog & Inventory"
                description="Manage products, categories, stock movements, ledger reconciliation, and bulk operations"
            />

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 gap-1.5 rounded-xl text-xs font-semibold"
                    @click="handleReconcileInventory"
                >
                    <RefreshCw class="size-3.5" aria-hidden="true" />
                    <span>Reconcile Stock Ledger</span>
                </Button>

                <Button
                    v-if="canRunAdminOnlyActions"
                    type="button"
                    variant="outline"
                    class="h-10 gap-1.5 rounded-xl text-xs font-semibold"
                    @click="importDialogOpen = true"
                >
                    <Upload class="size-3.5" aria-hidden="true" />
                    <span>Import CSV</span>
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    class="h-10 gap-1.5 rounded-xl text-xs font-semibold"
                    @click="categoryDialogOpen = true"
                >
                    <FolderPlus class="size-3.5" aria-hidden="true" />
                    <span>New Category</span>
                </Button>

                <Button
                    type="button"
                    class="h-10 gap-1.5 rounded-xl text-xs font-semibold"
                    @click="openCreateProduct"
                >
                    <PackagePlus class="size-3.5" aria-hidden="true" />
                    <span>New Product</span>
                </Button>
            </div>
        </div>

        <!-- Admin-Only Safe Bulk Status Bar -->
        <div
            v-if="canRunAdminOnlyActions && selectedIds.length > 0"
            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-primary/40 bg-brand-subtle/40 px-4 py-3"
        >
            <span class="text-xs font-semibold text-foreground">
                {{ selectedIds.length }} product(s) selected for bulk operation
            </span>

            <div class="flex flex-wrap items-center gap-2">
                <CustomDropdown
                    v-model="bulkTargetStatus"
                    :options="statusOptions"
                    label="Set status"
                    aria-label="Bulk status target"
                    class="h-9 min-w-[11rem]"
                />
                <Button
                    type="button"
                    size="sm"
                    class="h-9 rounded-xl text-xs font-semibold"
                    @click="handleBulkUpdateStatus"
                >
                    Apply Bulk Status
                </Button>
            </div>
        </div>

        <!-- Products Table -->
        <div
            class="overflow-x-auto rounded-2xl border border-border bg-card shadow-2xs"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-border bg-secondary/40 text-xs font-semibold text-muted-foreground uppercase"
                >
                    <tr>
                        <th
                            v-if="canRunAdminOnlyActions"
                            class="w-10 px-4 py-3"
                        >
                            <span class="sr-only">Select</span>
                        </th>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Price</th>
                        <th class="px-4 py-3">Stock &amp; Ledger</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/70">
                    <tr
                        v-for="product in products"
                        :key="product.id"
                        class="hover:bg-secondary/25"
                    >
                        <td
                            v-if="canRunAdminOnlyActions"
                            class="px-4 py-3 align-middle"
                        >
                            <Checkbox
                                :model-value="selectedIds.includes(product.id)"
                                :aria-label="`Select ${product.name}`"
                                @update:model-value="
                                    toggleSelection(product.id, $event === true)
                                "
                            />
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-semibold text-foreground">
                                {{ product.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                SKU: {{ product.sku }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">
                            {{ product.categoryName }}
                        </td>
                        <td class="px-4 py-3 font-semibold text-foreground">
                            {{ product.priceFormatted }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span
                                    class="font-bold text-foreground tabular-nums"
                                >
                                    {{ product.stockQuantity }}
                                </span>
                                <Badge
                                    :variant="
                                        product.isLedgerBalanced
                                            ? 'secondary'
                                            : 'outline'
                                    "
                                    class="text-[11px]"
                                >
                                    {{
                                        product.isLedgerBalanced
                                            ? 'Ledger balanced'
                                            : `Ledger: ${product.ledgerStockSum}`
                                    }}
                                </Badge>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    product.status === 'active'
                                        ? 'default'
                                        : 'secondary'
                                "
                                class="text-xs capitalize"
                            >
                                {{ product.status }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-1.5">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-8 rounded-lg text-xs"
                                    @click="openAdjustStock(product)"
                                >
                                    Adjust Stock
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 gap-1 rounded-lg text-xs"
                                    @click="openEditProduct(product)"
                                >
                                    <Pencil
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span>Edit</span>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create / Edit Product Dialog -->
        <Dialog v-model:open="productDialogOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingProduct ? 'Edit Product' : 'Create Product' }}
                    </DialogTitle>
                    <DialogDescription>
                        Server-verified product pricing, SKU, and stock details.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitProduct">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="prod_name">Product Name</Label>
                            <Input
                                id="prod_name"
                                v-model="productForm.name"
                                required
                            />
                            <InputError :message="productForm.errors.name" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="prod_sku">SKU</Label>
                            <Input
                                id="prod_sku"
                                v-model="productForm.sku"
                                required
                                placeholder="XH-AUD-010"
                            />
                            <InputError :message="productForm.errors.sku" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="prod_category">Category</Label>
                            <CustomDropdown
                                id="prod_category"
                                v-model="productForm.category_id"
                                :options="categoryOptions"
                                aria-label="Product category"
                                class="w-full"
                            />
                            <InputError
                                :message="productForm.errors.category_id"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="prod_status">Status</Label>
                            <CustomDropdown
                                id="prod_status"
                                v-model="productForm.status"
                                :options="statusOptions"
                                aria-label="Product status"
                                class="w-full"
                            />
                            <InputError :message="productForm.errors.status" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label for="prod_price">Price (PHP)</Label>
                            <Input
                                id="prod_price"
                                v-model="productForm.price"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                            />
                            <InputError :message="productForm.errors.price" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="prod_compare">
                                Compare-at (optional)
                            </Label>
                            <Input
                                id="prod_compare"
                                v-model="productForm.compare_at_price"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <InputError
                                :message="productForm.errors.compare_at_price"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="prod_stock">Stock Quantity</Label>
                            <Input
                                id="prod_stock"
                                v-model="productForm.stock_quantity"
                                type="number"
                                min="0"
                                required
                            />
                            <InputError
                                :message="productForm.errors.stock_quantity"
                            />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="prod_short">Short Description</Label>
                        <Input
                            id="prod_short"
                            v-model="productForm.short_description"
                        />
                        <InputError
                            :message="productForm.errors.short_description"
                        />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="productDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="productForm.processing"
                        >
                            Save Product
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- New Category Dialog -->
        <Dialog v-model:open="categoryDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Create Category</DialogTitle>
                    <DialogDescription>
                        Add a new product department to the storefront.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitCategory">
                    <div class="grid gap-1.5">
                        <Label for="cat_name">Category Name</Label>
                        <Input
                            id="cat_name"
                            v-model="categoryForm.name"
                            required
                        />
                        <InputError :message="categoryForm.errors.name" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="cat_desc">Description (optional)</Label>
                        <Input
                            id="cat_desc"
                            v-model="categoryForm.description"
                        />
                        <InputError
                            :message="categoryForm.errors.description"
                        />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="categoryDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="categoryForm.processing"
                        >
                            Save Category
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Adjust Stock Dialog -->
        <Dialog v-model:open="stockDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Adjust Stock: {{ stockTargetProduct?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Current stock:
                        <strong>{{ stockTargetProduct?.stockQuantity }}</strong>
                        units. Every adjustment writes an auditable ledger
                        entry.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitAdjustStock">
                    <fieldset class="w-full min-w-0 space-y-2">
                        <legend class="text-sm font-medium text-foreground">
                            Movement Type
                        </legend>
                        <RadioGroup
                            v-model="stockForm.type"
                            aria-label="Stock movement type"
                            class="grid grid-cols-2 gap-2"
                        >
                            <label
                                for="stock-type-restock"
                                :class="[
                                    'flex min-h-11 cursor-pointer items-center gap-2.5 rounded-xl border px-3 py-2 text-sm',
                                    stockForm.type === 'restock'
                                        ? 'border-primary/45 bg-brand-subtle/45 font-semibold'
                                        : 'border-border/70',
                                ]"
                            >
                                <RadioGroupItem
                                    id="stock-type-restock"
                                    value="restock"
                                />
                                <span>Restock</span>
                            </label>
                            <label
                                for="stock-type-manual"
                                :class="[
                                    'flex min-h-11 cursor-pointer items-center gap-2.5 rounded-xl border px-3 py-2 text-sm',
                                    stockForm.type === 'manual_adjustment'
                                        ? 'border-primary/45 bg-brand-subtle/45 font-semibold'
                                        : 'border-border/70',
                                ]"
                            >
                                <RadioGroupItem
                                    id="stock-type-manual"
                                    value="manual_adjustment"
                                />
                                <span>Adjustment</span>
                            </label>
                        </RadioGroup>
                    </fieldset>

                    <div class="grid gap-1.5">
                        <Label for="stock_delta">
                            Quantity Delta (e.g. +10 or -3)
                        </Label>
                        <Input
                            id="stock_delta"
                            v-model="stockForm.quantity_delta"
                            type="number"
                            required
                        />
                        <InputError
                            :message="stockForm.errors.quantity_delta"
                        />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="stock_reason">Audit Reason</Label>
                        <Input
                            id="stock_reason"
                            v-model="stockForm.reason"
                            required
                            placeholder="Warehouse recount or supplier delivery"
                        />
                        <InputError :message="stockForm.errors.reason" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="stockDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="stockForm.processing">
                            Record Movement
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Validated CSV Import Dialog (Admin Only) -->
        <Dialog v-model:open="importDialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Validated CSV Product Import</DialogTitle>
                    <DialogDescription>
                        All rows are validated before committing. Required
                        columns:
                        <code>
                            sku,name,category_slug,price,stock_quantity,status
                        </code>
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitCsvImport">
                    <div class="grid gap-1.5">
                        <Label for="csv_data">CSV Payload</Label>
                        <textarea
                            id="csv_data"
                            v-model="importForm.csv_data"
                            rows="6"
                            class="w-full rounded-xl border border-input bg-background p-3 font-mono text-xs text-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30 focus-visible:outline-none"
                        />
                        <InputError :message="importForm.errors.csv_data" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="importDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="importForm.processing">
                            Validate &amp; Import
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
