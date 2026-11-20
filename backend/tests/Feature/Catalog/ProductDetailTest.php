<?php

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('product detail renders media, pricing and availability from trusted records', function () {
    $category = Category::factory()->create(['name' => 'Audio', 'slug' => 'audio']);
    $product = Product::factory()->for($category)->onSale('6490.00', '7490.00')->create([
        'name' => 'Studio Headphones',
        'slug' => 'studio-headphones',
        'sku' => 'AUD-001',
        'short_description' => 'Closed-back monitors.',
        'description' => 'Long description.',
        'stock_quantity' => 3,
    ]);
    ProductImage::factory()->for($product)->create(['path' => 'products/b.jpg', 'sort_order' => 2, 'alt_text' => 'Back view']);
    ProductImage::factory()->for($product)->create(['path' => 'products/a.jpg', 'sort_order' => 1, 'alt_text' => null]);

    $this->get(route('products.show', 'studio-headphones'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/Show')
            ->where('product.name', 'Studio Headphones')
            ->where('product.sku', 'AUD-001')
            ->where('product.categoryName', 'Audio')
            ->where('product.categorySlug', 'audio')
            ->where('product.priceFormatted', '₱6,490.00')
            ->where('product.originalPriceFormatted', '₱7,490.00')
            ->where('product.discountLabel', 'Save ₱1,000.00')
            ->where('product.inStock', true)
            ->where('product.stockLabel', 'In Stock')
            ->where('product.description', 'Long description.')
            ->where('product.ratingAverage', null)
            ->has('product.images', 2)
            ->where('product.images.0.alt', 'Studio Headphones')
            ->where('product.images.1.alt', 'Back view')
            ->where('product.images.0.url', fn (string $url) => str_ends_with($url, 'products/a.jpg'))
        );
});

test('out of stock products are shown as unavailable', function () {
    Product::factory()->outOfStock()->create(['slug' => 'sold-out']);

    $this->get(route('products.show', 'sold-out'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.inStock', false)
            ->where('product.stockLabel', 'Out of Stock')
        );
});

test('products that are not publicly visible return 404', function () {
    Product::factory()->draft()->create(['slug' => 'draft-item']);
    Product::factory()->archived()->create(['slug' => 'archived-item']);
    Product::factory()->scheduled()->create(['slug' => 'scheduled-item']);
    Product::factory()->for(Category::factory()->inactive())->create(['slug' => 'hidden-category-item']);

    foreach (['draft-item', 'archived-item', 'scheduled-item', 'hidden-category-item', 'does-not-exist'] as $slug) {
        $this->get(route('products.show', $slug))->assertNotFound();
    }
});

test('malformed slugs do not resolve', function () {
    $this->get('/products/bad%20slug!')->assertNotFound();
});

test('related products come from the same category and exclude the current and hidden products', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['slug' => 'main']);
    Product::factory()->count(2)->for($category)->create();
    Product::factory()->draft()->for($category)->create();
    Product::factory()->create(); // different category

    $this->get(route('products.show', 'main'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('relatedProducts', 2)
            ->where('relatedProducts.0.slug', fn (string $slug) => $slug !== $product->slug)
        );
});

test('product detail exposes shell data for the storefront layout', function () {
    Product::factory()->create(['slug' => 'with-shell']);

    $this->get(route('products.show', 'with-shell'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories', 1)
            ->where('cartSummary.itemCount', 0)
            ->where('announcement', null)
        );
});
