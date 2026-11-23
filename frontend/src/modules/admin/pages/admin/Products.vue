<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    FolderPlus,
    ImagePlus,
    Package,
    PackagePlus,
    Pencil,
    RefreshCw,
    Trash2,
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

const deleteDialogOpen = ref(false);
const deletingSingleProduct = ref<AdminProductRow | null>(null);
const isDeleting = ref(false);

const allProductsSelected = computed(
    () =>
        props.products.length > 0 &&
        selectedIds.value.length === props.products.length,
);

function toggleSelectAll(checked: boolean): void {
    if (checked) {
        selectedIds.value = props.products.map((product) => product.id);
    } else {
        selectedIds.value = [];
    }
}

function openSingleDeleteDialog(product: AdminProductRow): void {
    deletingSingleProduct.value = product;
    deleteDialogOpen.value = true;
}

function openBulkDeleteDialog(): void {
    if (selectedIds.value.length === 0) {
        return;
    }
    deletingSingleProduct.value = null;
    deleteDialogOpen.value = true;
}

function confirmDeleteProducts(): void {
    if (isDeleting.value) {
        return;
    }

    isDeleting.value = true;

    if (deletingSingleProduct.value) {
        const targetId = deletingSingleProduct.value.id;
        router.delete(`/admin/products/${targetId}`, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = selectedIds.value.filter(
                    (id) => id !== targetId,
                );
                deleteDialogOpen.value = false;
                deletingSingleProduct.value = null;
            },
            onFinish: () => {
                isDeleting.value = false;
            },
        });
        return;
    }

    router.post(
        '/admin/products/bulk-delete',
        { product_ids: selectedIds.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
                deleteDialogOpen.value = false;
            },
            onFinish: () => {
                isDeleting.value = false;
            },
        },
    );
}

function handleDeleteCategory(category: AdminCategoryRow): void {
    router.delete(`/admin/categories/${category.id}`, {
        preserveScroll: true,
    });
}

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
    _method: 'post',
    category_id: props.categories[0]?.id ? String(props.categories[0].id) : '',
    name: '',
    sku: '',
    short_description: '',
    description: '',
    price: '0.00',
    compare_at_price: '',
    stock_quantity: 0,
    available_colors: '',
    available_sizes: '',
    status: 'active',
    is_featured: false,
    image_url: '',
});

const selectedImageFile = ref<File | null>(null);
const imagePreviewUrl = ref<string | null>(null);
const colorImageFiles = ref<Record<string, File | null>>({});
const colorImageUrls = ref<Record<string, string>>({});
const colorImagePreviews = ref<Record<string, string>>({});
const useVariantStocks = ref(false);
const variantStockValues = ref<Record<string, number>>({});

function parseCommaList(rawText: string): string[] {
    const seen = new Set<string>();
    const list: string[] = [];

    for (const raw of rawText.split(',')) {
        const value = raw.trim();
        if (value !== '' && !seen.has(value)) {
            seen.add(value);
            list.push(value);
        }
    }

    return list;
}

const parsedColorList = computed<string[]>(() =>
    parseCommaList(productForm.available_colors),
);

const parsedSizeList = computed<string[]>(() =>
    parseCommaList(productForm.available_sizes),
);

interface VariantCombination {
    key: string;
    color: string;
    size: string;
    label: string;
}

const variantCombinations = computed<VariantCombination[]>(() => {
    const hasColors = parsedColorList.value.length > 0;
    const hasSizes = parsedSizeList.value.length > 0;
    if (!hasColors && !hasSizes) {
        return [];
    }

    const colors = hasColors ? parsedColorList.value : [''];
    const sizes = hasSizes ? parsedSizeList.value : [''];
    const result: VariantCombination[] = [];

    for (const color of colors) {
        for (const size of sizes) {
            const key = `${color}|${size}`;
            const label = [color, size]
                .filter((part) => part !== '')
                .join(' / ');
            result.push({ key, color, size, label });
        }
    }

    return result;
});

const variantTotalStock = computed<number>(() =>
    variantCombinations.value.reduce(
        (sum, combo) =>
            sum +
            Math.max(0, Number(variantStockValues.value[combo.key] ?? 0) || 0),
        0,
    ),
);

function setVariantStock(key: string, value: string | number): void {
    const numeric = Math.max(0, Number.parseInt(String(value || '0'), 10) || 0);
    variantStockValues.value = {
        ...variantStockValues.value,
        [key]: numeric,
    };
}

function handleProductImageSelect(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    selectedImageFile.value = file;

    if (file) {
        imagePreviewUrl.value = URL.createObjectURL(file);
    } else {
        imagePreviewUrl.value = editingProduct.value?.imageUrl ?? null;
    }
}

function handleColorImageSelect(color: string, event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    colorImageFiles.value = {
        ...colorImageFiles.value,
        [color]: file,
    };

    if (file) {
        colorImagePreviews.value = {
            ...colorImagePreviews.value,
            [color]: URL.createObjectURL(file),
        };
    } else {
        const existingUrl = editingProduct.value?.colorImages?.[color];
        if (existingUrl) {
            colorImagePreviews.value = {
                ...colorImagePreviews.value,
                [color]: existingUrl,
            };
        } else {
            const next = { ...colorImagePreviews.value };
            delete next[color];
            colorImagePreviews.value = next;
        }
    }
}

function setColorImageUrl(color: string, value: string | number): void {
    colorImageUrls.value = {
        ...colorImageUrls.value,
        [color]: String(value),
    };
}

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
    productForm._method = 'post';
    productForm.category_id = props.categories[0]?.id
        ? String(props.categories[0].id)
        : '';
    productForm.status = 'active';
    productForm.available_colors = '';
    productForm.available_sizes = '';
    productForm.image_url = '';
    selectedImageFile.value = null;
    imagePreviewUrl.value = null;
    colorImageFiles.value = {};
    colorImageUrls.value = {};
    colorImagePreviews.value = {};
    useVariantStocks.value = false;
    variantStockValues.value = {};
    productDialogOpen.value = true;
}

function openEditProduct(product: AdminProductRow): void {
    editingProduct.value = product;
    productForm.clearErrors();
    productForm._method = 'put';
    productForm.category_id = String(product.categoryId);
    productForm.name = product.name;
    productForm.sku = product.sku;
    productForm.short_description = product.shortDescription ?? '';
    productForm.description = product.description ?? '';
    productForm.price = product.price;
    productForm.compare_at_price = product.compareAtPrice ?? '';
    productForm.stock_quantity = product.stockQuantity;
    productForm.available_colors = (product.availableColors ?? []).join(', ');
    productForm.available_sizes = (product.availableSizes ?? []).join(', ');
    productForm.status = product.status;
    productForm.is_featured = product.isFeatured;
    productForm.image_url =
        product.imageUrl && product.imageUrl.startsWith('https://')
            ? product.imageUrl
            : '';
    selectedImageFile.value = null;
    imagePreviewUrl.value = product.imageUrl ?? null;
    colorImageFiles.value = {};
    const existingColorMap = product.colorImages ?? {};
    const urls: Record<string, string> = {};
    for (const [color, url] of Object.entries(existingColorMap)) {
        if (url.startsWith('https://')) {
            urls[color] = url;
        }
    }
    colorImageUrls.value = urls;
    colorImagePreviews.value = { ...existingColorMap };
    const existingVariantStocks = product.variantStocks ?? null;
    if (
        existingVariantStocks &&
        Object.keys(existingVariantStocks).length > 0
    ) {
        useVariantStocks.value = true;
        variantStockValues.value = { ...existingVariantStocks };
    } else {
        useVariantStocks.value = false;
        variantStockValues.value = {};
    }
    productDialogOpen.value = true;
}

function buildProductPayload(
    data: Record<string, unknown>,
): Record<string, unknown> {
    const hasCustomVariantStock =
        useVariantStocks.value && variantCombinations.value.length > 0;

    return {
        ...data,
        stock_quantity: hasCustomVariantStock
            ? variantTotalStock.value
            : data.stock_quantity,
        image: selectedImageFile.value,
        color_images: parsedColorList.value.map((color) => ({
            color,
            file: colorImageFiles.value[color] ?? null,
            url: colorImageUrls.value[color] ?? '',
        })),
        variant_stock_entries: hasCustomVariantStock
            ? variantCombinations.value.map((combo) => ({
                  color: combo.color,
                  size: combo.size,
                  quantity: Math.max(
                      0,
                      Number(variantStockValues.value[combo.key] ?? 0) || 0,
                  ),
              }))
            : [],
    };
}

function submitProduct(): void {
    const target = editingProduct.value;
    if (target) {
        productForm._method = 'put';
        productForm
            .transform((data) => buildProductPayload(data))
            .post(updateProductRoute.url(target.id), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    productDialogOpen.value = false;
                },
            });
    } else {
        productForm._method = 'post';
        productForm
            .transform((data) => buildProductPayload(data))
            .post(storeProductRoute.url(), {
                forceFormData: true,
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

        <!-- Departments / Categories Strip -->
        <div
            v-if="categories.length > 0"
            class="flex flex-wrap items-center gap-2 rounded-2xl border border-border bg-card px-4 py-3 shadow-2xs"
        >
            <span
                class="mr-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                Categories ({{ categories.length }}):
            </span>
            <div
                v-for="cat in categories"
                :key="cat.id"
                class="inline-flex items-center gap-1.5 rounded-full border border-border/80 bg-secondary/40 px-3 py-1 text-xs"
            >
                <span class="font-medium text-foreground">{{ cat.name }}</span>
                <span class="text-muted-foreground"
                    >({{ cat.productCount }})</span
                >
                <button
                    v-if="canRunAdminOnlyActions && cat.productCount === 0"
                    type="button"
                    class="ml-0.5 inline-flex size-5 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-destructive/15 hover:text-destructive"
                    :title="`Delete empty category ${cat.name}`"
                    :aria-label="`Delete category ${cat.name}`"
                    @click="handleDeleteCategory(cat)"
                >
                    <Trash2 class="size-3" aria-hidden="true" />
                </button>
            </div>
        </div>

        <!-- Admin-Only Safe Bulk Status & Delete Bar -->
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
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-9 gap-1.5 rounded-xl border-destructive/40 text-xs font-semibold text-destructive hover:bg-destructive/10 hover:text-destructive"
                    @click="openBulkDeleteDialog"
                >
                    <Trash2 class="size-3.5" aria-hidden="true" />
                    <span>Delete Selected ({{ selectedIds.length }})</span>
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
                            class="w-10 px-4 py-3 align-middle"
                        >
                            <Checkbox
                                :model-value="allProductsSelected"
                                aria-label="Select all products"
                                @update:model-value="
                                    toggleSelectAll($event === true)
                                "
                            />
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
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-secondary/40"
                                >
                                    <img
                                        v-if="product.imageUrl"
                                        :src="product.imageUrl"
                                        :alt="product.name"
                                        class="size-full object-cover"
                                    />
                                    <Package
                                        v-else
                                        class="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div>
                                    <p class="font-semibold text-foreground">
                                        {{ product.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        SKU: {{ product.sku }}
                                    </p>
                                    <div
                                        v-if="
                                            (product.availableColors &&
                                                product.availableColors.length >
                                                    0) ||
                                            (product.availableSizes &&
                                                product.availableSizes.length >
                                                    0)
                                        "
                                        class="mt-1 flex flex-wrap gap-1"
                                    >
                                        <span
                                            v-if="
                                                product.availableColors &&
                                                product.availableColors.length >
                                                    0
                                            "
                                            class="rounded-md border border-border/80 bg-secondary/50 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                                        >
                                            Colors:
                                            {{
                                                product.availableColors.join(
                                                    ', ',
                                                )
                                            }}
                                        </span>
                                        <span
                                            v-if="
                                                product.availableSizes &&
                                                product.availableSizes.length >
                                                    0
                                            "
                                            class="rounded-md border border-border/80 bg-secondary/50 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                                        >
                                            Sizes:
                                            {{
                                                product.availableSizes.join(
                                                    ', ',
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
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
                                <Button
                                    v-if="canRunAdminOnlyActions"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 gap-1 rounded-lg text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    :aria-label="`Delete ${product.name}`"
                                    @click="openSingleDeleteDialog(product)"
                                >
                                    <Trash2
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span>Delete</span>
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
                            <Label for="prod_stock">
                                {{
                                    useVariantStocks &&
                                    variantCombinations.length > 0
                                        ? 'Total Stock (Auto)'
                                        : 'Stock Quantity'
                                }}
                            </Label>
                            <Input
                                v-if="
                                    useVariantStocks &&
                                    variantCombinations.length > 0
                                "
                                id="prod_stock"
                                :model-value="variantTotalStock"
                                type="number"
                                disabled
                            />
                            <Input
                                v-else
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

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="prod_colors">
                                Available Colors (optional)
                            </Label>
                            <Input
                                id="prod_colors"
                                v-model="productForm.available_colors"
                                placeholder="Black, Crimson Red, White"
                            />
                            <p class="text-[11px] text-muted-foreground">
                                Separate multiple colors with commas.
                            </p>
                            <InputError
                                :message="productForm.errors.available_colors"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="prod_sizes">
                                Available Sizes or Specs (optional)
                            </Label>
                            <Input
                                id="prod_sizes"
                                v-model="productForm.available_sizes"
                                placeholder="S, M, L, XL or 14mm, 16mm"
                            />
                            <p class="text-[11px] text-muted-foreground">
                                Separate multiple sizes with commas.
                            </p>
                            <InputError
                                :message="productForm.errors.available_sizes"
                            />
                        </div>
                    </div>

                    <!-- Per-Variant Stock Breakdown -->
                    <div
                        v-if="variantCombinations.length > 0"
                        class="space-y-3 rounded-xl border border-border/80 bg-secondary/15 p-3.5"
                    >
                        <label
                            class="flex cursor-pointer items-center justify-between gap-2 text-xs font-semibold text-foreground"
                        >
                            <span>
                                Set different stock quantity per variant ({{
                                    variantCombinations.length
                                }}
                                {{
                                    variantCombinations.length === 1
                                        ? 'variant'
                                        : 'variants'
                                }})
                            </span>
                            <Checkbox
                                :model-value="useVariantStocks"
                                @update:model-value="
                                    useVariantStocks = $event === true
                                "
                            />
                        </label>

                        <div v-if="useVariantStocks" class="space-y-2">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <div
                                    v-for="combo in variantCombinations"
                                    :key="combo.key"
                                    class="flex items-center justify-between gap-2 rounded-lg border border-border/70 bg-background/70 px-3 py-2"
                                >
                                    <span
                                        class="truncate text-xs font-medium text-foreground"
                                    >
                                        {{ combo.label }}
                                    </span>
                                    <Input
                                        :model-value="
                                            variantStockValues[combo.key] ?? 0
                                        "
                                        type="number"
                                        min="0"
                                        class="h-8 w-20 text-right text-xs"
                                        :aria-label="`Stock for ${combo.label}`"
                                        @update:model-value="
                                            setVariantStock(combo.key, $event)
                                        "
                                    />
                                </div>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Total stock across all variants:
                                <strong class="text-foreground">{{
                                    variantTotalStock
                                }}</strong>
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="prod_short">Short Description</Label>
                        <Input
                            id="prod_short"
                            v-model="productForm.short_description"
                            placeholder="Brief one-line product summary"
                        />
                        <InputError
                            :message="productForm.errors.short_description"
                        />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="prod_description">
                            Full Description (optional)
                        </Label>
                        <Input
                            id="prod_description"
                            v-model="productForm.description"
                            placeholder="Detailed specifications, materials, and features..."
                        />
                        <InputError :message="productForm.errors.description" />
                    </div>

                    <!-- Product Photo Upload & Preview -->
                    <div
                        class="space-y-3 rounded-xl border border-border/80 bg-secondary/20 p-3.5"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="space-y-0.5">
                                <Label
                                    for="prod_image"
                                    class="flex items-center gap-1.5 text-xs font-semibold"
                                >
                                    <ImagePlus
                                        class="size-3.5 text-primary"
                                        aria-hidden="true"
                                    />
                                    <span>Product Photo</span>
                                </Label>
                                <p class="text-[11px] text-muted-foreground">
                                    Upload a JPG, PNG, or WebP photo (max 5 MB)
                                </p>
                            </div>

                            <div
                                v-if="imagePreviewUrl"
                                class="size-12 shrink-0 overflow-hidden rounded-lg border border-border bg-background"
                            >
                                <img
                                    :src="imagePreviewUrl"
                                    alt="Product preview"
                                    class="size-full object-cover"
                                />
                            </div>
                        </div>

                        <input
                            id="prod_image"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="block w-full cursor-pointer text-xs text-muted-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-foreground hover:file:bg-primary/90"
                            @change="handleProductImageSelect"
                        />

                        <div class="grid gap-1">
                            <Label
                                for="prod_image_url"
                                class="text-[11px] text-muted-foreground"
                            >
                                Or paste an HTTPS image URL (optional)
                            </Label>
                            <Input
                                id="prod_image_url"
                                v-model="productForm.image_url"
                                type="url"
                                placeholder="Paste secure image URL (optional)"
                                class="h-8 text-xs"
                            />
                            <InputError
                                :message="productForm.errors.image_url"
                            />
                        </div>
                    </div>

                    <!-- Per-Color Photo Uploaders -->
                    <div
                        v-if="parsedColorList.length > 0"
                        class="space-y-3 rounded-xl border border-border/80 bg-secondary/15 p-3.5"
                    >
                        <div class="space-y-0.5">
                            <p class="text-xs font-semibold text-foreground">
                                Photo per Color Variant
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                Upload a photo for each color so customers see
                                the matching product image when they click a
                                color.
                            </p>
                        </div>

                        <div class="space-y-2.5">
                            <div
                                v-for="(color, colorIndex) in parsedColorList"
                                :key="color"
                                class="flex flex-col gap-2 rounded-lg border border-border/70 bg-background/70 p-2.5"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span
                                        class="text-xs font-semibold text-foreground"
                                    >
                                        {{ color }} Photo
                                    </span>

                                    <div
                                        v-if="colorImagePreviews[color]"
                                        class="size-10 shrink-0 overflow-hidden rounded-md border border-border bg-background"
                                    >
                                        <img
                                            :src="colorImagePreviews[color]"
                                            :alt="`${color} preview`"
                                            class="size-full object-cover"
                                        />
                                    </div>
                                </div>

                                <input
                                    :id="`prod_color_img_${colorIndex}`"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="block w-full cursor-pointer text-xs text-muted-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-secondary file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-foreground hover:file:bg-secondary/80"
                                    @change="
                                        handleColorImageSelect(color, $event)
                                    "
                                />

                                <Input
                                    :model-value="colorImageUrls[color] ?? ''"
                                    type="url"
                                    :placeholder="`Or paste secure URL for ${color} (optional)`"
                                    class="h-8 text-xs"
                                    @update:model-value="
                                        setColorImageUrl(color, $event)
                                    "
                                />
                            </div>
                        </div>
                    </div>

                    <label
                        class="flex cursor-pointer items-center gap-2.5 text-xs font-medium text-foreground"
                    >
                        <Checkbox
                            :model-value="productForm.is_featured"
                            @update:model-value="
                                productForm.is_featured = $event === true
                            "
                        />
                        <span>
                            Feature this product in Top Products on the front
                            page
                        </span>
                    </label>

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

        <!-- Confirm Product Deletion Dialog (Admin Only) -->
        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center gap-2 text-destructive"
                    >
                        <AlertTriangle class="size-5" aria-hidden="true" />
                        <span>
                            {{
                                deletingSingleProduct
                                    ? 'Delete Product'
                                    : `Delete ${selectedIds.length} Selected Products`
                            }}
                        </span>
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="deletingSingleProduct">
                            Are you sure you want to permanently delete
                            <strong class="text-foreground">{{
                                deletingSingleProduct.name
                            }}</strong>
                            (<code class="text-xs">{{
                                deletingSingleProduct.sku
                            }}</code
                            >)?
                        </template>
                        <template v-else>
                            Are you sure you want to permanently delete
                            <strong class="text-foreground"
                                >{{ selectedIds.length }} selected
                                product(s)</strong
                            >?
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="rounded-xl border border-border bg-secondary/30 p-3.5 text-xs text-muted-foreground"
                >
                    <p>
                        Historical customer order receipts remain preserved via
                        order item snapshots. Products attached to active
                        unfulfilled orders are protected from deletion until the
                        order is completed or cancelled.
                    </p>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-10 rounded-xl text-xs"
                        :disabled="isDeleting"
                        @click="deleteDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        class="h-10 gap-1.5 rounded-xl text-xs font-semibold"
                        :disabled="isDeleting"
                        @click="confirmDeleteProducts"
                    >
                        <Trash2 class="size-3.5" aria-hidden="true" />
                        <span>
                            {{
                                isDeleting
                                    ? 'Deleting...'
                                    : deletingSingleProduct
                                      ? 'Yes, Delete Product'
                                      : `Yes, Delete ${selectedIds.length} Products`
                            }}
                        </span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
