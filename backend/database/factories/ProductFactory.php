<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Published, in-stock product by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $words = fake()->unique()->words(3);
        $name = is_array($words) ? implode(' ', $words) : $words;

        return [
            'store_id' => null,
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 999999),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-????-####')),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraphs(2, true),
            'price' => fake()->numberBetween(1000, 900000) / 100,
            'compare_at_price' => null,
            'currency' => 'PHP',
            'stock_quantity' => fake()->numberBetween(1, 50),
            'status' => ProductStatus::Active,
            'is_featured' => false,
            'featured_rank' => null,
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => ProductStatus::Draft, 'published_at' => null]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => ProductStatus::Archived]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => ['published_at' => now()->addDay()]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (): array => ['stock_quantity' => 0]);
    }

    public function featured(?int $rank = null): static
    {
        return $this->state(fn (): array => ['is_featured' => true, 'featured_rank' => $rank]);
    }

    public function onSale(string $price = '100.00', string $compareAt = '150.00'): static
    {
        return $this->state(fn (): array => ['price' => $price, 'compare_at_price' => $compareAt]);
    }
}
