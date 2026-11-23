<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\BulkUpdateProductStatusAction;
use App\Domain\Admin\Actions\DeleteCategoryAction;
use App\Domain\Admin\Actions\DeleteProductsAction;
use App\Domain\Admin\Actions\ImportProductsAction;
use App\Domain\Admin\Actions\SaveCategoryAction;
use App\Domain\Admin\Actions\SaveProductAction;
use App\Domain\Admin\Services\AdminAuditRecorder;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Support\Money;
use App\Domain\Catalog\Support\ProductPresenter;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Services\InventoryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminCatalogController extends Controller
{
    public function index(Request $request, ProductPresenter $presenter): Response
    {
        $products = array_values(
            Product::query()
                ->with(['category:id,name,slug', 'primaryImage'])
                ->withSum('stockMovements as ledger_stock_sum', 'quantity_delta')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->map(function (Product $product) use ($presenter): array {
                    $ledgerSum = (int) ($product->getAttribute('ledger_stock_sum') ?? 0);

                    return [
                        'id' => $product->id,
                        'categoryId' => $product->category_id,
                        'categoryName' => $product->category->name,
                        'categorySlug' => $product->category->slug,
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'sku' => $product->sku,
                        'shortDescription' => $product->short_description,
                        'description' => $product->description,
                        'price' => $product->price,
                        'compareAtPrice' => $product->compare_at_price,
                        'priceFormatted' => Money::format($product->price, $product->currency),
                        'stockQuantity' => $product->stock_quantity,
                        'ledgerStockSum' => $ledgerSum,
                        'isLedgerBalanced' => $ledgerSum === $product->stock_quantity,
                        'status' => $product->status->value,
                        'isFeatured' => $product->is_featured,
                        'featuredRank' => $product->featured_rank,
                        'imageUrl' => $presenter->imageUrl($product->primaryImage?->path),
                    ];
                })
                ->all(),
        );

        $categories = array_values(
            Category::query()
                ->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'sortOrder' => $category->sort_order,
                    'isActive' => $category->is_active,
                    'productCount' => (int) $category->getAttribute('products_count'),
                ])
                ->all(),
        );

        return Inertia::render('admin/Products', [
            'products' => $products,
            'categories' => $categories,
            'canRunAdminOnlyActions' => $request->user()->isAdmin(),
        ]);
    }

    public function storeProduct(
        Request $request,
        SaveProductAction $saveProduct,
    ): RedirectResponse {
        $validated = $request->validate($this->productRules());
        $validated['image_path'] = $this->resolveUploadedImagePath($request, $validated);

        $saveProduct->execute($request->user(), $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        return to_route('admin.products.index');
    }

    public function updateProduct(
        Request $request,
        Product $product,
        SaveProductAction $saveProduct,
    ): RedirectResponse {
        $validated = $request->validate($this->productRules($product->id));
        $validated['image_path'] = $this->resolveUploadedImagePath($request, $validated);

        $saveProduct->execute($request->user(), $validated, $product);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return to_route('admin.products.index');
    }

    public function storeCategory(
        Request $request,
        SaveCategoryAction $saveCategory,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('categories', 'slug')],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $saveCategory->execute($request->user(), $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category saved.')]);

        return to_route('admin.products.index');
    }

    public function adjustStock(
        Request $request,
        Product $product,
        InventoryService $inventory,
        AdminAuditRecorder $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'quantity_delta' => ['required', 'integer', 'not_in:0', 'min:-10000', 'max:10000'],
            'type' => ['required', 'string', Rule::in([
                StockMovementType::Restock->value,
                StockMovementType::ManualAdjustment->value,
            ])],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $type = StockMovementType::from((string) $validated['type']);
        $delta = (int) $validated['quantity_delta'];
        $reason = trim((string) $validated['reason']);

        $movement = $inventory->adjustStock(
            $product,
            $delta,
            $type,
            $request->user(),
            $reason,
        );

        $audit->record(
            $request->user(),
            'inventory.adjusted',
            sprintf(
                'Adjusted stock for %s (%s) by %+d (new stock: %d). Reason: %s',
                $product->name,
                $product->sku,
                $delta,
                $movement->resulting_quantity,
                $reason,
            ),
            'Product',
            $product->id,
            [
                'sku' => $product->sku,
                'delta' => $delta,
                'previous' => $movement->previous_quantity,
                'resulting' => $movement->resulting_quantity,
                'reason' => $reason,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inventory movement recorded.')]);

        return to_route('admin.products.index');
    }

    public function reconcileInventory(
        Request $request,
        InventoryService $inventory,
        AdminAuditRecorder $audit,
    ): RedirectResponse {
        $reconciledCount = $inventory->reconcileAll($request->user());

        $audit->record(
            $request->user(),
            'inventory.reconciled',
            sprintf('Ran catalog inventory ledger reconciliation (%d product ledger(s) adjusted).', $reconciledCount),
            'Product',
            null,
            ['reconciled_count' => $reconciledCount],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Inventory reconciliation complete (:count adjusted).', ['count' => $reconciledCount]),
        ]);

        return to_route('admin.products.index');
    }

    public function bulkUpdateStatus(
        Request $request,
        BulkUpdateProductStatusAction $bulkUpdate,
    ): RedirectResponse {
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:'.BulkUpdateProductStatusAction::MAX_BATCH_SIZE],
            'product_ids.*' => ['required', 'integer', 'exists:products,id'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
        ]);

        /** @var list<int> $productIds */
        $productIds = array_values(array_map('intval', $validated['product_ids']));
        $status = ProductStatus::from((string) $validated['status']);

        $count = $bulkUpdate->execute($request->user(), $productIds, $status);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Updated :count product(s) to :status.', ['count' => $count, 'status' => $status->value]),
        ]);

        return to_route('admin.products.index');
    }

    public function importCsv(
        Request $request,
        ImportProductsAction $importProducts,
    ): RedirectResponse {
        $validated = $request->validate([
            'csv_data' => ['required', 'string', 'max:100000'],
        ]);

        $count = $importProducts->execute($request->user(), (string) $validated['csv_data']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Imported :count product row(s) successfully.', ['count' => $count]),
        ]);

        return to_route('admin.products.index');
    }

    public function destroyProduct(
        Request $request,
        Product $product,
        DeleteProductsAction $deleteProducts,
    ): RedirectResponse {
        $deleteProducts->execute($request->user(), [$product->id]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Product deleted.'),
        ]);

        return to_route('admin.products.index');
    }

    public function bulkDestroyProducts(
        Request $request,
        DeleteProductsAction $deleteProducts,
    ): RedirectResponse {
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:'.DeleteProductsAction::MAX_BATCH_SIZE],
            'product_ids.*' => ['required', 'integer', 'exists:products,id'],
        ]);

        /** @var list<int> $productIds */
        $productIds = array_values(array_map('intval', $validated['product_ids']));

        $count = $deleteProducts->execute($request->user(), $productIds);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Deleted :count product(s).', ['count' => $count]),
        ]);

        return to_route('admin.products.index');
    }

    public function destroyCategory(
        Request $request,
        Category $category,
        DeleteCategoryAction $deleteCategory,
    ): RedirectResponse {
        $deleteCategory->execute($request->user(), $category);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category deleted.'),
        ]);

        return to_route('admin.products.index');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function productRules(?int $ignoreProductId = null): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:200'],
            'slug' => [
                'nullable',
                'string',
                'max:200',
                'alpha_dash',
                $ignoreProductId === null
                    ? Rule::unique('products', 'slug')
                    : Rule::unique('products', 'slug')->ignore($ignoreProductId),
            ],
            'sku' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9\-_]{3,64}$/',
                $ignoreProductId === null
                    ? Rule::unique('products', 'sku')
                    : Rule::unique('products', 'sku')->ignore($ignoreProductId),
            ],
            'short_description' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'is_featured' => ['nullable', 'boolean'],
            'featured_rank' => ['nullable', 'integer', 'min:1', 'max:999'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_url' => ['nullable', 'url:https', 'max:500'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveUploadedImagePath(Request $request, array $validated): ?string
    {
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file !== null && $file->isValid()) {
                $stored = $file->store('products', 'public');

                return is_string($stored) ? $stored : null;
            }
        }

        if (isset($validated['image_url']) && is_string($validated['image_url']) && trim($validated['image_url']) !== '') {
            return trim($validated['image_url']);
        }

        return null;
    }
}
