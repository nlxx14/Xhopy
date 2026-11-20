<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Check, ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import {
    SelectContent,
    SelectIcon,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectPortal,
    SelectRoot,
    SelectTrigger,
    SelectValue,
    SelectViewport,
} from 'reka-ui';
import { cn } from '@shared/lib/utils';

export interface CustomDropdownOption {
    value: string;
    label: string;
    description?: string;
    badge?: string | number;
    disabled?: boolean;
}

const props = withDefaults(
    defineProps<{
        options: CustomDropdownOption[];
        id?: string;
        label?: string;
        placeholder?: string;
        ariaLabel?: string;
        disabled?: boolean;
        align?: 'start' | 'center' | 'end';
        class?: HTMLAttributes['class'];
    }>(),
    {
        id: undefined,
        label: undefined,
        placeholder: 'Select an option',
        ariaLabel: undefined,
        disabled: false,
        align: 'end',
        class: undefined,
    },
);

const model = defineModel<string>({ required: true });

const selectedOption = computed(() =>
    props.options.find((option) => option.value === model.value),
);
</script>

<template>
    <SelectRoot v-model="model" :disabled="disabled">
        <SelectTrigger
            :id="id"
            data-slot="custom-dropdown-trigger"
            :aria-label="ariaLabel ?? label ?? placeholder"
            :class="
                cn(
                    'group/trigger inline-flex h-11 min-w-[11.5rem] items-center justify-between gap-2.5 rounded-xl border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-2xs transition-all duration-150 outline-none hover:border-primary/45 hover:bg-secondary/35 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/35 disabled:cursor-not-allowed disabled:opacity-50 data-[state=open]:border-primary data-[state=open]:ring-[3px] data-[state=open]:ring-ring/25',
                    props.class,
                )
            "
        >
            <span class="flex min-w-0 items-center gap-2 truncate">
                <slot name="icon" />
                <span
                    v-if="label"
                    class="text-xs font-medium whitespace-nowrap text-muted-foreground"
                >
                    {{ label }}:
                </span>
                <SelectValue
                    :placeholder="placeholder"
                    class="truncate font-semibold text-foreground"
                >
                    {{ selectedOption?.label ?? placeholder }}
                </SelectValue>
            </span>

            <SelectIcon as-child>
                <span
                    class="flex size-6 shrink-0 items-center justify-center rounded-lg bg-secondary/80 text-muted-foreground transition-all duration-200 group-hover/trigger:bg-brand-subtle/70 group-hover/trigger:text-primary group-data-[state=open]/trigger:rotate-180 group-data-[state=open]/trigger:bg-brand-subtle group-data-[state=open]/trigger:text-primary"
                >
                    <ChevronDown class="size-3.5" aria-hidden="true" />
                </span>
            </SelectIcon>
        </SelectTrigger>

        <SelectPortal>
            <SelectContent
                data-slot="custom-dropdown-content"
                position="popper"
                :align="align"
                :side-offset="6"
                class="relative z-50 max-h-(--reka-select-content-available-height) min-w-[var(--reka-select-trigger-width)] overflow-x-hidden overflow-y-auto rounded-2xl border border-border/90 bg-popover p-1.5 text-popover-foreground shadow-xl ring-1 ring-black/5 data-[side=bottom]:slide-in-from-top-2 data-[side=top]:slide-in-from-bottom-2 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
            >
                <SelectViewport class="space-y-0.5">
                    <SelectItem
                        v-for="option in options"
                        :key="option.value"
                        :value="option.value"
                        :disabled="option.disabled"
                        data-slot="custom-dropdown-item"
                        class="group/item relative flex min-h-10 w-full cursor-pointer items-center justify-between gap-3 rounded-xl px-3 py-2 pr-9 text-sm font-medium text-foreground transition-colors outline-none select-none focus:bg-brand-subtle/60 focus:text-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-45 data-[state=checked]:bg-brand-subtle/45 data-[state=checked]:font-semibold data-[state=checked]:text-primary"
                    >
                        <div class="flex min-w-0 flex-1 flex-col">
                            <SelectItemText class="truncate">
                                {{ option.label }}
                            </SelectItemText>
                            <span
                                v-if="option.description"
                                class="truncate text-xs font-normal text-muted-foreground"
                            >
                                {{ option.description }}
                            </span>
                        </div>

                        <span
                            v-if="
                                option.badge !== undefined &&
                                option.badge !== null
                            "
                            class="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-semibold text-muted-foreground group-data-[state=checked]/item:bg-primary/15 group-data-[state=checked]/item:text-primary"
                        >
                            {{ option.badge }}
                        </span>

                        <span
                            class="absolute right-2.5 flex size-5 items-center justify-center"
                        >
                            <SelectItemIndicator
                                class="flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-2xs motion-safe:animate-in motion-safe:zoom-in-75"
                            >
                                <Check
                                    class="size-3 stroke-[2.75]"
                                    aria-hidden="true"
                                />
                            </SelectItemIndicator>
                        </span>
                    </SelectItem>
                </SelectViewport>
            </SelectContent>
        </SelectPortal>
    </SelectRoot>
</template>
