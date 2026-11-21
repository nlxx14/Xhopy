<?php

namespace Database\Seeders;

use App\Domain\Catalog\Enums\CampaignPlacement;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\CampaignContent;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Development-only catalog fixtures, persisted so listing, filters, sorting, pagination and
 * the Daily Interest module can be exercised against a real database.
 *
 * Every record is explicitly labeled "[DEMO]" (engineering rule #4: no fabricated facts) and the
 * seeder refuses to run in production.
 *
 *   php artisan db:seed --class=CatalogDemoSeeder
 */
class CatalogDemoSeeder extends Seeder
{
    /**
     * @var list<array{slug: string, name: string, description: string}>
     */
    private const CATEGORIES = [
        ['slug' => 'demo-audio', 'name' => '[DEMO] Audio & Acoustics', 'description' => 'Demo category: headphones, DACs and monitors.'],
        ['slug' => 'demo-workspace', 'name' => '[DEMO] Workspace & Desk', 'description' => 'Demo category: lighting, keyboards and stands.'],
        ['slug' => 'demo-carry', 'name' => '[DEMO] Everyday Carry', 'description' => 'Demo category: packs, pouches and organizers.'],
        ['slug' => 'demo-home', 'name' => '[DEMO] Home Living', 'description' => 'Demo category: drinkware and ambient essentials (no products yet).'],
    ];

    /**
     * [category slug, name, sku, price, compare-at price, stock, featured rank].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string|null, 5: int, 6: int|null}>
     */
    private const PRODUCTS = [
        ['demo-audio', 'Studio Headphones', 'DEMO-AUD-001', '6490.00', '7490.00', 12, 1],
        ['demo-audio', 'Balanced Desktop DAC & Amp', 'DEMO-AUD-002', '4950.00', null, 8, 3],
        ['demo-audio', 'Open-Back Monitor Headphones', 'DEMO-AUD-003', '8200.00', null, 0, null],
        ['demo-audio', 'Desktop Monitor Speakers', 'DEMO-AUD-004', '5600.00', '6200.00', 5, null],
        ['demo-workspace', 'Aluminum Monitor Light Bar', 'DEMO-WRK-001', '2850.00', null, 20, 2],
        ['demo-workspace', 'Mechanical Keyboard 75%', 'DEMO-WRK-002', '3990.00', null, 14, null],
        ['demo-workspace', 'Adjustable Laptop Stand', 'DEMO-WRK-003', '1490.00', '1790.00', 30, null],
        ['demo-workspace', 'Desk Cable Organizer Set', 'DEMO-WRK-004', '390.00', null, 60, null],
        ['demo-workspace', 'Ergonomic Wrist Rest', 'DEMO-WRK-005', '650.00', null, 0, null],
        ['demo-workspace', 'Large Desk Mat', 'DEMO-WRK-006', '1190.00', null, 25, null],
        ['demo-carry', 'Weatherproof Tech Sling 8L', 'DEMO-EDC-001', '3400.00', null, 0, null],
        ['demo-carry', 'Commuter Backpack 20L', 'DEMO-EDC-002', '4200.00', '4800.00', 9, null],
        ['demo-carry', 'Travel Cable Pouch', 'DEMO-EDC-003', '690.00', null, 40, null],
        ['demo-carry', 'Compact Passport Wallet', 'DEMO-EDC-004', '890.00', null, 18, null],
        ['demo-carry', 'Packing Cube Trio', 'DEMO-EDC-005', '1250.00', null, 22, null],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('CatalogDemoSeeder must never run in production.');

            return;
        }

        $categories = [];
        foreach (self::CATEGORIES as $position => $data) {
            $categories[$data['slug']] = Category::query()->updateOrCreate(
                ['slug' => $data['slug']],
                ['name' => $data['name'], 'description' => $data['description'], 'sort_order' => $position, 'is_active' => true],
            );
        }

        foreach (self::PRODUCTS as $index => [$categorySlug, $name, $sku, $price, $compareAt, $stock, $rank]) {
            Product::query()->updateOrCreate(
                ['sku' => $sku],
                [
                    'category_id' => $categories[$categorySlug]->id,
                    'name' => '[DEMO] '.$name,
                    'slug' => 'demo-'.Str::slug($name),
                    'short_description' => 'Demo product used to verify catalog behavior. Not a real item.',
                    'description' => "This is a clearly labeled demo product for development.\n\nIt does not represent a real item, price, or stock level.",
                    'price' => $price,
                    'compare_at_price' => $compareAt,
                    'currency' => 'PHP',
                    'stock_quantity' => $stock,
                    'status' => ProductStatus::Active,
                    'is_featured' => $rank !== null,
                    'featured_rank' => $rank,
                    'published_at' => now()->subHours(count(self::PRODUCTS) - $index),
                ],
            );
        }

        CampaignContent::query()->updateOrCreate(
            ['placement' => CampaignPlacement::DailyInterestFeatured, 'title' => '[DEMO] Designing a distraction-free desk'],
            [
                'tag' => '[DEMO] Workspace Spotlight',
                'summary' => 'Demo editorial banner pointing at the demo workspace category.',
                'cta_label' => 'View Workspace Collection',
                'href' => '/products?category=demo-workspace',
                'sort_order' => 0,
                'is_active' => true,
            ],
        );

        $tiles = [
            ['[DEMO] Audio Guide', '[DEMO] Choosing studio headphones', '/products?category=demo-audio', 'Browse Audio'],
            ['[DEMO] Commute Gear', '[DEMO] Organize your daily carry', '/products?category=demo-carry', 'Explore Everyday Carry'],
        ];

        foreach ($tiles as $position => [$tag, $title, $href, $cta]) {
            CampaignContent::query()->updateOrCreate(
                ['placement' => CampaignPlacement::DailyInterestTile, 'title' => $title],
                [
                    'tag' => $tag,
                    'summary' => 'Demo campaign tile for development only.',
                    'cta_label' => $cta,
                    'href' => $href,
                    'sort_order' => $position,
                    'is_active' => true,
                ],
            );
        }

        if (Schema::hasTable('stock_movements')) {
            app(InventoryService::class)->reconcileAll();
        }
    }
}
