<script setup lang="ts">
import { ImageOff } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { ProductImage } from '@modules/catalog/types';

const props = defineProps<{
    images: ProductImage[];
    productName: string;
}>();

const usable = computed(() =>
    props.images.filter((image) => image.url !== null && image.url !== ''),
);
const selected = ref(0);

watch(usable, () => {
    selected.value = 0;
});

const current = computed(() => usable.value[selected.value] ?? null);
</script>

<template>
    <div class="space-y-3">
        <!-- Fixed aspect ratio reserves space and prevents layout shift -->
        <div
            class="relative aspect-square w-full overflow-hidden rounded-2xl border border-border bg-secondary/70"
        >
            <img
                v-if="current"
                :key="current.id"
                :src="current.url ?? undefined"
                :alt="current.alt"
                width="800"
                height="800"
                decoding="async"
                class="size-full object-cover"
            />
            <div
                v-else
                role="img"
                :aria-label="`No photo available for ${productName}`"
                class="flex size-full flex-col items-center justify-center gap-2 p-6 text-center text-muted-foreground"
            >
                <ImageOff class="size-10 opacity-60" aria-hidden="true" />
                <span class="text-sm font-medium">No photo available</span>
            </div>
        </div>

        <ul
            v-if="usable.length > 1"
            class="grid grid-cols-5 gap-2"
            aria-label="Product photos"
        >
            <li v-for="(image, index) in usable" :key="image.id">
                <button
                    type="button"
                    class="relative block aspect-square w-full overflow-hidden rounded-lg border-2 bg-secondary/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        index === selected
                            ? 'border-primary'
                            : 'border-transparent hover:border-border'
                    "
                    :aria-label="`Show photo ${index + 1} of ${usable.length}`"
                    :aria-pressed="index === selected"
                    @click="selected = index"
                >
                    <img
                        :src="image.url ?? undefined"
                        alt=""
                        width="160"
                        height="160"
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover"
                    />
                </button>
            </li>
        </ul>
    </div>
</template>
