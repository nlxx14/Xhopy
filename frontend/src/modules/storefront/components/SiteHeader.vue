<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, ShieldCheck, User } from '@lucide/vue';
import { computed, ref } from 'vue';
import { CartButton, type CartSummary } from '@modules/cart';
import SearchBox from '@modules/storefront/components/SearchBox.vue';
import BrandLogo from '@shared/components/BrandLogo.vue';
import { Button } from '@shared/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@shared/components/ui/sheet';
import { dashboard, home, login, register } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import type {
    AnnouncementBanner,
    CategorySummary,
} from '@modules/storefront/types';

const props = withDefaults(
    defineProps<{
        announcement?: AnnouncementBanner | null;
        categories?: CategorySummary[];
        cart: CartSummary;
        searchQuery?: string;
        selectedCategory?: string | null;
    }>(),
    {
        announcement: null,
        categories: () => [],
        searchQuery: '',
        selectedCategory: null,
    },
);

const emit = defineEmits<{
    search: [query: string];
    selectCategory: [slug: string | null];
    openCart: [];
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user);
const canAccessAdmin = computed(
    () => user.value?.role === 'staff' || user.value?.role === 'admin',
);
const mobileMenuOpen = ref(false);

function handleMobileCategory(slug: string | null): void {
    mobileMenuOpen.value = false;
    emit('selectCategory', slug);
}
</script>

<template>
    <header
        class="sticky top-0 z-40 w-full border-b border-border bg-background/95 backdrop-blur-sm"
    >
        <!-- Skip link for keyboard accessibility -->
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-primary focus:px-4 focus:py-2.5 focus:text-sm focus:font-semibold focus:text-primary-foreground focus:shadow-lg"
        >
            Skip to main content
        </a>

        <!-- Announcement bar: only rendered when an active announcement exists -->
        <div
            v-if="announcement"
            class="bg-charcoal px-4 py-2 text-center text-xs font-medium text-charcoal-foreground"
        >
            <div
                class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-2"
            >
                <span>{{ announcement.message }}</span>
                <a
                    v-if="announcement.linkText && announcement.linkHref"
                    :href="announcement.linkHref"
                    class="font-semibold text-primary-foreground underline underline-offset-4 hover:opacity-90"
                >
                    {{ announcement.linkText }}
                </a>
            </div>
        </div>

        <!-- Main header bar -->
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div
                class="flex h-16 items-center justify-between gap-3 sm:h-20 sm:gap-6"
            >
                <!-- Left: Mobile menu trigger + Brand Logo -->
                <div class="flex items-center gap-2.5">
                    <Sheet v-model:open="mobileMenuOpen">
                        <SheetTrigger as-child>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-11 lg:hidden"
                                aria-label="Open navigation menu"
                            >
                                <Menu class="size-5" aria-hidden="true" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-80 p-6">
                            <SheetHeader class="text-left">
                                <SheetTitle class="flex items-center">
                                    <BrandLogo size="sm" />
                                </SheetTitle>
                                <SheetDescription>
                                    Browse verified categories and manage your
                                    account.
                                </SheetDescription>
                            </SheetHeader>

                            <nav
                                aria-label="Mobile navigation"
                                class="mt-6 flex flex-col gap-1"
                            >
                                <button
                                    type="button"
                                    class="flex min-h-11 items-center rounded-lg px-3 text-left text-sm font-semibold text-foreground hover:bg-secondary"
                                    @click="handleMobileCategory(null)"
                                >
                                    All Products
                                </button>
                                <button
                                    v-for="cat in props.categories"
                                    :key="cat.id"
                                    type="button"
                                    class="flex min-h-11 items-center justify-between rounded-lg px-3 text-left text-sm font-medium text-foreground hover:bg-secondary"
                                    @click="handleMobileCategory(cat.slug)"
                                >
                                    <span>{{ cat.name }}</span>
                                    <span
                                        v-if="
                                            typeof cat.productCount === 'number'
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ cat.productCount }}
                                    </span>
                                </button>
                            </nav>

                            <div class="mt-8 border-t border-border pt-6">
                                <div v-if="user" class="space-y-2">
                                    <Button
                                        v-if="canAccessAdmin"
                                        as-child
                                        variant="outline"
                                        class="h-11 w-full justify-start gap-2"
                                    >
                                        <Link :href="adminDashboard()">
                                            <ShieldCheck
                                                class="size-4 text-primary"
                                                aria-hidden="true"
                                            />
                                            <span>Admin Back-Office</span>
                                        </Link>
                                    </Button>
                                    <Button
                                        as-child
                                        variant="default"
                                        class="h-11 w-full justify-start gap-2"
                                    >
                                        <Link :href="dashboard()">
                                            <User
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                            <span>My Account</span>
                                        </Link>
                                    </Button>
                                </div>
                                <div v-else class="grid grid-cols-2 gap-2">
                                    <Button
                                        as-child
                                        variant="outline"
                                        class="h-11"
                                    >
                                        <Link :href="login()">Log in</Link>
                                    </Button>
                                    <Button as-child class="h-11">
                                        <Link :href="register()">Register</Link>
                                    </Button>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>

                    <Link
                        :href="home()"
                        class="flex items-center rounded-lg py-1 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <BrandLogo size="lg" subtitle="Official Store" />
                    </Link>
                </div>

                <!-- Center: Desktop Search Box -->
                <div class="hidden max-w-xl flex-1 md:block">
                    <SearchBox
                        :initial-query="searchQuery"
                        input-id="desktop-catalog-search"
                        @search="emit('search', $event)"
                    />
                </div>

                <!-- Right: Account & Cart actions -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <template v-if="user">
                        <Button
                            v-if="canAccessAdmin"
                            as-child
                            variant="outline"
                            class="hidden h-11 gap-2 rounded-full border-primary/40 px-4 text-xs font-semibold sm:inline-flex"
                        >
                            <Link :href="adminDashboard()">
                                <ShieldCheck
                                    class="size-4 text-primary"
                                    aria-hidden="true"
                                />
                                <span>Admin</span>
                            </Link>
                        </Button>
                        <Button
                            as-child
                            variant="ghost"
                            class="hidden h-11 gap-2 rounded-full px-4 sm:inline-flex"
                        >
                            <Link :href="dashboard()">
                                <User class="size-4" aria-hidden="true" />
                                <span>Account</span>
                            </Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button
                            as-child
                            variant="ghost"
                            class="hidden h-11 rounded-full px-4 text-sm font-medium sm:inline-flex"
                        >
                            <Link :href="login()">Log in</Link>
                        </Button>
                        <Button
                            as-child
                            variant="default"
                            class="hidden h-11 rounded-full px-4 text-sm font-semibold lg:inline-flex"
                        >
                            <Link :href="register()">Register</Link>
                        </Button>
                    </template>

                    <CartButton :cart="cart" @open-cart="emit('openCart')" />
                </div>
            </div>

            <!-- Mobile Search Row -->
            <div class="pb-3 md:hidden">
                <SearchBox
                    :initial-query="searchQuery"
                    input-id="mobile-catalog-search"
                    @search="emit('search', $event)"
                />
            </div>
        </div>

        <!-- Optional Category Navigation Strip -->
        <nav
            v-if="categories.length > 0"
            aria-label="Product categories"
            class="hidden border-t border-border/70 bg-secondary/30 lg:block"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
            >
                <ul class="flex flex-wrap items-center gap-1 py-1.5">
                    <li>
                        <button
                            type="button"
                            :class="[
                                'inline-flex min-h-9 items-center rounded-full px-3.5 text-xs font-semibold transition-colors',
                                !selectedCategory
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-foreground hover:bg-secondary',
                            ]"
                            @click="emit('selectCategory', null)"
                        >
                            All Categories
                        </button>
                    </li>
                    <li v-for="cat in categories" :key="cat.id">
                        <button
                            type="button"
                            :class="[
                                'inline-flex min-h-9 items-center rounded-full px-3.5 text-xs font-medium transition-colors',
                                selectedCategory === cat.slug
                                    ? 'bg-primary font-semibold text-primary-foreground'
                                    : 'text-foreground hover:bg-secondary',
                            ]"
                            @click="emit('selectCategory', cat.slug)"
                        >
                            {{ cat.name }}
                        </button>
                    </li>
                </ul>

                <div
                    class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                >
                    <ShieldCheck
                        class="size-3.5 text-primary"
                        aria-hidden="true"
                    />
                    <span>Server-verified pricing &amp; stock</span>
                </div>
            </div>
        </nav>
    </header>
</template>
