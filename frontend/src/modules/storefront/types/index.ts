import type { CartSummary } from '@modules/cart';
import type { ProductSummary } from '@modules/catalog';

export interface AnnouncementBanner {
    id: string;
    message: string;
    linkText?: string | null;
    linkHref?: string | null;
}

export interface CategorySummary {
    id: number | string;
    name: string;
    slug: string;
    description?: string | null;
    productCount?: number | null;
    imageUrl?: string | null;
}

export interface HeroCampaign {
    badgeText?: string | null;
    eyebrow?: string | null;
    title: string;
    subtitle: string;
    primaryCtaLabel: string;
    primaryCtaHref: string;
    secondaryCtaLabel?: string | null;
    secondaryCtaHref?: string | null;
}

export interface DailyInterestItem {
    id: string;
    tag: string;
    title: string;
    summary: string;
    ctaLabel: string;
    href: string;
    imageUrl?: string | null;
    imageAlt?: string | null;
}

export interface DailyInterestSection {
    featured: DailyInterestItem;
    tiles: DailyInterestItem[];
}

export interface StorefrontHomeProps {
    announcement?: AnnouncementBanner | null;
    hero?: HeroCampaign | null;
    categories: CategorySummary[];
    topProducts: ProductSummary[];
    dailyDiscoverProducts?: ProductSummary[];
    dailyInterest?: DailyInterestSection | null;
    cartSummary: CartSummary;
    searchQuery?: string;
    selectedCategory?: string | null;
    isDemoPreview?: boolean;
}
