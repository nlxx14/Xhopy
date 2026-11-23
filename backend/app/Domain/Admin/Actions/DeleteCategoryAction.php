<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Services\AdminAuditRecorder;
use App\Domain\Catalog\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteCategoryAction
{
    public function __construct(
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function execute(User $actor, Category $category): void
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => __(
                    'Cannot delete category ":name" while it still contains products. Delete or move its products first.',
                    ['name' => $category->name],
                ),
            ]);
        }

        DB::transaction(function () use ($actor, $category): void {
            $id = $category->id;
            $name = $category->name;
            $slug = $category->slug;

            $category->delete();

            $this->audit->record(
                $actor,
                'category.deleted',
                sprintf('Deleted category "%s" (%s).', $name, $slug),
                'Category',
                $id,
                [
                    'id' => $id,
                    'name' => $name,
                    'slug' => $slug,
                ],
            );
        });
    }
}
