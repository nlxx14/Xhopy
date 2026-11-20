<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Multi-vendor readiness (ADR-001 §5): nullable boundary, no FK until a stores table exists.
            $table->unsignedBigInteger('store_id')->nullable()->index();

            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();

            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->string('sku', 64)->unique();
            $table->string('short_description', 300)->nullable();
            $table->text('description')->nullable();

            // Money: fixed-point decimal with explicit currency; never floats.
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->char('currency', 3)->default('PHP');

            // Interim availability counter. Batch 6 replaces writes with an auditable stock-movement ledger.
            $table->unsignedInteger('stock_quantity')->default(0);

            $table->string('status', 16)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('featured_rank')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status']);
            $table->index(['is_featured', 'featured_rank']);
            $table->index(['status', 'price']);
        });

        // Enforce critical invariants in the database as well (SQLite cannot add constraints after create).
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('draft', 'active', 'archived'))");
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_check CHECK (price >= 0 AND (compare_at_price IS NULL OR compare_at_price >= 0))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
