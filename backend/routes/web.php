<?php

use App\Http\Controllers\Admin\AdminCatalogController;
use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\DashboardController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\WishlistController;
use App\Http\Controllers\Webhooks\PayMongoWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('products', [CatalogController::class, 'index'])->name('products.index');
Route::get('products/{slug}', [ProductController::class, 'show'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('products.show');

Route::get('cart', [CartController::class, 'index'])->name('cart.index');
Route::post('cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');
Route::delete('cart', [CartController::class, 'clear'])->name('cart.clear');

Route::middleware(['auth'])->group(function () {
    Route::post('wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::delete('wishlist/{productId}', [WishlistController::class, 'destroy'])
        ->whereNumber('productId')
        ->name('wishlist.destroy');
});

Route::post('webhooks/paymongo', PayMongoWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('webhooks.paymongo');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('checkout', [CheckoutController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('checkout.store');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{orderNumber}', [OrderController::class, 'show'])
        ->where('orderNumber', '[A-Za-z0-9\-]+')
        ->name('orders.show');
    Route::post('orders/{orderNumber}/cancel', [OrderController::class, 'cancel'])
        ->where('orderNumber', '[A-Za-z0-9\-]+')
        ->name('orders.cancel');

    // Back-office routes (least-privilege: staff and admin)
    Route::prefix('admin')->name('admin.')->middleware('role:staff,admin')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        Route::get('products', [AdminCatalogController::class, 'index'])->name('products.index');
        Route::post('products', [AdminCatalogController::class, 'storeProduct'])->name('products.store');
        Route::put('products/{product}', [AdminCatalogController::class, 'updateProduct'])->name('products.update');
        Route::post('categories', [AdminCatalogController::class, 'storeCategory'])->name('categories.store');
        Route::post('products/{product}/stock', [AdminCatalogController::class, 'adjustStock'])->name('products.stock');
        Route::post('inventory/reconcile', [AdminCatalogController::class, 'reconcileInventory'])->name('inventory.reconcile');

        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::post('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');

        Route::get('customers', [AdminCustomerController::class, 'index'])->name('customers.index');

        // Admin-only routes (least-privilege: admin only)
        Route::middleware('role:admin')->group(function () {
            Route::post('products/bulk-status', [AdminCatalogController::class, 'bulkUpdateStatus'])
                ->name('products.bulk-status');
            Route::post('products/import', [AdminCatalogController::class, 'importCsv'])
                ->name('products.import');
            Route::patch('customers/{customer}/role', [AdminCustomerController::class, 'updateRole'])
                ->name('customers.role');
        });
    });
});

require __DIR__.'/settings.php';
