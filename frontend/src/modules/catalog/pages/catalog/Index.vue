<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Filter, ShoppingBag, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    CartPreviewDialog,
    useCartPreview,
    type CartSummary,
} from '@modules/cart';
import PaginationBar from '@modules/catalog/components/PaginationBar.vue';
import ProductCard from '@modules/catalog/components/ProductCard.vue';
import ProductFilters, {
    type AppliedFilters,
} from '@modules/catalog/components/ProductFilters.vue';
import SortSelect from '@modules/catalog/components/SortSelect.vue';
import type {
    CatalogFilterState,
    PaginationMeta,
    ProductSummary,
    SortOption,
} from '@modules/catalog/types';
import {
    StorefrontLayout,
    type AnnouncementBanner,
    type CategorySummary,
} from '@modules/storefront';
import Breadcrumbs from '@shared/components/Breadcrumbs.vue';
import EmptyState from '@shared/components/EmptyState.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    CustomDropdown,
    type CustomDropdownOption,
} from '@shared/components/ui/custom-dropdown';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@shared/components/ui/sheet';
import type { BreadcrumbItem } from '@shared/types';
import { home } from '@/routes';
import { store as storeCartItem } from '@/routes/cart/items';
import { index as productsIndex } from '@/routes/products';

const props = withDefaults(
    defineProps<{
        announcement?: AnnouncementBanner | null;
        categories: CategorySummary[];
        cartSummary: CartSummary;
        products: ProductSummary[];
        pagination: PaginationMeta;
        filters: CatalogFilterState;
        sortOptions: SortOption[];
        perPageOptions?: number[];
        activeCategory?: CategorySummary | null;
    }>(),
    {
        announcement: null,
        perPageOptions: () => [12, 24, 48],
        activeCategory: null,
    },
);

const cartPreview = useCartPreview();
const mobileFiltersOpen = ref(false);

const effectiveCart = computed<CartSummary>(() => ({
    itemCount: props.cartSummary.itemCount + cartPreview.itemCount.value,
    subtotalFormatted: props.cartSummary.subtotalFormatted,
}));

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const items: BreadcrumbItem[] = [
        { title: 'Home', href: home.url() },
        { title: 'Products', href: productsIndex.url() },
    ];

    if (props.activeCategory) {
        items.push({
            title: props.activeCategory.name,
            href: productsIndex.url({
                query: { category: props.activeCategory.slug },
            }),
        });
    }

    return items;
});

const hasActiveFilters = computed(
    () =>
        Boolean(props.filters.q) ||
        Boolean(props.filters.category) ||
        Boolean(props.filters.minPrice) ||
        Boolean(props.filters.maxPrice) ||
        props.filters.inStock,
);

function buildQuery(
    overrides: Partial<CatalogFilterState & { page?: number }> = {},
): Record<string, string | number | undefined> {
    const merged = {
        ...props.filters,
        ...overrides,
    };

    return {
        q: merged.q || undefined,
        category: merged.category || undefined,
        min_price: merged.minPrice || undefined,
        max_price: merged.maxPrice || undefined,
        in_stock: merged.inStock ? 1 : undefined,
        sort:
            merged.sort && merged.sort !== 'featured' ? merged.sort : undefined,
        per_page:
            merged.perPage && merged.perPage !== 12
                ? merged.perPage
                : undefined,
        page: merged.page && merged.page > 1 ? merged.page : undefined,
    };
}

function navigate(
    overrides: Partial<CatalogFilterState & { page?: number }> = {},
): void {
    router.get(
        productsIndex.url({ query: buildQuery(overrides) }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function handleSearch(query: string): void {
    navigate({ q: query, page: 1 });
}

function handleSelectCategory(slug: string | null): void {
    navigate({ category: slug, page: 1 });
}

function handleApplyFilters(next: AppliedFilters): void {
    mobileFiltersOpen.value = false;
    navigate({
        category: next.category,
        minPrice: next.minPrice,
        maxPrice: next.maxPrice,
        inStock: next.inStock,
        page: 1,
    });
}

function handleResetFilters(): void {
    mobileFiltersOpen.value = false;
    router.get(productsIndex.url());
}

function handleSortChange(sort: string): void {
    navigate({ sort, page: 1 });
}

const perPageDropdownOptions = computed<CustomDropdownOption[]>(() =>
    props.perPageOptions.map((count) => ({
        value: String(count),
        label: `${count} / page`,
    })),
);

function handlePerPageChange(next: string): void {
    navigate({ perPage: Number(next), page: 1 });
}

function handlePageChange(page: number): void {
    navigate({ page });
}

function handleAddToCart(product: ProductSummary): void {
    cartPreview.add({
        name: product.name,
        priceFormatted: product.priceFormatted,
    });

    if (typeof product.id === 'number') {
        router.post(
            storeCartItem.url(),
            {
                product_id: product.id,
                quantity: 1,
                selected_color: product.selectedColor ?? undefined,
                selected_size: product.selectedSize ?? undefined,
            },
            { preserveScroll: true, preserveState: true },
        );
    }
}
</script>

<template>
    <Head
        :title="
            activeCategory
                ? `${activeCategory.name} — XHopy Catalog`
                : 'Product Catalog — XHopy'
        "
    />

    <StorefrontLayout
        :announcement="announcement"
        :categories="categories"
        :cart="effectiveCart"
        :search-query="filters.q"
        :selected-category="filters.category"
        @search="handleSearch"
        @select-category="handleSelectCategory"
        @open-cart="cartPreview.open()"
    >
        <div
            class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 sm:py-10 lg:px-8"
        >
            <!-- Breadcrumb & Header -->
            <div class="space-y-3">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />

                <div
                    class="flex flex-col justify-between gap-4 border-b border-border pb-6 sm:flex-row sm:items-end"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wider text-primary uppercase"
                        >
                            Verified Catalog
                        </p>
                        <h1
                            class="mt-1 text-2xl font-extrabold tracking-tight text-foreground sm:text-3xl"
                        >
                            {{
                                activeCategory
                                    ? activeCategory.name
                                    : 'All Products'
                            }}
                        </h1>
                        <p
                            v-if="activeCategory?.description"
                            class="mt-1.5 max-w-2xl text-sm text-muted-foreground"
                        >
                            {{ activeCategory.description }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Mobile Filter Drawer Trigger -->
                        <Sheet v-model:open="mobileFiltersOpen">
                            <SheetTrigger as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-11 gap-2 lg:hidden"
                                >
                                    <Filter class="size-4" aria-hidden="true" />
                                    <span>Filters</span>
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="left"
                                class="w-80 overflow-y-auto p-6"
                            >
                                <SheetHeader class="text-left">
                                    <SheetTitle>Filter Products</SheetTitle>
                                    <SheetDescription>
                                        Narrow results by department, price
                                        range, and stock availability.
                                    </SheetDescription>
                                </SheetHeader>
                                <div class="mt-6">
                                    <ProductFilters
                                        :filters="filters"
                                        :categories="categories"
                                        id-prefix="mobile-filters"
                                        @apply="handleApplyFilters"
                                        @reset="handleResetFilters"
                                    />
                                </div>
                            </SheetContent>
                        </Sheet>

                        <SortSelect
                            :model-value="filters.sort"
                            :options="sortOptions"
                            @update:model-value="handleSortChange"
                        />

                        <CustomDropdown
                            id="catalog-per-page"
                            :model-value="String(filters.perPage)"
                            :options="perPageDropdownOptions"
                            label="Show"
                            aria-label="Products per page"
                            class="min-w-[8.75rem]"
                            @update:model-value="handlePerPageChange"
                        />
                    </div>
                </div>
            </div>

            <!-- Active Filter Chips -->
            <div
                v-if="hasActiveFilters"
                class="flex flex-wrap items-center gap-2"
                aria-label="Active filters"
            >
                <span class="text-xs font-medium text-muted-foreground">
                    Active filters:
                </span>

                <Badge
                    v-if="filters.q"
                    variant="secondary"
                    class="gap-1.5 px-2.5 py-1 text-xs"
                >
                    <span>Search: "{{ filters.q }}"</span>
                    <button
                        type="button"
                        class="rounded-full hover:text-foreground"
                        aria-label="Remove search filter"
                        @click="navigate({ q: '', page: 1 })"
                    >
                        <X class="size-3" aria-hidden="true" />
                    </button>
                </Badge>

                <Badge
                    v-if="filters.category"
                    variant="secondary"
                    class="gap-1.5 px-2.5 py-1 text-xs"
                >
                    <span>
                        Category:
                        {{ activeCategory?.name ?? filters.category }}
                    </span>
                    <button
                        type="button"
                        class="rounded-full hover:text-foreground"
                        aria-label="Remove category filter"
                        @click="navigate({ category: null, page: 1 })"
                    >
                        <X class="size-3" aria-hidden="true" />
                    </button>
                </Badge>

                <Badge
                    v-if="filters.minPrice || filters.maxPrice"
                    variant="secondary"
                    class="gap-1.5 px-2.5 py-1 text-xs"
                >
                    <span>
                        Price: ₱{{ filters.minPrice ?? '0.00' }} –
                        {{ filters.maxPrice ? `₱${filters.maxPrice}` : 'Any' }}
                    </span>
                    <button
                        type="button"
                        class="rounded-full hover:text-foreground"
                        aria-label="Remove price filter"
                        @click="
                            navigate({
                                minPrice: null,
                                maxPrice: null,
                                page: 1,
                            })
                        "
                    >
                        <X class="size-3" aria-hidden="true" />
                    </button>
                </Badge>

                <Badge
                    v-if="filters.inStock"
                    variant="secondary"
                    class="gap-1.5 px-2.5 py-1 text-xs"
                >
                    <span>In stock only</span>
                    <button
                        type="button"
                        class="rounded-full hover:text-foreground"
                        aria-label="Remove in-stock filter"
                        @click="navigate({ inStock: false, page: 1 })"
                    >
                        <X class="size-3" aria-hidden="true" />
                    </button>
                </Badge>

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-8 text-xs font-semibold text-primary"
                    @click="handleResetFilters"
                >
                    Clear all
                </Button>
            </div>

            <!-- Main Catalog Layout: Sidebar Filters + Product Grid -->
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
                <!-- Desktop Sidebar -->
                <aside
                    aria-label="Catalog filters"
                    class="hidden min-w-0 lg:col-span-3 lg:block"
                >
                    <div
                        class="sticky top-28 overflow-hidden rounded-2xl border border-border bg-card p-5"
                    >
                        <ProductFilters
                            :filters="filters"
                            :categories="categories"
                            id-prefix="desktop-filters"
                            @apply="handleApplyFilters"
                            @reset="handleResetFilters"
                        />
                    </div>
                </aside>

                <!-- Product Results Column -->
                <div class="space-y-8 lg:col-span-9">
                    <div
                        class="flex items-center justify-between text-sm text-muted-foreground"
                    >
                        <p>
                            Showing
                            <strong class="font-semibold text-foreground">{{
                                products.length
                            }}</strong>
                            of
                            <strong class="font-semibold text-foreground">{{
                                pagination.total
                            }}</strong>
                            {{
                                pagination.total === 1 ? 'product' : 'products'
                            }}
                        </p>
                        <Link
                            :href="home.url()"
                            class="text-xs font-semibold text-primary hover:underline"
                        >
                            Back to Storefront Home
                        </Link>
                    </div>

                    <div
                        v-if="products.length > 0"
                        class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <ProductCard
                            v-for="product in products"
                            :key="product.id"
                            :product="product"
                            @add-to-cart="handleAddToCart"
                        />
                    </div>

                    <EmptyState
                        v-else
                        :icon="ShoppingBag"
                        :title="
                            hasActiveFilters
                                ? 'No matching products found'
                                : 'No products published yet'
                        "
                        :description="
                            hasActiveFilters
                                ? 'Try broadening your search keywords or clearing one of the active filters.'
                                : 'Products will appear here as soon as they are published to the catalog.'
                        "
                        :action-label="
                            hasActiveFilters ? 'Reset all filters' : null
                        "
                        @action="handleResetFilters"
                    />

                    <PaginationBar
                        :meta="pagination"
                        @change-page="handlePageChange"
                    />
                </div>
            </div>
        </div>

        <CartPreviewDialog />
    </StorefrontLayout>
</template>
