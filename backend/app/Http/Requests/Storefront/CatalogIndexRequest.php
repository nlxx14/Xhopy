<?php

namespace App\Http\Requests\Storefront;

use App\Domain\Catalog\Enums\ProductSort;
use App\Domain\Catalog\Queries\CatalogFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogIndexRequest extends FormRequest
{
    private const MAX_PRICE = 9999999.99;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80', 'alpha_dash'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_PRICE],
            'max_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:'.self::MAX_PRICE,
                Rule::when($this->filled('min_price'), ['gte:min_price']),
            ],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::enum(ProductSort::class)],
            'per_page' => ['nullable', 'integer', Rule::in(CatalogFilters::PER_PAGE_OPTIONS)],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function filters(): CatalogFilters
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CatalogFilters::fromValidated($validated);
    }
}
