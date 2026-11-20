<?php

use App\Domain\Admin\Models\AdminAuditLog;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('least-privilege roles separate customer, staff, and admin back-office operations', function () {
    $customer = User::factory()->create();
    $staff = User::factory()->staff()->create();
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();

    // Customer is denied across all back-office routes
    $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.products.index'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.orders.index'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.customers.index'))->assertForbidden();

    // Staff can access back-office dashboard and create products/categories
    $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($staff)->post(route('admin.products.store'), [
        'category_id' => $category->id,
        'name' => 'Staff Created Mouse',
        'sku' => 'STF-MSE-001',
        'price' => '1890.00',
        'stock_quantity' => 15,
        'status' => 'active',
    ])->assertRedirect(route('admin.products.index'));

    $product = Product::query()->where('sku', 'STF-MSE-001')->firstOrFail();

    // Staff cannot execute admin-only bulk status, CSV import, or role assignment
    $this->actingAs($staff)->post(route('admin.products.bulk-status'), [
        'product_ids' => [$product->id],
        'status' => 'archived',
    ])->assertForbidden();

    $this->actingAs($staff)->post(route('admin.products.import'), [
        'csv_data' => "sku,name,category_slug,price,stock_quantity,status\n",
    ])->assertForbidden();

    $this->actingAs($staff)->patch(route('admin.customers.role', $customer), [
        'role' => 'staff',
    ])->assertForbidden();

    // Admin can assign roles to other users, but cannot demote themselves
    $this->actingAs($admin)->patch(route('admin.customers.role', $customer), [
        'role' => 'staff',
    ])->assertRedirect(route('admin.customers.index'));

    expect($customer->fresh()->role)->toBe(UserRole::Staff);

    $this->actingAs($admin)
        ->from(route('admin.customers.index'))
        ->patch(route('admin.customers.role', $admin), [
            'role' => 'customer',
        ])
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->role)->toBe(UserRole::Admin);
});

test('inventory movements ledger records initial stock, adjustments, reservations, releases, and reconciliation', function () {
    $staff = User::factory()->staff()->create();
    $customer = User::factory()->create();
    $address = CustomerAddress::factory()->for($customer)->defaultShipping()->create();
    $category = Category::factory()->create();

    // 1. Product creation with initial stock writes InitialStock ledger entry
    $this->actingAs($staff)->post(route('admin.products.store'), [
        'category_id' => $category->id,
        'name' => 'Studio Microphone',
        'sku' => 'MIC-PRO-01',
        'price' => '5200.00',
        'stock_quantity' => 10,
        'status' => 'active',
    ]);

    $product = Product::query()->where('sku', 'MIC-PRO-01')->firstOrFail();

    expect(StockMovement::query()->where('product_id', $product->id)->count())->toBe(1)
        ->and(StockMovement::query()->where('product_id', $product->id)->firstOrFail()->type)
        ->toBe(StockMovementType::InitialStock);

    // 2. Restock (+5) and negative manual adjustment (-2)
    $this->actingAs($staff)->post(route('admin.products.stock', $product), [
        'quantity_delta' => 5,
        'type' => 'restock',
        'reason' => 'Supplier shipment #42',
    ])->assertRedirect(route('admin.products.index'));

    expect($product->fresh()->stock_quantity)->toBe(15);

    // Attempting to deduct more than current stock (-20 from 15) is rejected
    $this->actingAs($staff)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.stock', $product), [
            'quantity_delta' => -20,
            'type' => 'manual_adjustment',
            'reason' => 'Invalid deduction',
        ])
        ->assertSessionHasErrors('quantity_delta');

    expect($product->fresh()->stock_quantity)->toBe(15);

    // 3. Checkout order placement records OrderReservation (-3) and cancellation records OrderCancellationRelease (+3)
    $this->actingAs($customer)->post(route('cart.items.store'), [
        'product_id' => $product->id,
        'quantity' => 3,
    ]);
    $this->actingAs($customer)->post(route('checkout.store'), [
        'payment_method' => 'paymongo_checkout',
        'address_id' => $address->id,
    ]);

    $order = Order::query()->where('user_id', $customer->id)->firstOrFail();
    expect($product->fresh()->stock_quantity)->toBe(12)
        ->and(
            StockMovement::query()
                ->where('product_id', $product->id)
                ->where('order_id', $order->id)
                ->where('type', StockMovementType::OrderReservation)
                ->where('quantity_delta', -3)
                ->exists()
        )->toBeTrue();

    // Staff transitions order to Cancelled -> releases stock back to 15 and writes OrderCancellationRelease
    $this->actingAs($staff)->post(route('admin.orders.status', $order), [
        'status' => OrderStatus::Cancelled->value,
        'note' => 'Cancelled on customer request.',
    ])->assertRedirect(route('admin.orders.index'));

    expect($product->fresh()->stock_quantity)->toBe(15)
        ->and(
            StockMovement::query()
                ->where('product_id', $product->id)
                ->where('order_id', $order->id)
                ->where('type', StockMovementType::OrderCancellationRelease)
                ->where('quantity_delta', 3)
                ->exists()
        )->toBeTrue();

    // 4. Reconciliation detects a product seeded without a baseline movement and balances its ledger
    $unreconciled = Product::factory()->for($category)->create(['stock_quantity' => 8]);
    expect((int) StockMovement::query()->where('product_id', $unreconciled->id)->sum('quantity_delta'))->toBe(0);

    $this->actingAs($staff)->post(route('admin.inventory.reconcile'))
        ->assertRedirect(route('admin.products.index'));

    expect((int) StockMovement::query()->where('product_id', $unreconciled->id)->sum('quantity_delta'))->toBe(8)
        ->and(
            StockMovement::query()
                ->where('product_id', $unreconciled->id)
                ->where('type', StockMovementType::Reconciliation)
                ->exists()
        )->toBeTrue();
});

test('admin bulk status update and validated csv import enforce all-or-nothing integrity and audit logging', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['slug' => 'audio-gear']);
    $p1 = Product::factory()->draft()->for($category)->create();
    $p2 = Product::factory()->draft()->for($category)->create();

    // Safe bulk status update
    $this->actingAs($admin)->post(route('admin.products.bulk-status'), [
        'product_ids' => [$p1->id, $p2->id],
        'status' => 'active',
    ])->assertRedirect(route('admin.products.index'));

    expect($p1->fresh()->status)->toBe(ProductStatus::Active)
        ->and($p1->fresh()->published_at)->not->toBeNull()
        ->and($p2->fresh()->status)->toBe(ProductStatus::Active);

    // Invalid CSV row (unknown category_slug on row 3) rolls back entire import
    $invalidCsv = implode("\n", [
        'sku,name,category_slug,price,stock_quantity,status',
        'CSV-AUD-101,Valid CSV Amp,audio-gear,3500.00,6,active',
        'CSV-AUD-102,Bad Category Item,unknown-category,1200.00,4,active',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.products.index'))
        ->post(route('admin.products.import'), ['csv_data' => $invalidCsv])
        ->assertSessionHasErrors('csv_data');

    expect(Product::query()->where('sku', 'CSV-AUD-101')->exists())->toBeFalse();

    // Valid CSV imports all rows, records initial stock movements, and logs admin audit entry
    $validCsv = implode("\n", [
        'sku,name,category_slug,price,stock_quantity,status,short_description',
        'CSV-AUD-101,Valid CSV Amp,audio-gear,3500.00,6,active,Compact desktop amplifier',
        'CSV-AUD-102,Valid CSV Cable,audio-gear,490.00,20,draft,Braided XLR cable',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.products.import'), ['csv_data' => $validCsv])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.products.index'));

    $importedAmp = Product::query()->where('sku', 'CSV-AUD-101')->firstOrFail();
    expect($importedAmp->price)->toBe('3500.00')
        ->and($importedAmp->stock_quantity)->toBe(6)
        ->and(StockMovement::query()->where('product_id', $importedAmp->id)->value('quantity_delta'))->toBe(6)
        ->and(AdminAuditLog::query()->where('action', 'product.csv_imported')->exists())->toBeTrue();
});
