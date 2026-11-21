import { expect, test } from '@playwright/test';

const viewports = [
    { name: 'mobile', width: 375, height: 812 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1280, height: 800 },
] as const;

test.describe('XHopy Batch 2 Storefront Shell & Design System', () => {
    for (const viewport of viewports) {
        test(`default storefront renders cleanly with no horizontal overflow or runtime errors on ${viewport.name} (${viewport.width}px)`, async ({
            page,
        }) => {
            const errors: string[] = [];
            page.on('pageerror', (err) => errors.push(err.message));
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    errors.push(msg.text());
                }
            });

            await page.setViewportSize({
                width: viewport.width,
                height: viewport.height,
            });

            const response = await page.goto('/');
            expect(response?.ok()).toBe(true);

            await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
            await expect(
                page.getByText('Currency: PHP (₱ Philippine Peso)'),
            ).toBeVisible();

            // Production/default shell must not show fake products or Daily Interest
            await expect(
                page.getByText('No product categories published yet'),
            ).toBeVisible();
            await expect(
                page.getByText('Catalog is ready for products'),
            ).toBeVisible();
            await expect(
                page.getByRole('heading', { name: 'Daily Interest' }),
            ).toHaveCount(0);

            const hasOverflow = await page.evaluate(
                () =>
                    document.documentElement.scrollWidth >
                    document.documentElement.clientWidth,
            );
            expect(hasOverflow).toBe(false);
            expect(errors).toEqual([]);
        });

        test(`demo preview storefront renders all modules without overflow on ${viewport.name} (${viewport.width}px)`, async ({
            page,
        }) => {
            const errors: string[] = [];
            page.on('pageerror', (err) => errors.push(err.message));

            await page.setViewportSize({
                width: viewport.width,
                height: viewport.height,
            });

            const response = await page.goto('/?preview_demo=1');
            expect(response?.ok()).toBe(true);

            await expect(
                page.getByText('Development Demo Preview Active'),
            ).toBeVisible();
            await expect(
                page.getByRole('heading', { name: 'Top Products' }),
            ).toBeVisible();
            await expect(
                page.getByRole('heading', { name: 'Daily Interest' }),
            ).toBeVisible();

            const hasOverflow = await page.evaluate(
                () =>
                    document.documentElement.scrollWidth >
                    document.documentElement.clientWidth,
            );
            expect(hasOverflow).toBe(false);
            expect(errors).toEqual([]);
        });
    }

    test('keyboard navigation focuses skip link and interactive header controls cleanly', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto('/?preview_demo=1');

        await page.keyboard.press('Tab');
        const skipLink = page.getByRole('link', {
            name: 'Skip to main content',
        });
        await expect(skipLink).toBeFocused();

        // Tab through announcement link and brand link to reach desktop search input
        await page.keyboard.press('Tab');
        await expect(
            page.getByRole('link', { name: 'Explore Top Products' }),
        ).toBeFocused();

        await page.keyboard.press('Tab');
        await expect(
            page.getByRole('link', { name: 'XHopy Official Store' }),
        ).toBeFocused();

        await page.keyboard.press('Tab');
        const searchInput = page.locator('#desktop-catalog-search');
        await expect(searchInput).toBeFocused();
    });

    test('add-to-cart, cart dialog, search, and category filter interactions work', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto('/?preview_demo=1');

        const cartButton = page.getByRole('button', {
            name: /Shopping cart/i,
        });
        await expect(cartButton).toContainText('0');

        const addButton = page.getByRole('button', {
            name: /Add \[DEMO\] XHopy Reference Studio Headphones to cart/i,
        });
        await addButton.click();

        await expect(cartButton).toContainText('1');
        await cartButton.click();

        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible();
        await expect(dialog).toContainText(
            '1 item selected in current session',
        );
        await expect(dialog).toContainText(
            '[DEMO] XHopy Reference Studio Headphones',
        );

        await page.getByRole('button', { name: 'Continue Shopping' }).click();
        await expect(dialog).toBeHidden();

        // Test search filter + clear button without overlap
        const searchInput = page.locator('#desktop-catalog-search');
        await searchInput.fill('DAC');
        await page
            .locator('#desktop-catalog-search')
            .locator('..')
            .getByRole('button', { name: 'Search', exact: true })
            .click();

        await expect(
            page.getByRole('heading', {
                level: 3,
                name: '[DEMO] Compact Balanced Desktop DAC & Amp',
            }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', {
                level: 3,
                name: '[DEMO] XHopy Reference Studio Headphones',
            }),
        ).toHaveCount(0);

        await page
            .getByRole('button', { name: 'Clear search query' })
            .first()
            .click();
        await expect(
            page.getByRole('heading', {
                level: 3,
                name: '[DEMO] XHopy Reference Studio Headphones',
            }),
        ).toBeVisible();

        // Test category filter button
        await page
            .getByRole('button', { name: /Workspace & Desk/i })
            .first()
            .click();
        await expect(
            page.getByRole('heading', {
                level: 3,
                name: '[DEMO] Precision Aluminum Monitor Light Bar',
            }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', {
                level: 3,
                name: '[DEMO] XHopy Reference Studio Headphones',
            }),
        ).toHaveCount(0);
    });

    test('mobile navigation drawer opens cleanly at 375px', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('/?preview_demo=1');

        await page
            .getByRole('button', { name: 'Open navigation menu' })
            .click();
        const drawer = page.getByRole('dialog');
        await expect(drawer).toBeVisible();
        await expect(
            drawer.getByRole('button', { name: 'All Products' }),
        ).toBeVisible();
    });

    test('catalog listing, login, and health readiness endpoints work with security headers', async ({
        page,
        request,
    }) => {
        const healthResponse = await request.get('/health/ready');
        expect(healthResponse.ok()).toBe(true);
        expect(healthResponse.headers()['x-content-type-options']).toBe(
            'nosniff',
        );
        expect(healthResponse.headers()['x-frame-options']).toBe('SAMEORIGIN');
        expect(healthResponse.headers()['x-request-id']).toBeTruthy();

        for (const viewport of viewports) {
            await page.setViewportSize({
                width: viewport.width,
                height: viewport.height,
            });
            const catalogResponse = await page.goto('/products');
            expect(catalogResponse?.ok()).toBe(true);

            const hasOverflow = await page.evaluate(
                () =>
                    document.documentElement.scrollWidth >
                    document.documentElement.clientWidth,
            );
            expect(hasOverflow).toBe(false);
        }

        const loginResponse = await page.goto('/login');
        expect(loginResponse?.ok()).toBe(true);
        await expect(page.getByLabel(/Email address/i)).toBeVisible();
    });
});
