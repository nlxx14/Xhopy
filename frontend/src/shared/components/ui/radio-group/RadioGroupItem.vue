<script setup lang="ts">
import type { RadioGroupItemProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import {
    RadioGroupIndicator,
    RadioGroupItem,
    useForwardProps,
} from 'reka-ui';
import { cn } from '@shared/lib/utils';

const props = defineProps<
    RadioGroupItemProps & { class?: HTMLAttributes['class'] }
>();

const delegatedProps = reactiveOmit(props, 'class');
const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
    <RadioGroupItem
        data-slot="radio-group-item"
        v-bind="forwardedProps"
        :class="
            cn(
                'group/radio relative aspect-square size-5 shrink-0 rounded-full border-2 border-input bg-background text-primary shadow-2xs transition-all duration-150 outline-none hover:border-primary/60 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/35 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:border-primary data-[state=checked]:bg-brand-subtle/60 dark:bg-input/30',
                props.class,
            )
        "
    >
        <RadioGroupIndicator
            data-slot="radio-group-indicator"
            class="flex size-full items-center justify-center"
        >
            <span
                class="size-2.5 rounded-full bg-primary shadow-2xs transition-transform duration-150 motion-safe:animate-in motion-safe:zoom-in-50"
            />
        </RadioGroupIndicator>
    </RadioGroupItem>
</template>
