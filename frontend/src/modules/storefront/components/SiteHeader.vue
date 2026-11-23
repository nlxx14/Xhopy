<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Briefcase,
    Check,
    ChevronDown,
    Headphones,
    Home,
    Layers,
    LayoutGrid,
    Menu,
    Monitor,
    ShieldCheck,
    User,
} from '@lucide/vue';
import { computed, ref, type Component } from 'vue';
import { CartButton, type CartSummary } from '@modules/cart';
import SearchBox from '@modules/storefront/components/SearchBox.vue';
import BrandLogo from '@shared/components/BrandLogo.vue';
import { Button } from '@shared/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@shared/components/ui/dropdown-menu';
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
import { index as productsIndex } from '@/routes/products';
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

const activeCategory = computed<CategorySummary | null>(() => {
    if (!props.selectedCategory) {
        return null;
    }

    return (
        props.categories.find((cat) => cat.slug === props.selectedCategory) ??
        null
    );
});

const totalProductCount = computed<number | null>(() => {
    const counts = props.categories
        .map((cat) => cat.productCount)
        .filter((count): count is number => typeof count === 'number');

    if (counts.length === 0) {
        return null;
    }

    return counts.reduce((sum, count) => sum + count, 0);
});

function resolveCategoryIcon(category: CategorySummary): Component {
    const haystack = `${category.slug} ${category.name}`.toLowerCase();

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
}

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

                    <!-- Desktop Categories Dropdown Pill next to Brand Logo -->
                    <template v-if="props.categories.length > 0">
                        <span
                            class="hidden h-5 w-px bg-border/80 lg:block"
                            aria-hidden="true"
                        />

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    :class="[
                                        'hidden h-10 items-center gap-2 rounded-full border px-3.5 text-xs font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none lg:inline-flex',
                                        activeCategory
                                            ? 'border-primary/60 bg-brand-subtle/40 text-foreground'
                                            : 'border-border bg-secondary/40 text-foreground hover:border-primary/40 hover:bg-secondary/70',
                                    ]"
                                    aria-label="Browse product categories"
                                >
                                    <component
                                        :is="
                                            activeCategory
                                                ? resolveCategoryIcon(
                                                      activeCategory,
                                                  )
                                                : LayoutGrid
                                        "
                                        class="size-3.5 text-primary"
                                        aria-hidden="true"
                                    />
                                    <span class="max-w-36 truncate">
                                        {{
                                            activeCategory
                                                ? activeCategory.name
                                                : 'Categories'
                                        }}
                                    </span>
                                    <ChevronDown
                                        class="size-3.5 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                </button>
                            </DropdownMenuTrigger>

                            <DropdownMenuContent
                                align="start"
                                :side-offset="8"
                                class="w-64 rounded-2xl border-border/80 p-1.5 shadow-lg"
                            >
                                <DropdownMenuLabel
                                    class="px-2.5 py-1.5 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Browse Departments
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />

                                <DropdownMenuItem
                                    class="flex cursor-pointer items-center justify-between gap-2 rounded-xl px-2.5 py-2 text-xs font-medium"
                                    @select="emit('selectCategory', null)"
                                >
                                    <span class="flex items-center gap-2.5">
                                        <Layers
                                            :class="[
                                                'size-4',
                                                !selectedCategory
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground',
                                            ]"
                                            aria-hidden="true"
                                        />
                                        <span
                                            :class="
                                                !selectedCategory
                                                    ? 'font-semibold text-foreground'
                                                    : 'text-foreground'
                                            "
                                        >
                                            All Categories
                                        </span>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <span
                                            v-if="
                                                typeof totalProductCount ===
                                                'number'
                                            "
                                            class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                        >
                                            {{ totalProductCount }}
                                        </span>
                                        <Check
                                            v-if="!selectedCategory"
                                            class="size-3.5 text-primary"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </DropdownMenuItem>

                                <DropdownMenuItem
                                    v-for="cat in props.categories"
                                    :key="cat.id"
                                    class="flex cursor-pointer items-center justify-between gap-2 rounded-xl px-2.5 py-2 text-xs font-medium"
                                    @select="emit('selectCategory', cat.slug)"
                                >
                                    <span
                                        class="flex min-w-0 items-center gap-2.5"
                                    >
                                        <component
                                            :is="resolveCategoryIcon(cat)"
                                            :class="[
                                                'size-4 shrink-0',
                                                selectedCategory === cat.slug
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground',
                                            ]"
                                            aria-hidden="true"
                                        />
                                        <span
                                            :class="[
                                                'truncate',
                                                selectedCategory === cat.slug
                                                    ? 'font-semibold text-foreground'
                                                    : 'text-foreground',
                                            ]"
                                        >
                                            {{ cat.name }}
                                        </span>
                                    </span>
                                    <span
                                        class="flex shrink-0 items-center gap-1.5"
                                    >
                                        <span
                                            v-if="
                                                typeof cat.productCount ===
                                                'number'
                                            "
                                            class="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                        >
                                            {{ cat.productCount }}
                                        </span>
                                        <Check
                                            v-if="selectedCategory === cat.slug"
                                            class="size-3.5 text-primary"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </DropdownMenuItem>

                                <DropdownMenuSeparator />

                                <DropdownMenuItem
                                    as-child
                                    class="cursor-pointer rounded-xl px-2.5 py-2 text-xs font-semibold text-primary"
                                >
                                    <Link
                                        :href="productsIndex.url()"
                                        class="flex w-full items-center justify-between"
                                    >
                                        <span>View Full Catalog</span>
                                        <ArrowRight
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </template>
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
    </header>
</template>
