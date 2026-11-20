<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
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
}

const ALL_CATEGORIES_VALUE = '__all__';

const props = withDefaults(
    defineProps<{
        filters: CatalogFilterState;
        categories: FilterableCategory[];
        idPrefix?: string;
    }>(),
    { idPrefix: 'filters' },
);

const emit = defineEmits<{
    apply: [filters: AppliedFilters];
    reset: [];
}>();

const category = ref<string | null>(props.filters.category);
const minPrice = ref(props.filters.minPrice ?? '');
const maxPrice = ref(props.filters.maxPrice ?? '');
const inStock = ref(props.filters.inStock);

watch(
    () => props.filters,
    (next) => {
        category.value = next.category;
        minPrice.value = next.minPrice ?? '';
        maxPrice.value = next.maxPrice ?? '';
        inStock.value = next.inStock;
    },
    { deep: true },
);

const radioCategoryModel = computed<string>({
    get: () => category.value ?? ALL_CATEGORIES_VALUE,
    set: (next: string) => {
        category.value = next === ALL_CATEGORIES_VALUE ? null : next;
    },
});

const priceError = computed(() => {
    if (minPrice.value === '' || maxPrice.value === '') {
        return null;
    }

    return Number(maxPrice.value) < Number(minPrice.value)
        ? 'Maximum price must be at least the minimum price.'
        : null;
});

function normalizePrice(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

function submit(): void {
    if (priceError.value) {
        return;
    }

    emit('apply', {
        category: category.value,
        minPrice: normalizePrice(minPrice.value),
        maxPrice: normalizePrice(maxPrice.value),
        inStock: inStock.value,
    });
}
</script>

<template>
    <form
        class="w-full min-w-0 space-y-6"
        aria-label="Product filters"
        @submit.prevent="submit"
    >
        <fieldset class="w-full min-w-0 space-y-2.5">
            <legend class="text-sm font-semibold text-foreground">
                Category
            </legend>

            <RadioGroup
                v-model="radioCategoryModel"
                aria-label="Filter by product category"
                class="w-full min-w-0 gap-1.5"
            >
                <label
                    :for="`${idPrefix}-category-all`"
                    :class="[
                        'flex min-h-11 w-full min-w-0 cursor-pointer items-center gap-3 rounded-xl border px-3 py-2 text-sm transition-all',
                        category === null
                            ? 'border-primary/45 bg-brand-subtle/45 font-semibold text-foreground shadow-2xs'
                            : 'border-transparent text-foreground/90 hover:border-border hover:bg-secondary/60',
                    ]"
                >
                    <RadioGroupItem
                        :id="`${idPrefix}-category-all`"
                        :value="ALL_CATEGORIES_VALUE"
                    />
                    <span class="min-w-0 flex-1 truncate">All categories</span>
                </label>

                <label
                    v-for="item in categories"
                    :key="item.id"
                    :for="`${idPrefix}-category-${item.slug}`"
                    :class="[
                        'flex min-h-11 w-full min-w-0 cursor-pointer items-center gap-3 rounded-xl border px-3 py-2 text-sm transition-all',
                        category === item.slug
                            ? 'border-primary/45 bg-brand-subtle/45 font-semibold text-foreground shadow-2xs'
                            : 'border-transparent text-foreground/90 hover:border-border hover:bg-secondary/60',
                    ]"
                >
                    <RadioGroupItem
                        :id="`${idPrefix}-category-${item.slug}`"
                        :value="item.slug"
                    />
                    <span class="min-w-0 flex-1 truncate">{{ item.name }}</span>
                    <span
                        v-if="
                            item.productCount !== null &&
                            item.productCount !== undefined
                        "
                        :class="[
                            'shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums transition-colors',
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

        <fieldset class="w-full min-w-0 space-y-2">
            <legend class="text-sm font-semibold text-foreground">
                Price (PHP)
            </legend>
            <div class="flex items-center gap-2">
                <div class="min-w-0 flex-1">
                    <Label :for="`${idPrefix}-min`" class="sr-only">
                        Minimum price
                    </Label>
                    <Input
                        :id="`${idPrefix}-min`"
                        v-model="minPrice"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        step="0.01"
                        placeholder="Min"
                        class="h-11 rounded-xl"
                        :aria-invalid="priceError ? 'true' : undefined"
                    />
                </div>
                <span class="text-muted-foreground" aria-hidden="true">–</span>
                <div class="min-w-0 flex-1">
                    <Label :for="`${idPrefix}-max`" class="sr-only">
                        Maximum price
                    </Label>
                    <Input
                        :id="`${idPrefix}-max`"
                        v-model="maxPrice"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        step="0.01"
                        placeholder="Max"
                        class="h-11 rounded-xl"
                        :aria-invalid="priceError ? 'true' : undefined"
                        :aria-describedby="
                            priceError ? `${idPrefix}-price-error` : undefined
                        "
                    />
                </div>
            </div>
            <p
                v-if="priceError"
                :id="`${idPrefix}-price-error`"
                class="text-xs text-destructive"
                role="alert"
            >
                {{ priceError }}
            </p>
        </fieldset>

        <label
            :for="`${idPrefix}-in-stock`"
            :class="[
                'flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border px-3 py-2 text-sm transition-all',
                inStock
                    ? 'border-primary/45 bg-brand-subtle/45 font-semibold text-foreground shadow-2xs'
                    : 'border-border/70 bg-secondary/25 text-foreground/90 hover:border-border hover:bg-secondary/60',
            ]"
        >
            <Checkbox
                :id="`${idPrefix}-in-stock`"
                :model-value="inStock"
                @update:model-value="inStock = $event === true"
            />
            <span>In stock only</span>
        </label>

        <div class="flex gap-2">
            <Button
                type="submit"
                class="h-11 flex-1 rounded-xl text-sm font-semibold"
            >
                Apply filters
            </Button>
            <Button
                type="button"
                variant="outline"
                class="h-11 rounded-xl text-sm"
                @click="emit('reset')"
            >
                Reset
            </Button>
        </div>
    </form>
</template>
