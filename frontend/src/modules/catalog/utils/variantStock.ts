import type { ProductSummary } from '@modules/catalog/types';

export function hasCustomVariantStocks(
    product: Pick<ProductSummary, 'variantStocks'>,
): boolean {
    return (
        product.variantStocks !== null &&
        product.variantStocks !== undefined &&
        Object.keys(product.variantStocks).length > 0
    );
}

export function resolveVariantStock(
    product: Pick<
        ProductSummary,
        'inStock' | 'stockQuantity' | 'variantStocks'
    >,
    color: string | null | undefined,
    size: string | null | undefined,
): number {
    if (!hasCustomVariantStocks(product) || !product.variantStocks) {
        return product.stockQuantity ?? (product.inStock ? 20 : 0);
    }

    const key = `${(color ?? '').trim()}|${(size ?? '').trim()}`;
    if (Object.prototype.hasOwnProperty.call(product.variantStocks, key)) {
        return Math.max(0, Number(product.variantStocks[key] ?? 0) || 0);
    }

    const lowerKey = key.toLowerCase();
    for (const [existingKey, qty] of Object.entries(product.variantStocks)) {
        if (existingKey.toLowerCase() === lowerKey) {
            return Math.max(0, Number(qty ?? 0) || 0);
        }
    }

    return 0;
}

export function isOptionOptionInStock(
    product: Pick<
        ProductSummary,
        | 'inStock'
        | 'stockQuantity'
        | 'variantStocks'
        | 'availableColors'
        | 'availableSizes'
    >,
    dimension: 'color' | 'size',
    optionValue: string,
): boolean {
    if (!hasCustomVariantStocks(product)) {
        return product.inStock;
    }

    if (dimension === 'color') {
        const sizes =
            product.availableSizes && product.availableSizes.length > 0
                ? product.availableSizes
                : [''];
        return sizes.some(
            (size) => resolveVariantStock(product, optionValue, size) > 0,
        );
    }

    const colors =
        product.availableColors && product.availableColors.length > 0
            ? product.availableColors
            : [''];
    return colors.some(
        (color) => resolveVariantStock(product, color, optionValue) > 0,
    );
}

export function pickInitialVariant(
    product: Pick<
        ProductSummary,
        | 'inStock'
        | 'stockQuantity'
        | 'variantStocks'
        | 'availableColors'
        | 'availableSizes'
    >,
): { color: string | null; size: string | null } {
    const colors = product.availableColors ?? [];
    const sizes = product.availableSizes ?? [];
    const defaultColor = colors[0] ?? null;
    const defaultSize = sizes[0] ?? null;

    if (!hasCustomVariantStocks(product)) {
        return { color: defaultColor, size: defaultSize };
    }

    const candidateColors = colors.length > 0 ? colors : [''];
    const candidateSizes = sizes.length > 0 ? sizes : [''];

    for (const color of candidateColors) {
        for (const size of candidateSizes) {
            if (resolveVariantStock(product, color, size) > 0) {
                return {
                    color: color !== '' ? color : null,
                    size: size !== '' ? size : null,
                };
            }
        }
    }

    return { color: defaultColor, size: defaultSize };
}
