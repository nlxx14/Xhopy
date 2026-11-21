<?php

use App\Domain\Admin\Services\DatabaseBackupService;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\PaymentStatus;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('responses include hardened HTTP security headers and request correlation IDs', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');

    $requestId = $response->headers->get('X-Request-Id');
    expect($requestId)->toBeString()->not->toBeEmpty();
});

test('valid incoming correlation IDs are preserved while malformed header values are sanitized', function () {
    $valid = $this->withHeaders(['X-Request-Id' => 'xhopy-trace-2026-0001'])
        ->getJson(route('health.ready'));

    $valid->assertOk()
        ->assertHeader('X-Request-Id', 'xhopy-trace-2026-0001')
        ->assertJsonPath('request_id', 'xhopy-trace-2026-0001');

    $malicious = $this->withHeaders(['X-Request-Id' => "bad<script>alert(1)</script>\r\nInjected: 1"])
        ->getJson(route('health.ready'));

    $malicious->assertOk();
    $sanitizedId = (string) $malicious->headers->get('X-Request-Id');

    expect($sanitizedId)->not->toContain('<script>')
        ->and($sanitizedId)->toMatch('/^[A-Za-z0-9._\-]{8,64}$/');
});

test('health readiness endpoint verifies database, cache, and storage without leaking secrets', function () {
    $response = $this->getJson(route('health.ready'));

    $response->assertOk()
        ->assertJson([
            'status' => 'healthy',
            'checks' => [
                'database' => 'ok',
                'cache' => 'ok',
                'storage' => 'ok',
            ],
        ])
        ->assertJsonStructure(['status', 'checks', 'timestamp', 'request_id']);

    $payload = (string) $response->getContent();
    expect($payload)->not->toContain((string) config('app.key'));
});

test('cross-customer IDOR attempts on orders and shipping addresses are denied', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $address = CustomerAddress::factory()->for($owner)->create();
    $order = Order::query()->create([
        'order_number' => 'XH-20261121-IDOR01',
        'user_id' => $owner->id,
        'idempotency_key' => 'idor-test-key-01',
        'status' => OrderStatus::PendingPayment,
        'payment_status' => PaymentStatus::Unpaid,
        'payment_method' => 'paymongo_checkout',
        'currency' => 'PHP',
        'subtotal_amount' => '1500.00',
        'discount_amount' => '0.00',
        'shipping_amount' => '0.00',
        'total_amount' => '1500.00',
        'shipping_recipient_name' => $address->recipient_name,
        'shipping_phone' => $address->phone,
        'shipping_line_1' => $address->line_1,
        'shipping_city' => $address->city,
        'shipping_province' => $address->province,
        'shipping_postal_code' => $address->postal_code,
        'shipping_country_code' => 'PH',
        'placed_at' => now(),
    ]);

    // Order IDOR checks
    $this->actingAs($intruder)
        ->get(route('orders.show', $order->order_number))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->post(route('orders.cancel', $order->order_number))
        ->assertForbidden();

    // Address IDOR checks
    $this->actingAs($intruder)
        ->put(route('addresses.update', $address), [
            'label' => 'Home',
            'recipient_name' => 'Intruder',
            'phone' => '+63 917 000 0000',
            'line_1' => '123 Fake St',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'postal_code' => '1000',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->patch(route('addresses.default', $address))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('addresses.destroy', $address))
        ->assertForbidden();
});

test('mass assignment cannot escalate customer role during registration or profile update', function () {
    $this->post('/register', [
        'name' => 'Attempted Escalation',
        'email' => 'escalate@example.com',
        'password' => 'SecurePass#2026!',
        'password_confirmation' => 'SecurePass#2026!',
        'role' => 'admin',
    ]);

    $registered = User::query()->where('email', 'escalate@example.com')->firstOrFail();
    expect($registered->role)->toBe(UserRole::Customer);

    $this->actingAs($registered)
        ->patch(route('profile.update'), [
            'name' => 'Updated Name',
            'email' => 'escalate@example.com',
            'role' => 'admin',
        ]);

    expect($registered->fresh()->role)->toBe(UserRole::Customer);
});

test('catalog search and filtering safely handle SQL injection and XSS payloads', function () {
    Product::factory()->create([
        'name' => 'Safe Reference Headphones',
        'status' => ProductStatus::Active,
        'stock_quantity' => 5,
    ]);

    $sqlInjectionResponse = $this->get(route('products.index', [
        'q' => "' OR 1=1; DROP TABLE products; --",
        'sort' => 'featured',
    ]));

    $sqlInjectionResponse->assertOk();
    expect(Product::query()->count())->toBe(1);

    $xssResponse = $this->get(route('products.index', [
        'q' => '<script>alert("xss")</script>',
    ]));

    $xssResponse->assertOk();
});

test('checkout aborts atomically when product becomes inactive or out of stock while in cart', function () {
    $user = User::factory()->create();
    $address = CustomerAddress::factory()->for($user)->defaultShipping()->create();
    $product = Product::factory()->create([
        'name' => 'Limited Edition DAC',
        'price' => '5200.00',
        'stock_quantity' => 2,
        'status' => ProductStatus::Active,
    ]);

    $this->actingAs($user)->post(route('cart.items.store'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertSessionHasNoErrors();

    // Product is archived while in the user's cart
    $product->update(['status' => ProductStatus::Archived]);

    $this->actingAs($user)->post(route('checkout.store'), [
        'idempotency_key' => 'idem-inactive-check-1',
        'payment_method' => 'paymongo_checkout',
        'address_id' => $address->id,
    ])->assertSessionHasErrors('cart');

    expect(Order::query()->count())->toBe(0)
        ->and($product->fresh()->stock_quantity)->toBe(2);

    // Re-activate product, but drop stock to 0 before checkout
    $product->update([
        'status' => ProductStatus::Active,
        'stock_quantity' => 0,
    ]);

    $this->actingAs($user)->post(route('checkout.store'), [
        'idempotency_key' => 'idem-zero-stock-check-2',
        'payment_method' => 'paymongo_checkout',
        'address_id' => $address->id,
    ])->assertSessionHasErrors('cart');

    expect(Order::query()->count())->toBe(0);
});

test('encrypted database backup creation, checksum verification, tamper detection, and transactional restore simulation work', function () {
    Storage::fake('local');

    Product::factory()->count(2)->create();

    /** @var DatabaseBackupService $backupService */
    $backupService = app(DatabaseBackupService::class);

    $backup = $backupService->createBackup(encrypt: true);

    expect($backup['encrypted'])->toBeTrue()
        ->and($backup['total_rows'])->toBeGreaterThanOrEqual(2);

    Storage::disk('local')->assertExists("backups/{$backup['filename']}");

    $verification = $backupService->verifyBackup($backup['filename'], runRestoreSimulation: true);

    expect($verification['verified'])->toBeTrue()
        ->and($verification['encrypted'])->toBeTrue()
        ->and($verification['restore_tested'])->toBeTrue()
        ->and($verification['checksum_sha256'])->toBe($backup['checksum_sha256']);

    // Create an unencrypted backup and tamper with a row to verify SHA-256 tamper detection
    $plainBackup = $backupService->createBackup(encrypt: false);
    $rawPath = "backups/{$plainBackup['filename']}";
    $decoded = json_decode((string) Storage::disk('local')->get($rawPath), true, 512, JSON_THROW_ON_ERROR);
    $decoded['tables']['products'][0]['name'] = 'Tampered Product Name';
    Storage::disk('local')->put($rawPath, json_encode($decoded, JSON_THROW_ON_ERROR));

    expect(fn () => $backupService->verifyBackup($plainBackup['filename']))
        ->toThrow(RuntimeException::class, 'Backup SHA-256 checksum mismatch');
});

test('artisan backup, backup-verify, and launch-check commands succeed', function () {
    Storage::fake('local');

    Product::factory()->create(['stock_quantity' => 0]);

    $this->artisan('xhopy:launch-check')
        ->assertSuccessful();

    $backupService = app(DatabaseBackupService::class);
    $created = $backupService->createBackup(encrypt: true);

    $this->artisan('xhopy:backup-verify', [
        'file' => $created['filename'],
        '--restore-test' => true,
    ])->assertSuccessful();
});
