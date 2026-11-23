<?php

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Catalog\Models\ProductReview;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\PaymentStatus;
use App\Domain\Orders\Models\Order;
use App\Models\User;
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

test('only customers who purchased a product can submit and update a rating and feedback', function () {
    $product = Product::factory()->create([
        'name' => 'Balanced Desktop DAC',
        'slug' => 'balanced-desktop-dac',
        'sku' => 'DAC-001',
        'price' => '4950.00',
    ]);

    $nonBuyer = User::factory()->create();
    $buyer = User::factory()->create(['name' => 'Verified Buyer']);

    // Non-buyer attempt is rejected
    $this->actingAs($nonBuyer)
        ->post(route('products.reviews.store', $product), [
            'rating' => 5,
            'title' => 'Fake review',
            'comment' => 'Never bought this.',
        ])
        ->assertSessionHasErrors('rating');

    expect(ProductReview::query()->count())->toBe(0);

    // Create a purchased order for $buyer containing $product (initially Shipped, not yet Delivered)
    $order = Order::query()->create([
        'order_number' => 'XH-20261122-REV001',
        'user_id' => $buyer->id,
        'idempotency_key' => 'rev-order-001',
        'status' => OrderStatus::Shipped,
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => 'cash_on_delivery',
        'currency' => 'PHP',
        'subtotal_amount' => '4950.00',
        'discount_amount' => '0.00',
        'shipping_amount' => '0.00',
        'total_amount' => '4950.00',
        'shipping_recipient_name' => 'Verified Buyer',
        'shipping_phone' => '+63 917 111 2222',
        'shipping_line_1' => '100 Ayala Ave',
        'shipping_city' => 'Makati',
        'shipping_province' => 'Metro Manila',
        'shipping_postal_code' => '1226',
        'shipping_country_code' => 'PH',
        'placed_at' => now(),
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_slug' => $product->slug,
        'sku' => $product->sku,
        'unit_price' => '4950.00',
        'quantity' => 1,
        'line_total' => '4950.00',
        'currency' => 'PHP',
    ]);

    // Before delivery (Shipped), review submission is blocked
    $this->actingAs($buyer)
        ->post(route('products.reviews.store', $product), [
            'rating' => 5,
            'title' => 'Early review',
            'comment' => 'Still in transit.',
        ])
        ->assertSessionHasErrors('rating');

    expect(ProductReview::query()->count())->toBe(0);

    // Once order is marked Delivered, dashboard prompts the customer to review it
    $order->update(['status' => OrderStatus::Delivered]);

    $this->actingAs($buyer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('pendingReviews', 1)
            ->where('pendingReviews.0.productId', $product->id)
            ->where('pendingReviews.0.orderNumber', $order->order_number)
        );

    $this->actingAs($buyer)
        ->post(route('products.reviews.store', $product), [
            'rating' => 5,
            'title' => 'Clean soundstage',
            'comment' => 'Zero noise floor and solid aluminum knob.',
        ])
        ->assertSessionHasNoErrors();

    expect(ProductReview::query()->count())->toBe(1);

    // After submitting review, dashboard pending review prompt is cleared
    $this->actingAs($buyer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('pendingReviews', 0)
        );

    // Verify ratingAverage, ratingCount, and review appear on product detail page
    $this->actingAs($buyer)
        ->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.ratingAverage', 5)
            ->where('product.ratingCount', 1)
            ->where('product.canReview', true)
            ->where('product.userReview.title', 'Clean soundstage')
            ->has('product.reviews', 1)
            ->where('product.reviews.0.authorName', 'Verified Buyer')
            ->where('product.reviews.0.isVerifiedPurchase', true)
        );

    // Verify order detail page also reflects the submitted review
    $this->actingAs($buyer)
        ->get(route('orders.show', $order->order_number))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.items.0.canReview', true)
            ->where('order.items.0.review.rating', 5)
            ->where('order.items.0.review.title', 'Clean soundstage')
        );
});
