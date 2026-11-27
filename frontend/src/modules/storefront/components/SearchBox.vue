<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowRight, Package, Search, X } from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';

interface SearchSuggestion {
    id: number;
    name: string;
    slug: string;
    categoryName: string | null;
    priceFormatted: string;
    imageUrl: string | null;
    inStock: boolean;
    url: string;
}

const props = withDefaults(
    defineProps<{
        initialQuery?: string;
        placeholder?: string;
        inputId?: string;
    }>(),
    {
        initialQuery: '',
        placeholder: 'Search products, categories, or SKUs...',
        inputId: 'storefront-search-input',
    },
);

const emit = defineEmits<{
    search: [query: string];
}>();

const containerRef = ref<HTMLElement | null>(null);
const query = ref(props.initialQuery);
const suggestions = ref<SearchSuggestion[]>([]);
const isLoading = ref(false);
const dropdownOpen = ref(false);

let debounceTimer: number | undefined;
let abortController: AbortController | null = null;

onClickOutside(containerRef, () => {
    dropdownOpen.value = false;
});

onBeforeUnmount(() => {
    if (debounceTimer !== undefined) {
        window.clearTimeout(debounceTimer);
    }
    abortController?.abort();
});

watch(
    () => props.initialQuery,
    (next) => {
        query.value = next;
    },
);

async function fetchSuggestions(term: string): Promise<void> {
    abortController?.abort();

    const trimmed = term.trim();
    if (trimmed.length < 1) {
        suggestions.value = [];
        dropdownOpen.value = false;
        isLoading.value = false;
        return;
    }

    abortController = new AbortController();
    isLoading.value = true;

    try {
        const response = await fetch(
            `/catalog/suggestions?q=${encodeURIComponent(trimmed)}`,
            {
                headers: { Accept: 'application/json' },
                signal: abortController.signal,
            },
        );
        if (!response.ok) {
            suggestions.value = [];
            return;
        }
        const payload = (await response.json()) as {
            items?: SearchSuggestion[];
        };
        suggestions.value = payload.items ?? [];
        dropdownOpen.value = true;
    } catch {
        // Ignore aborted or transient fetch errors
    } finally {
        isLoading.value = false;
    }
}

function handleInput(): void {
    if (debounceTimer !== undefined) {
        window.clearTimeout(debounceTimer);
    }

    const current = query.value;
    if (current.trim() === '') {
        suggestions.value = [];
        dropdownOpen.value = false;
        return;
    }

    debounceTimer = window.setTimeout(() => {
        void fetchSuggestions(current);
    }, 200);
}

function handleSubmit(): void {
    dropdownOpen.value = false;
    emit('search', query.value.trim());
}

function clearSearch(): void {
    query.value = '';
    suggestions.value = [];
    dropdownOpen.value = false;
    emit('search', '');
}

function selectSuggestion(item: SearchSuggestion): void {
    dropdownOpen.value = false;
    router.visit(item.url);
}
</script>

<template>
    <div ref="containerRef" class="relative w-full">
        <form
            role="search"
            aria-label="Catalog search"
            class="flex h-11 w-full items-center gap-1 rounded-full border border-input bg-secondary/50 pr-1.5 pl-3.5 transition-colors focus-within:border-ring focus-within:bg-background focus-within:ring-2 focus-within:ring-ring/30"
            @submit.prevent="handleSubmit"
        >
            <label :for="inputId" class="sr-only">Search catalog</label>
            <Search
                class="size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                :id="inputId"
                v-model="query"
                type="search"
                name="q"
                :placeholder="placeholder"
                autocomplete="off"
                class="h-9 min-w-0 flex-1 border-0 bg-transparent px-2 text-sm shadow-none focus-visible:ring-0 [&::-webkit-search-cancel-button]:appearance-none"
                @input="handleInput"
                @focus="
                    if (suggestions.length > 0 && query.trim() !== '') {
                        dropdownOpen = true;
                    }
                "
                @keydown.escape="dropdownOpen = false"
            />
            <button
                v-if="query.length > 0"
                type="button"
                class="inline-flex size-9 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-secondary hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label="Clear search query"
                @click="clearSearch"
            >
                <X class="size-4" aria-hidden="true" />
            </button>
            <Button
                type="submit"
                size="sm"
                class="h-8 shrink-0 rounded-full px-3.5 text-xs font-semibold"
            >
                Search
            </Button>
        </form>

        <!-- Live Search Suggestions Dropdown -->
        <div
            v-if="dropdownOpen && query.trim().length > 0"
            class="absolute inset-x-0 top-full z-50 mt-2 overflow-hidden rounded-2xl border border-border bg-popover text-popover-foreground shadow-xl"
            role="listbox"
            aria-label="Search suggestions"
        >
            <div
                v-if="isLoading && suggestions.length === 0"
                class="px-4 py-3 text-xs text-muted-foreground"
            >
                Searching catalog...
            </div>

            <ul
                v-else-if="suggestions.length > 0"
                class="divide-y divide-border/60"
            >
                <li v-for="item in suggestions" :key="item.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 px-3.5 py-2.5 text-left transition-colors hover:bg-secondary/60"
                        @click="selectSuggestion(item)"
                    >
                        <div
                            class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-secondary/50"
                        >
                            <img
                                v-if="item.imageUrl"
                                :src="item.imageUrl"
                                :alt="item.name"
                                class="size-full object-cover"
                            />
                            <Package
                                v-else
                                class="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-xs font-semibold text-foreground"
                            >
                                {{ item.name }}
                            </p>
                            <p
                                class="truncate text-[11px] text-muted-foreground"
                            >
                                {{ item.categoryName ?? 'Product' }} ·
                                {{ item.inStock ? 'In Stock' : 'Out of Stock' }}
                            </p>
                        </div>

                        <span
                            class="shrink-0 text-xs font-bold text-primary tabular-nums"
                        >
                            {{ item.priceFormatted }}
                        </span>
                    </button>
                </li>
            </ul>

            <div v-else class="px-4 py-3 text-xs text-muted-foreground">
                No matching products found for "{{ query.trim() }}".
            </div>

            <button
                type="button"
                class="flex w-full items-center justify-between border-t border-border bg-secondary/35 px-4 py-2.5 text-xs font-semibold text-primary transition-colors hover:bg-secondary/60"
                @click="handleSubmit"
            >
                <span>View all results for "{{ query.trim() }}"</span>
                <ArrowRight class="size-3.5" aria-hidden="true" />
            </button>
        </div>
    </div>
</template>
