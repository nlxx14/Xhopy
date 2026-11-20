<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Services\AdminAuditRecorder;
use App\Domain\Catalog\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SaveCategoryAction
{
    public function __construct(private readonly AdminAuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $actor, array $attributes, ?Category $existing = null): Category
    {
        return DB::transaction(function () use ($actor, $attributes, $existing): Category {
            $name = trim((string) $attributes['name']);
            $slug = isset($attributes['slug']) && trim((string) $attributes['slug']) !== ''
                ? Str::slug((string) $attributes['slug'])
                : Str::slug($name);

            $payload = [
                'name' => $name,
                'slug' => $slug,
                'description' => isset($attributes['description']) && trim((string) $attributes['description']) !== ''
                    ? trim((string) $attributes['description'])
                    : null,
                'sort_order' => (int) ($attributes['sort_order'] ?? 0),
                'is_active' => (bool) ($attributes['is_active'] ?? true),
            ];

            $isCreating = $existing === null;

            if ($existing !== null) {
                $existing->fill($payload)->save();
                $category = $existing;
            } else {
                $category = Category::query()->create($payload);
            }

            $this->audit->record(
                $actor,
                $isCreating ? 'category.created' : 'category.updated',
                sprintf('%s category "%s" (%s).', $isCreating ? 'Created' : 'Updated', $category->name, $category->slug),
                'Category',
                $category->id,
                ['slug' => $category->slug, 'is_active' => $category->is_active],
            );

            return $category;
        });
    }
}
