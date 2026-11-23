<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Actions\SaveProductReviewAction;
use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductReviewController extends Controller
{
    public function store(
        Request $request,
        Product $product,
        SaveProductReviewAction $saveReview,
    ): RedirectResponse {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'comment' => ['nullable', 'string', 'max:1500'],
        ]);

        $saveReview->execute(
            $request->user(),
            $product,
            (int) $validated['rating'],
            isset($validated['title']) ? (string) $validated['title'] : null,
            isset($validated['comment']) ? (string) $validated['comment'] : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Thank you! Your verified product rating and feedback have been saved.'),
        ]);

        return back();
    }
}
