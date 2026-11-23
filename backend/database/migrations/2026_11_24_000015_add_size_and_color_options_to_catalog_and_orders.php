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
        Schema::table('products', function (Blueprint $table) {
            $table->json('available_colors')->nullable()->after('stock_quantity');
            $table->json('available_sizes')->nullable()->after('available_colors');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->index('cart_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'product_id']);
            $table->string('selected_color', 80)->nullable()->after('quantity');
            $table->string('selected_size', 80)->nullable()->after('selected_color');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('selected_color', 80)->nullable()->after('sku');
            $table->string('selected_size', 80)->nullable()->after('selected_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['selected_color', 'selected_size']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['selected_color', 'selected_size']);
            $table->unique(['cart_id', 'product_id']);
            $table->dropIndex(['cart_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['available_colors', 'available_sizes']);
        });
    }
};
