<?php

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return list<string>
 */
function listedNames($response): array
{
    return collect($response->inertiaProps('products'))->pluck('name')->all();
}

test('listing renders an empty catalog without fabricated products', function () {
    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/Index')
            ->has('products', 0)
            ->where('pagination.total', 0)
            ->where('filters.sort', 'featured')
            ->has('sortOptions', 5)
        );
});

test('listing only exposes publicly visible products', function () {
    $visible = Product::factory()->create(['name' => 'Visible Item']);
    Product::factory()->draft()->create(['name' => 'Draft Item']);
    Product::factory()->archived()->create(['name' => 'Archived Item']);
    Product::factory()->scheduled()->create(['name' => 'Scheduled Item']);
    Product::factory()->for(Category::factory()->inactive())->create(['name' => 'Hidden Category Item']);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Visible Item')
            ->where('products.0.slug', $visible->slug)
            ->where('pagination.total', 1)
        );
});

test('product cards never include fabricated ratings or sellers', function () {
    Product::factory()->create();

    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.0.ratingAverage', null)
            ->where('products.0.ratingCount', null)
            ->where('products.0.sellerName', null)
        );
});

test('search matches name, sku, short description and category name', function () {
    $category = Category::factory()->create(['name' => 'Studio Gear']);
    Product::factory()->for($category)->create(['name' => 'Alpha Widget', 'sku' => 'ZZ-001', 'short_description' => 'plain']);
    Product::factory()->create(['name' => 'Beta Gadget', 'sku' => 'QQ-777', 'short_description' => 'plain']);
    Product::factory()->create(['name' => 'Gamma Thing', 'sku' => 'GG-123', 'short_description' => 'ergonomic grip']);

    $byName = $this->get(route('products.index', ['q' => 'alpha']));
    expect(listedNames($byName))->toBe(['Alpha Widget']);

    $bySku = $this->get(route('products.index', ['q' => 'qq-777']));
    expect(listedNames($bySku))->toBe(['Beta Gadget']);

    $byDescription = $this->get(route('products.index', ['q' => 'ergonomic']));
    expect(listedNames($byDescription))->toBe(['Gamma Thing']);

    $byCategory = $this->get(route('products.index', ['q' => 'studio']));
    expect(listedNames($byCategory))->toBe(['Alpha Widget']);
});

test('search requires every term to match', function () {
    Product::factory()->create(['name' => 'Red Mechanical Keyboard']);
    Product::factory()->create(['name' => 'Red Desk Lamp']);

    $response = $this->get(route('products.index', ['q' => 'red keyboard']));

    expect(listedNames($response))->toBe(['Red Mechanical Keyboard']);
});

test('search treats LIKE wildcards in user input literally', function () {
    Product::factory()->create(['name' => 'Plain Item', 'sku' => 'PLAIN-1']);
    Product::factory()->create(['name' => '100% Cotton Tee', 'sku' => 'TEE-1']);

    expect(listedNames($this->get(route('products.index', ['q' => '%']))))->toBe(['100% Cotton Tee']);
    expect(listedNames($this->get(route('products.index', ['q' => '_']))))->toBe([]);
});

test('category filter narrows results and unknown or inactive categories return nothing', function () {
    $audio = Category::factory()->create(['slug' => 'audio']);
    $inactive = Category::factory()->inactive()->create(['slug' => 'retired']);
    Product::factory()->for($audio)->create(['name' => 'Headphones']);
    Product::factory()->create(['name' => 'Other']);
    Product::factory()->for($inactive)->create(['name' => 'Retired Item']);

    expect(listedNames($this->get(route('products.index', ['category' => 'audio']))))->toBe(['Headphones']);
    expect(listedNames($this->get(route('products.index', ['category' => 'missing']))))->toBe([]);
    expect(listedNames($this->get(route('products.index', ['category' => 'retired']))))->toBe([]);

    $this->get(route('products.index', ['category' => 'audio']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeCategory.slug', 'audio')
            ->where('filters.category', 'audio')
        );
});

test('price range and stock filters apply', function () {
    Product::factory()->create(['name' => 'Cheap', 'price' => '100.00']);
    Product::factory()->create(['name' => 'Mid', 'price' => '500.00']);
    Product::factory()->create(['name' => 'Pricey', 'price' => '900.00']);
    Product::factory()->outOfStock()->create(['name' => 'Mid Sold Out', 'price' => '510.00']);

    expect(listedNames($this->get(route('products.index', ['min_price' => 200, 'max_price' => 600, 'sort' => 'price_asc']))))
        ->toBe(['Mid', 'Mid Sold Out']);

    expect(listedNames($this->get(route('products.index', ['min_price' => 200, 'max_price' => 600, 'in_stock' => 1, 'sort' => 'price_asc']))))
        ->toBe(['Mid']);

    $this->get(route('products.index', ['min_price' => 500, 'max_price' => 100]))
        ->assertSessionHasErrors('max_price');
});

test('sorting uses the allowlist and orders results', function () {
    Product::factory()->create(['name' => 'B', 'price' => '300.00', 'published_at' => now()->subDays(3)]);
    Product::factory()->create(['name' => 'A', 'price' => '100.00', 'published_at' => now()->subDays(1)]);
    Product::factory()->create(['name' => 'C', 'price' => '200.00', 'published_at' => now()->subDays(2)]);

    expect(listedNames($this->get(route('products.index', ['sort' => 'price_asc']))))->toBe(['A', 'C', 'B']);
    expect(listedNames($this->get(route('products.index', ['sort' => 'price_desc']))))->toBe(['B', 'C', 'A']);
    expect(listedNames($this->get(route('products.index', ['sort' => 'name_asc']))))->toBe(['A', 'B', 'C']);
    expect(listedNames($this->get(route('products.index', ['sort' => 'newest']))))->toBe(['A', 'C', 'B']);

    $this->get(route('products.index', ['sort' => 'price; DROP TABLE products']))
        ->assertSessionHasErrors('sort');
});

test('featured sort places featured products first by rank', function () {
    Product::factory()->create(['name' => 'Regular']);
    Product::factory()->featured(2)->create(['name' => 'Featured Two']);
    Product::factory()->featured(1)->create(['name' => 'Featured One']);

    expect(listedNames($this->get(route('products.index'))))
        ->toBe(['Featured One', 'Featured Two', 'Regular']);
});

test('pagination is bounded and stable', function () {
    Product::factory()->count(13)->create();

    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 12)
            ->where('pagination.currentPage', 1)
            ->where('pagination.lastPage', 2)
            ->where('pagination.total', 13)
            ->where('pagination.perPage', 12)
        );

    $this->get(route('products.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('pagination.currentPage', 2)
        );

    $this->get(route('products.index', ['per_page' => 24]))
        ->assertInertia(fn (Assert $page) => $page->has('products', 13)->where('pagination.lastPage', 1));

    $this->get(route('products.index', ['per_page' => 500]))->assertSessionHasErrors('per_page');
    $this->get(route('products.index', ['page' => 0]))->assertSessionHasErrors('page');
});

test('invalid filter input is rejected at the server boundary', function () {
    $this->get(route('products.index', ['category' => 'bad slug!']))->assertSessionHasErrors('category');
    $this->get(route('products.index', ['q' => str_repeat('a', 121)]))->assertSessionHasErrors('q');
    $this->get(route('products.index', ['min_price' => -5]))->assertSessionHasErrors('min_price');
    $this->get(route('products.index', ['min_price' => 'abc']))->assertSessionHasErrors('min_price');
});

test('prices are formatted from exact decimals and discounts only show when higher', function () {
    Product::factory()->onSale('6490.00', '7490.00')->create(['name' => 'On Sale', 'published_at' => now()->subMinute()]);
    Product::factory()->onSale('500.00', '500.00')->create(['name' => 'Not Discounted', 'published_at' => now()->subMinutes(2)]);

    $this->get(route('products.index', ['sort' => 'newest']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.0.priceFormatted', '₱6,490.00')
            ->where('products.0.originalPriceFormatted', '₱7,490.00')
            ->where('products.0.discountLabel', 'Save ₱1,000.00')
            ->where('products.1.originalPriceFormatted', null)
            ->where('products.1.discountLabel', null)
        );
});

test('cards use the primary image and fall back to the product name for alt text', function () {
    $product = Product::factory()->create(['name' => 'Camera']);
    ProductImage::factory()->for($product)->create(['path' => 'products/second.jpg', 'sort_order' => 2, 'alt_text' => 'Second']);
    ProductImage::factory()->for($product)->primary()->create(['path' => 'products/main.jpg', 'sort_order' => 5, 'alt_text' => null]);

    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.0.imageUrl', fn (string $url) => str_ends_with($url, 'products/main.jpg'))
            ->where('products.0.imageAlt', 'Camera')
        );
});

test('listing query count does not grow with the number of products', function () {
    Product::factory()->count(2)->create()->each(
        fn (Product $product) => ProductImage::factory()->for($product)->primary()->create()
    );

    DB::enableQueryLog();
    $this->get(route('products.index'))->assertOk();
    $small = count(DB::getQueryLog());

    Product::factory()->count(10)->create()->each(
        fn (Product $product) => ProductImage::factory()->for($product)->primary()->create()
    );

    DB::flushQueryLog();
    $this->get(route('products.index'))->assertOk();
    $large = count(DB::getQueryLog());

    expect($large)->toBe($small);
});

test('category sidebar lists active categories with published product counts', function () {
    $audio = Category::factory()->create(['name' => 'Audio', 'sort_order' => 1]);
    Category::factory()->create(['name' => 'Empty', 'sort_order' => 2]);
    Category::factory()->inactive()->create(['name' => 'Retired']);
    Product::factory()->count(2)->for($audio)->create();
    Product::factory()->draft()->for($audio)->create();

    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories', 2)
            ->where('categories.0.name', 'Audio')
            ->where('categories.0.productCount', 2)
            ->where('categories.1.productCount', 0)
        );
});

test('catalog suggestions endpoint returns matching published products only', function () {
    Product::factory()->create(['name' => 'Kuikma Pickleball Paddle']);
    Product::factory()->draft()->create(['name' => 'Kuikma Draft Paddle']);
    Product::factory()->create(['name' => 'Leather Wallet']);

    $this->getJson(route('catalog.suggestions', ['q' => 'Kuikma']))
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.name', 'Kuikma Pickleball Paddle');

    $this->getJson(route('catalog.suggestions', ['q' => '']))
        ->assertOk()
        ->assertJsonCount(0, 'items');
});
