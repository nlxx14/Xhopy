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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('provider', 32)->default('paymongo');
            $table->string('provider_reference', 120)->nullable()->unique();
            $table->string('checkout_url', 500)->nullable();
            $table->string('status', 24)->default('pending');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('PHP');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->default('paymongo');
            $table->string('provider_event_id', 120)->unique();
            $table->string('event_type', 80);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->json('payload');
            $table->timestamp('processed_at');

            $table->index(['order_id', 'processed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
    }
};
