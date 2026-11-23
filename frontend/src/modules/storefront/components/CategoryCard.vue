<script setup lang="ts">
import {
    ArrowUpRight,
    Briefcase,
    Headphones,
    Home,
    Layers,
    Monitor,
} from '@lucide/vue';
import { computed, type Component } from 'vue';
import type { CategorySummary } from '@modules/storefront/types';

const props = defineProps<{
    category: CategorySummary;
    active?: boolean;
}>();

const emit = defineEmits<{
    select: [slug: string];
}>();

const categoryIcon = computed<Component>(() => {
    const haystack =
        `${props.category.slug} ${props.category.name}`.toLowerCase();

    if (haystack.includes('audio') || haystack.includes('acoustic')) {
        return Headphones;
    }
    if (haystack.includes('workspace') || haystack.includes('desk')) {
        return Monitor;
    }
    if (haystack.includes('carry') || haystack.includes('edc')) {
        return Briefcase;
    }
    if (haystack.includes('home') || haystack.includes('living')) {
        return Home;
    }

    return Layers;
});
</script>

<template>
    <button
        type="button"
        :class="[
            'group flex min-h-28 w-full flex-col justify-between rounded-2xl border p-4 text-left transition-all focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
            active
                ? 'border-primary bg-brand-subtle/50 shadow-2xs'
                : 'border-border/80 bg-card hover:border-primary/40 hover:bg-secondary/20',
        ]"
        :aria-pressed="active"
        @click="emit('select', category.slug)"
    >
        <div class="flex w-full items-center justify-between gap-2">
            <span
                :class="[
                    'inline-flex size-9 items-center justify-center rounded-xl transition-colors',
                    active
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-secondary text-foreground group-hover:bg-brand-subtle group-hover:text-brand-foreground',
                ]"
            >
                <component
                    :is="categoryIcon"
                    class="size-4"
                    aria-hidden="true"
                />
            </span>

            <div class="flex items-center gap-1.5">
                <span
                    v-if="typeof category.productCount === 'number'"
                    class="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
                >
                    {{ category.productCount }}
                    {{ category.productCount === 1 ? 'item' : 'items' }}
                </span>
                <ArrowUpRight
                    class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-primary"
                    aria-hidden="true"
                />
            </div>
        </div>

        <div class="mt-4">
            <h3
                class="line-clamp-1 text-sm font-semibold text-foreground transition-colors group-hover:text-primary"
            >
                {{ category.name }}
            </h3>
            <p
                v-if="category.description"
                class="mt-0.5 line-clamp-1 text-xs text-muted-foreground"
            >
                {{ category.description }}
            </p>
        </div>
    </button>
</template>
