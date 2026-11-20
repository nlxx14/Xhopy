<script setup lang="ts">
import { ArrowUpDown } from '@lucide/vue';
import { CustomDropdown } from '@shared/components/ui/custom-dropdown';
import type { SortOption } from '@modules/catalog/types';

defineProps<{
    options: SortOption[];
    id?: string;
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <CustomDropdown
        :id="id ?? 'catalog-sort'"
        v-model="model"
        :options="options"
        label="Sort by"
        aria-label="Sort products by"
    >
        <template #icon>
            <ArrowUpDown
                class="size-3.5 shrink-0 text-primary"
                aria-hidden="true"
            />
        </template>
    </CustomDropdown>
</template>
