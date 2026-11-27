<script setup lang="ts">
import { SliderRange, SliderRoot, SliderThumb, SliderTrack } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@shared/components/ui/radio-group';
import type {
    CatalogFilterState,
    FilterableCategory,
} from '@modules/catalog/types';

export interface AppliedFilters {
    category: string | null;
    minPrice: string | null;
    maxPrice: string | null;
    inStock: boolean;
    onSale: boolean;
    minRating: number | null;
}

const ALL_CATEGORIES_VALUE = '__all__';

const props = withDefaults(
    defineProps<{
        filters: CatalogFilterState;
        categories: FilterableCategory[];
        maxCatalogPrice?: number;
        idPrefix?: string;
    }>(),
    {
        maxCatalogPrice: 20000,
        idPrefix: 'filters',
    },
);

const emit = defineEmits<{
    apply: [filters: AppliedFilters];
    reset: [];
}>();

const PRICE_MIN = 0;
const sliderMax = computed(() => Math.max(20000, props.maxCatalogPrice));
const sliderStep = computed(() => (sliderMax.value > 50000 ? 500 : 100));

function parseSliderBounds(
    minRaw: string | null,
    maxRaw: string | null,
    upperBound: number,
): [number, number] {
    const parsedMin = minRaw ? Number(minRaw) : PRICE_MIN;
    const parsedMax = maxRaw ? Number(maxRaw) : upperBound;
    const safeMin = Number.isFinite(parsedMin)
        ? Math.max(PRICE_MIN, Math.min(upperBound, parsedMin))
        : PRICE_MIN;
    const safeMax = Number.isFinite(parsedMax)
        ? Math.max(safeMin, Math.min(upperBound, parsedMax))
        : upperBound;

    return [safeMin, safeMax];
}

const category = ref<string | null>(props.filters.category);
const priceRange = ref<[number, number]>(
    parseSliderBounds(
        props.filters.minPrice,
        props.filters.maxPrice,
        sliderMax.value,
    ),
);
const inStock = ref(props.filters.inStock);
const onSale = ref(props.filters.onSale);
const minRating = ref<number | null>(props.filters.minRating);

watch(
    [() => props.filters, sliderMax],
    ([next, upper]) => {
        category.value = next.category;
        priceRange.value = parseSliderBounds(
            next.minPrice,
            next.maxPrice,
            upper,
        );
        inStock.value = next.inStock;
        onSale.value = next.onSale;
        minRating.value = next.minRating;
    },
    { deep: true },
);

const radioCategoryModel = computed<string>({
    get: () => category.value ?? ALL_CATEGORIES_VALUE,
    set: (next: string) => {
        category.value = next === ALL_CATEGORIES_VALUE ? null : next;
        submit();
    },
});

function formatCurrencyLabel(amount: number, isUpperMax: boolean): string {
    const formatted = amount.toLocaleString('en-PH');
    return isUpperMax && amount >= sliderMax.value
        ? `₱${formatted}+`
        : `₱${formatted}`;
}

function submit(): void {
    const [low, high] = priceRange.value;

    emit('apply', {
        category: category.value,
        minPrice: low > PRICE_MIN ? String(low) : null,
        maxPrice: high < sliderMax.value ? String(high) : null,
        inStock: inStock.value,
        onSale: onSale.value,
        minRating: minRating.value,
    });
}

function handleInStockToggle(checked: boolean): void {
    inStock.value = checked;
    submit();
}

function handleOnSaleToggle(checked: boolean): void {
    onSale.value = checked;
    submit();
}

function handleMinRatingToggle(checked: boolean): void {
    minRating.value = checked ? 4 : null;
    submit();
}
</script>

<template>
    <form
        class="w-full min-w-0 space-y-4"
        aria-label="Product filters"
        @submit.prevent="submit"
    >
        <fieldset class="w-full min-w-0 space-y-1.5">
            <legend
                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                Category
            </legend>

            <RadioGroup
                v-model="radioCategoryModel"
                aria-label="Filter by product category"
                class="w-full min-w-0 gap-1"
            >
                <label
                    :for="`${idPrefix}-category-all`"
                    :class="[
                        'flex min-h-8 w-full min-w-0 cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs transition-all',
                        category === null
                            ? 'border-primary/45 bg-brand-subtle/45 font-semibold text-foreground shadow-2xs'
                            : 'border-transparent text-foreground/90 hover:border-border hover:bg-secondary/60',
                    ]"
                >
                    <RadioGroupItem
                        :id="`${idPrefix}-category-all`"
                        :value="ALL_CATEGORIES_VALUE"
                        class="size-3.5"
                    />
                    <span class="min-w-0 flex-1 truncate">All categories</span>
                </label>

                <label
                    v-for="item in categories"
                    :key="item.id"
                    :for="`${idPrefix}-category-${item.slug}`"
                    :class="[
                        'flex min-h-8 w-full min-w-0 cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs transition-all',
                        category === item.slug
                            ? 'border-primary/45 bg-brand-subtle/45 font-semibold text-foreground shadow-2xs'
                            : 'border-transparent text-foreground/90 hover:border-border hover:bg-secondary/60',
                    ]"
                >
                    <RadioGroupItem
                        :id="`${idPrefix}-category-${item.slug}`"
                        :value="item.slug"
                        class="size-3.5"
                    />
                    <span class="min-w-0 flex-1 truncate">{{ item.name }}</span>
                    <span
                        v-if="
                            item.productCount !== null &&
                            item.productCount !== undefined
                        "
                        :class="[
                            'shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums transition-colors',
                            category === item.slug
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-secondary text-muted-foreground',
                        ]"
                    >
                        {{ item.productCount }}
                    </span>
                </label>
            </RadioGroup>
        </fieldset>

        <fieldset
            class="w-full min-w-0 space-y-2.5 border-t border-border/60 pt-3"
        >
            <div class="flex items-center justify-between gap-2">
                <legend
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Price (PHP)
                </legend>
                <span
                    class="text-[11px] font-semibold text-foreground tabular-nums"
                >
                    {{ formatCurrencyLabel(priceRange[0], false) }} –
                    {{ formatCurrencyLabel(priceRange[1], true) }}
                </span>
            </div>

            <div class="px-1 py-2">
                <SliderRoot
                    v-model="priceRange"
                    :min="PRICE_MIN"
                    :max="sliderMax"
                    :step="sliderStep"
                    :min-steps-between-thumbs="1"
                    class="relative flex w-full touch-none items-center select-none"
                    aria-label="Price range slider"
                    @value-commit="submit"
                >
                    <SliderTrack
                        class="relative h-1.5 w-full grow overflow-hidden rounded-full bg-secondary"
                    >
                        <SliderRange class="absolute h-full bg-primary" />
                    </SliderTrack>
                    <SliderThumb
                        class="block size-4 rounded-full border-2 border-primary bg-background shadow-sm transition-transform hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label="Minimum price"
                    />
                    <SliderThumb
                        class="block size-4 rounded-full border-2 border-primary bg-background shadow-sm transition-transform hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label="Maximum price"
                    />
                </SliderRoot>
            </div>
        </fieldset>

        <div class="space-y-1.5">
            <label
                :for="`${idPrefix}-in-stock`"
                :class="[
                    'flex min-h-9 cursor-pointer items-center gap-2.5 rounded-lg border px-2.5 py-1.5 text-xs transition-all',
                    inStock
                        ? 'border-primary/45 bg-brand-subtle/45 font-semibold text-foreground shadow-2xs'
                        : 'border-border/70 bg-secondary/25 text-foreground/90 hover:border-border hover:bg-secondary/60',
                ]"
            >
                <Checkbox
                    :id="`${idPrefix}-in-stock`"
                    :model-value="inStock"
                    @update:model-value="handleInStockToggle($event === true)"
                />
                <span>In stock only</span>
            </label>

            <label
                :for="`${idPrefix}-on-sale`"
                :class="[
                    'flex min-h-9 cursor-pointer items-center gap-2.5 rounded-lg border px-2.5 py-1.5 text-xs transition-all',
                    onSale
                        ? 'border-primary/45 bg-brand-subtle/45 font-semibold text-foreground shadow-2xs'
                        : 'border-border/70 bg-secondary/25 text-foreground/90 hover:border-border hover:bg-secondary/60',
                ]"
            >
                <Checkbox
                    :id="`${idPrefix}-on-sale`"
                    :model-value="onSale"
                    @update:model-value="handleOnSaleToggle($event === true)"
                />
                <span>On sale / discounted</span>
            </label>

            <label
                :for="`${idPrefix}-min-rating`"
                :class="[
                    'flex min-h-9 cursor-pointer items-center gap-2.5 rounded-lg border px-2.5 py-1.5 text-xs transition-all',
                    minRating === 4
                        ? 'border-primary/45 bg-brand-subtle/45 font-semibold text-foreground shadow-2xs'
                        : 'border-border/70 bg-secondary/25 text-foreground/90 hover:border-border hover:bg-secondary/60',
                ]"
            >
                <Checkbox
                    :id="`${idPrefix}-min-rating`"
                    :model-value="minRating === 4"
                    @update:model-value="handleMinRatingToggle($event === true)"
                />
                <span>4★ &amp; up</span>
            </label>
        </div>

        <div class="flex gap-2 pt-1">
            <Button
                type="submit"
                size="sm"
                class="h-9 flex-1 rounded-lg text-xs font-semibold"
            >
                Apply
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="h-9 rounded-lg text-xs"
                @click="emit('reset')"
            >
                Reset
            </Button>
        </div>
    </form>
</template>
