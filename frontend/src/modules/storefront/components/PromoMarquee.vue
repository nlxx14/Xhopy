<script setup lang="ts">
/**
 * Slim brand banner with continuously scrolling text.
 * The second copy is aria-hidden so assistive tech reads the message once.
 * Motion keeps running on hover and is disabled for users who prefer reduced motion.
 */
const repeats = 6;
</script>

<template>
    <section
        aria-label="XClusive Deals — Explore, Shop, Enjoy"
        class="overflow-hidden border-b border-border/70 bg-secondary/30"
    >
        <div
            class="flex w-max animate-marquee items-center py-2 motion-reduce:animate-none"
        >
            <template v-for="copy in 2" :key="copy">
                <div
                    class="flex shrink-0 items-center"
                    :aria-hidden="copy === 2 ? 'true' : undefined"
                >
                    <div
                        v-for="n in repeats"
                        :key="`${copy}-${n}`"
                        class="flex shrink-0 items-center gap-5 px-6 sm:gap-6 sm:px-8"
                    >
                        <span
                            class="flex items-center gap-1.5 font-serif text-foreground"
                        >
                            <span
                                class="text-4xl leading-none font-normal text-primary"
                                aria-hidden="true"
                                >X</span
                            >
                            <span
                                class="flex flex-col items-start justify-center gap-0.5 leading-none"
                            >
                                <span class="text-[15px] tracking-wide"
                                    >Clusive Deals</span
                                >
                                <span
                                    class="text-[22px] font-semibold tracking-wide"
                                    >Hopy</span
                                >
                            </span>
                        </span>

                        <span
                            class="h-6 w-0.5 rounded-full bg-foreground/70"
                            aria-hidden="true"
                        />

                        <span
                            class="flex items-center gap-3 font-serif text-sm tracking-wide whitespace-nowrap text-foreground sm:gap-4 sm:text-base"
                        >
                            <span>EXPLORE</span>
                            <span class="text-primary" aria-hidden="true"
                                >•</span
                            >
                            <span>SHOP</span>
                            <span class="text-primary" aria-hidden="true"
                                >•</span
                            >
                            <span>ENJOY</span>
                        </span>
                    </div>
                </div>
            </template>
        </div>
    </section>
</template>
