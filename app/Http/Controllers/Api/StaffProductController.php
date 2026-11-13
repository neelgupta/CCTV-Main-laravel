<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoqCategory;
use App\Models\BoqProduct;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class StaffProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');
        abort_unless($user->company?->enable_boq ?? true, 403, 'BOQ feature is disabled for this company.');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'category_id' => ['nullable', 'integer'],
            'include_inactive' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = isset($validated['search']) ? trim((string) $validated['search']) : null;
        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
        $includeInactive = $request->boolean('include_inactive');
        $perPage = (int) ($validated['per_page'] ?? 20);

        if ($categoryId !== null) {
            $categoryExists = BoqCategory::query()
                ->where('company_id', $user->company_id)
                ->whereKey($categoryId)
                ->exists();

            abort_unless($categoryExists, 404, 'Category not found.');
        }

        $query = BoqProduct::query()
            ->where('company_id', $user->company_id)
            ->when(! $includeInactive, fn ($builder) => $builder->where('is_active', true))
            ->when($categoryId !== null, fn ($builder) => $builder->where('category_id', $categoryId))
            ->when($search, function ($builder) use ($search) {
                $builder->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', '%' . $search . '%')
                        ->orWhere('brand', 'like', '%' . $search . '%')
                        ->orWhere('model', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->with('category:id,name')
            ->orderBy('name');

        $products = $query->paginate($perPage)->withQueryString();

        $categories = BoqCategory::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'products' => collect($products->items())
                ->map(fn (BoqProduct $product) => $this->formatProduct($product))
                ->values()
                ->all(),
            'meta' => [
                'total' => $products->total(),
                'categories' => $categories->map(fn (BoqCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])->values()->all(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                    'has_more_pages' => $products->hasMorePages(),
                ],
                'filters' => [
                    'search' => $search,
                    'category_id' => $categoryId,
                    'include_inactive' => $includeInactive,
                    'page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                ],
            ],
        ]);
    }

    public function image(Request $request, BoqProduct $product): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');
        abort_unless((int) $product->company_id === (int) $user->company_id, 403, 'You are not allowed to access this product image.');
        abort_if(blank($product->image_path), 404);

        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';
        $path = ltrim(str_replace('\\', '/', (string) $product->image_path), '/');
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, basename($path));
    }

    private function formatProduct(BoqProduct $product): array
    {
        return [
            'id' => $product->id,
            // 'public_id' => $product->getRouteKey(),
            'name' => $product->name,
            'brand' => $product->brand,
            'model' => $product->model,
            'unit' => $product->unit,
            'price' => (float) $product->price,
            'gst' => (float) $product->gst,
            'description' => $product->description,
            'is_active' => (bool) $product->is_active,
            // 'image_url' => $product->image_path
            //     ? route('api.staff.products.image', ['product' => $product->getRouteKey()])
            //     : null,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,
            // 'created_at' => $product->created_at?->toISOString(),
            // 'updated_at' => $product->updated_at?->toISOString(),
        ];
    }
}
