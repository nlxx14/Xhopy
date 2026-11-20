<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->string('idempotency_key', 64)->nullable()->unique();

            $table->string('status', 24)->default('pending_payment');
            $table->string('payment_status', 24)->default('unpaid');
            $table->string('payment_method', 32)->default('paymongo_checkout');
            $table->char('currency', 3)->default('PHP');

            $table->decimal('subtotal_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);

            // Immutable shipping address snapshot captured at order placement
            $table->string('shipping_recipient_name', 120);
            $table->string('shipping_phone', 32);
            $table->string('shipping_line_1', 180);
            $table->string('shipping_line_2', 180)->nullable();
            $table->string('shipping_barangay', 120)->nullable();
            $table->string('shipping_city', 120);
            $table->string('shipping_province', 120);
            $table->string('shipping_postal_code', 20);
            $table->char('shipping_country_code', 2)->default('PH');
            $table->string('shipping_notes', 255)->nullable();

            $table->timestamp('placed_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'placed_at']);
            $table->index(['status', 'placed_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedBigInteger('store_id')->nullable();

            // Immutable catalog snapshots (database-standards.md rule: preserve historical accuracy)
            $table->string('product_name', 200);
            $table->string('product_slug', 200);
            $table->string('sku', 64);
            $table->decimal('unit_price', 12, 2);
            $table->char('currency', 3)->default('PHP');
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            $table->index('order_id');
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('occurred_at');

            $table->index(['order_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
