<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Services\AdminAuditRecorder;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Services\InventoryService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * All-or-nothing validated CSV catalog import (admin-only).
 * Required columns: sku,name,category_slug,price,stock_quantity,status[,short_description]
 */
final class ImportProductsAction
{
    public const MAX_IMPORT_ROWS = 50;

    private const REQUIRED_HEADERS = [
        'sku',
        'name',
        'category_slug',
        'price',
        'stock_quantity',
        'status',
    ];

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function execute(User $actor, string $csvContent): int
    {
        $lines = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', trim($csvContent)) ?: [],
            fn (string $line): bool => trim($line) !== '',
        ));

        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'csv_data' => __('The CSV import must include a header row and at least one product row.'),
            ]);
        }

        if (count($lines) - 1 > self::MAX_IMPORT_ROWS) {
            throw ValidationException::withMessages([
                'csv_data' => __('A single CSV import cannot exceed :max product rows.', ['max' => self::MAX_IMPORT_ROWS]),
            ]);
        }

        $headers = array_map(
            fn (?string $col): string => strtolower(trim((string) $col)),
            str_getcsv($lines[0]),
        );

        foreach (self::REQUIRED_HEADERS as $required) {
            if (! in_array($required, $headers, true)) {
                throw ValidationException::withMessages([
                    'csv_data' => __('Missing required CSV column header [:column].', ['column' => $required]),
                ]);
            }
        }

        $categoriesBySlug = Category::query()->get()->keyBy('slug');
        $parsedRows = [];
        $errors = [];

        foreach (array_slice($lines, 1) as $zeroIndex => $rawLine) {
            $rowNumber = $zeroIndex + 2;
            $values = str_getcsv($rawLine);
            $row = [];
            foreach ($headers as $colIndex => $headerName) {
                $row[$headerName] = isset($values[$colIndex]) ? trim((string) $values[$colIndex]) : '';
            }

            $sku = strtoupper($row['sku'] ?? '');
            $name = $row['name'] ?? '';
            $categorySlug = $row['category_slug'] ?? '';
            $priceRaw = $row['price'] ?? '';
            $stockRaw = $row['stock_quantity'] ?? '';
            $statusRaw = strtolower($row['status'] ?? '');

            if ($sku === '' || ! preg_match('/^[A-Z0-9\-_]{3,64}$/', $sku)) {
                $errors[] = "Row {$rowNumber}: invalid SKU [{$sku}].";
            }

            if ($name === '' || mb_strlen($name) > 200) {
                $errors[] = "Row {$rowNumber}: product name is required (max 200 chars).";
            }

            /** @var Category|null $category */
            $category = $categoriesBySlug->get($categorySlug);
            if ($category === null) {
                $errors[] = "Row {$rowNumber}: unknown category_slug [{$categorySlug}].";
            }

            if (! is_numeric($priceRaw) || (float) $priceRaw < 0 || (float) $priceRaw > 9999999.99) {
                $errors[] = "Row {$rowNumber}: invalid price [{$priceRaw}].";
            }

            if (! ctype_digit($stockRaw) || (int) $stockRaw < 0 || (int) $stockRaw > 100000) {
                $errors[] = "Row {$rowNumber}: invalid stock_quantity [{$stockRaw}].";
            }

            $status = ProductStatus::tryFrom($statusRaw);
            if ($status === null) {
                $errors[] = "Row {$rowNumber}: status must be draft, active, or archived.";
            }

            if ($errors === [] && $category !== null && $status !== null) {
                $parsedRows[] = [
                    'sku' => $sku,
                    'name' => $name,
                    'category_id' => $category->id,
                    'price' => number_format((float) $priceRaw, 2, '.', ''),
                    'stock_quantity' => (int) $stockRaw,
                    'status' => $status,
                    'short_description' => isset($row['short_description']) && $row['short_description'] !== ''
                        ? mb_substr($row['short_description'], 0, 300)
                        : null,
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'csv_data' => implode(' ', $errors),
            ]);
        }

        return DB::transaction(function () use ($actor, $parsedRows): int {
            $imported = 0;

            foreach ($parsedRows as $item) {
                $existing = Product::query()
                    ->where('sku', $item['sku'])
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $previousStock = $existing->stock_quantity;
                    $existing->fill([
                        'category_id' => $item['category_id'],
                        'name' => $item['name'],
                        'price' => $item['price'],
                        'stock_quantity' => $item['stock_quantity'],
                        'status' => $item['status'],
                        'short_description' => $item['short_description'] ?? $existing->short_description,
                        'published_at' => $item['status'] === ProductStatus::Active
                            ? ($existing->published_at ?? now())
                            : $existing->published_at,
                    ])->save();

                    if ($item['stock_quantity'] !== $previousStock) {
                        $this->inventory->recordMovementForLockedProduct(
                            $existing,
                            $previousStock,
                            $item['stock_quantity'],
                            StockMovementType::ManualAdjustment,
                            $actor,
                            'Stock updated via CSV import.',
                        );
                    }
                } else {
                    $baseSlug = Str::slug($item['name']).'-'.strtolower($item['sku']);
                    $product = Product::query()->create([
                        'category_id' => $item['category_id'],
                        'name' => $item['name'],
                        'slug' => mb_substr($baseSlug, 0, 200),
                        'sku' => $item['sku'],
                        'short_description' => $item['short_description'],
                        'price' => $item['price'],
                        'currency' => 'PHP',
                        'stock_quantity' => $item['stock_quantity'],
                        'status' => $item['status'],
                        'published_at' => $item['status'] === ProductStatus::Active ? now() : null,
                    ]);

                    if ($item['stock_quantity'] > 0) {
                        $this->inventory->recordMovementForLockedProduct(
                            $product,
                            0,
                            $item['stock_quantity'],
                            StockMovementType::InitialStock,
                            $actor,
                            'Initial stock via CSV import.',
                        );
                    }
                }

                $imported++;
            }

            $this->audit->record(
                $actor,
                'product.csv_imported',
                sprintf('Imported %d product row(s) via validated CSV import.', $imported),
                'Product',
                null,
                ['imported_count' => $imported],
            );

            return $imported;
        });
    }
}
