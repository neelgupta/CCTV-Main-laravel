<?php

namespace App\Http\Controllers;

use App\Exports\BoqProductExport;
use App\Exports\BoqProductImportSampleExport;
use App\Imports\BoqProductImport;
use App\Models\BoqCategory;
use App\Models\BoqProduct;
use App\Models\Company;
use App\Models\CompanyGstRate;
use App\Models\CompanyUnit;
use App\Services\MediaUploadService;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class CompanyBoqController extends Controller
{
    private function company(): ?Company
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return $user->company ?? $user->roleRecord?->company;
    }

    private function ensureAccess(): Company
    {
        $company = $this->company();
        abort_unless($company, 403);

        if (!($company->enable_boq ?? true)) {
            abort(403, 'BOQ feature is disabled for this company.');
        }

        return $company;
    }

    public function index(Request $request)
    {
        $company = $this->ensureAccess();
        $filters = $this->productFilters($request);

        $categories = BoqCategory::query()
            ->where('company_id', $company->id)
            ->withCount('products')
            ->orderBy('name')
            ->paginate(10, ['*'], 'category_page');

        $categoryOptions = BoqCategory::query()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

    // Paginate products using 'product_page' as the URL parameter
        $products = $this->applyProductFilters(
            BoqProduct::query()
                ->where('company_id', $company->id)
                ->with('category:id,name')
                ->orderBy('name'),
            $filters
        )
            ->paginate(10, ['*'], 'product_page');

        $units = CompanyUnit::query()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get();

        $gstRates = CompanyGstRate::query()
            ->where('company_id', $company->id)
            ->orderBy('rate')
            ->get();

        return view('settings.boq.index', compact('company', 'categories', 'categoryOptions', 'products', 'units', 'gstRates', 'filters'));
    }

    public function exportProducts(Request $request)
    {
        $company = $this->ensureAccess();

        $products = $this->applyProductFilters(
            BoqProduct::query()
                ->where('company_id', $company->id)
                ->with('category:id,name')
                ->orderBy('name'),
            $this->productFilters($request)
        )
            ->get();

        return Excel::download(
            new BoqProductExport($products),
            'products-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public function importProducts(Request $request)
    {
        $this->ensureAccess();

        $request->validate([
            'product_import_file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [
            'product_import_file.required' => 'Please choose a product import file.',
            'product_import_file.mimes' => 'Product import file must be CSV, XLS, or XLSX.',
        ]);

        $import = new BoqProductImport(auth()->user());

        Excel::import(
            $import,
            $request->file('product_import_file')
        );

        $summary = $import->summary();

        return redirect()
            ->route('settings.boq.index', ['tab' => 'products'])
            ->with('success', 'Product import completed.')
            ->with('boqProductImportSummary', $summary);
    }

    public function downloadProductImportSample()
    {
        $this->ensureAccess();

        return Excel::download(
            new BoqProductImportSampleExport(),
            'product-import-sample.xlsx'
        );
    }

    private function productFilters(Request $request): array
    {
        $validated = $request->validate([
            'product_search' => ['nullable', 'string', 'max:191'],
            'product_category_id' => ['nullable', 'integer', 'exists:boq_categories,id'],
            'product_gst' => ['nullable', 'numeric', 'min:0'],
            'product_status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'gte:price_min'],
        ]);

        return [
            'product_search' => isset($validated['product_search']) ? trim((string) $validated['product_search']) : null,
            'product_category_id' => isset($validated['product_category_id']) ? (int) $validated['product_category_id'] : null,
            'product_gst' => filled($validated['product_gst'] ?? null) ? (float) $validated['product_gst'] : null,
            'product_status' => $validated['product_status'] ?? null,
            'price_min' => filled($validated['price_min'] ?? null) ? (float) $validated['price_min'] : null,
            'price_max' => filled($validated['price_max'] ?? null) ? (float) $validated['price_max'] : null,
        ];
    }

    private function applyProductFilters($query, array $filters)
    {
        $search = isset($filters['product_search']) ? trim((string) $filters['product_search']) : null;
        $categoryId = $filters['product_category_id'] ?? null;
        $gst = $filters['product_gst'] ?? null;
        $status = $filters['product_status'] ?? null;
        $priceMin = $filters['price_min'] ?? null;
        $priceMax = $filters['price_max'] ?? null;

        return $query
            ->when($categoryId, fn ($builder) => $builder->where('category_id', $categoryId))
            ->when($gst !== null, fn ($builder) => $builder->where('gst', $gst))
            ->when($status === 'active', fn ($builder) => $builder->where('is_active', true))
            ->when($status === 'inactive', fn ($builder) => $builder->where('is_active', false))
            ->when($priceMin !== null, fn ($builder) => $builder->where('price', '>=', $priceMin))
            ->when($priceMax !== null, fn ($builder) => $builder->where('price', '<=', $priceMax))
            ->when($search !== '', function ($builder) use ($search) {
                $like = '%' . mb_strtolower($search) . '%';
                $numericSearch = preg_replace('/[^0-9.]/', '', $search);
                $normalizedSearch = mb_strtolower($search);

                $builder->where(function ($searchQuery) use ($like, $numericSearch, $normalizedSearch) {
                    $searchQuery->whereRaw('LOWER(name) like ?', [$like])
                        ->orWhereRaw('LOWER(brand) like ?', [$like])
                        ->orWhereRaw('LOWER(model) like ?', [$like])
                        ->orWhereRaw('LOWER(unit) like ?', [$like])
                        ->orWhereRaw('LOWER(description) like ?', [$like])
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->whereRaw('LOWER(name) like ?', [$like]));

                    if ($numericSearch !== '') {
                        $searchQuery->orWhereRaw('CAST(price AS CHAR) like ?', ['%' . $numericSearch . '%'])
                            ->orWhereRaw('CAST(gst AS CHAR) like ?', ['%' . $numericSearch . '%']);
                    }

                    if (in_array($normalizedSearch, ['active', 'inactive'], true)) {
                        $searchQuery->orWhere('is_active', $normalizedSearch === 'active');
                    }
                });
            });
    }

    public function storeCategory(Request $request)
    {
        $company = $this->ensureAccess();
        $data = $request->validate([
            'name' => 'required|string|max:191',
        ]);

        BoqCategory::create([
            'company_id' => $company->id,
            'name' => $data['name'],
        ]);

        return back()->with('success', 'BOQ category added successfully.');
    }

    public function updateCategory(Request $request, BoqCategory $category)
    {
        $company = $this->ensureAccess();
        abort_unless((int) $category->company_id === (int) $company->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:191',
        ]);

        $category->update($data);

        return back()->with('success', 'BOQ category updated successfully.');
    }

    public function destroyCategory(BoqCategory $category)
    {
        $company = $this->ensureAccess();
        abort_unless((int) $category->company_id === (int) $company->id, 403);

        if ($category->products()->exists()) {
            return back()->withErrors(['category' => 'Delete products first before removing this category.']);
        }

        $category->delete();

        return back()->with('success', 'BOQ category deleted successfully.');
    }

    public function storeProduct(Request $request)
    {
        $company = $this->ensureAccess();
        app(SubscriptionLimitService::class)->ensureWithinLimit((int) $company->id, 'products', 1, 'name');

        $data = $request->validate([
            'category_id' => 'required|exists:boq_categories,id',
            'name' => 'required|string|max:191',
            'brand' => 'nullable|string|max:191',
            'model' => 'nullable|string|max:191',
            'unit' => [
                'required',
                'string',
                'max:50',
                Rule::exists('company_units', 'name')->where('company_id', $company->id),
            ],
            'price' => 'required|numeric|min:0',
            'gst' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                $this->gstRateRule($company),
            ],
            'description' => 'nullable|string|max:1000',
            'product_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'is_active' => 'nullable|boolean',
        ]);

        $category = BoqCategory::query()
            ->where('company_id', $company->id)
            ->findOrFail($data['category_id']);

        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';
        $imagePath = $request->hasFile('product_image')
            ? app(MediaUploadService::class)->store($request->file('product_image'), 'company_'.$company->id.'/products', $disk)
            : null;

        BoqProduct::create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => $data['name'],
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'image_path' => $imagePath,
            'unit' => $data['unit'],
            'price' => $data['price'],
            'gst' => round((float) $data['gst'], 2),
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'BOQ product added successfully.');
    }

    public function updateProduct(Request $request, BoqProduct $product)
    {
        $company = $this->ensureAccess();
        abort_unless((int) $product->company_id === (int) $company->id, 403);

        $data = $request->validate([
            'category_id' => 'required|exists:boq_categories,id',
            'name' => 'required|string|max:191',
            'brand' => 'nullable|string|max:191',
            'model' => 'nullable|string|max:191',
            'unit' => [
                'required',
                'string',
                'max:50',
                Rule::exists('company_units', 'name')->where('company_id', $company->id),
            ],
            'price' => 'required|numeric|min:0',
            'gst' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                $this->gstRateRule($company),
            ],
            'description' => 'nullable|string|max:1000',
            'product_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'is_active' => 'nullable|boolean',
        ]);

        $category = BoqCategory::query()
            ->where('company_id', $company->id)
            ->findOrFail($data['category_id']);

        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';
        $imagePath = $product->image_path;
        if ($request->hasFile('product_image')) {
            if ($imagePath) {
                app(MediaUploadService::class)->delete($imagePath, $disk, (int) $company->id);
            }

            $imagePath = app(MediaUploadService::class)->store($request->file('product_image'), 'company_'.$company->id.'/products', $disk);
        }

        $product->update([
            'category_id' => $category->id,
            'name' => $data['name'],
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'image_path' => $imagePath,
            'unit' => $data['unit'],
            'price' => $data['price'],
            'gst' => round((float) $data['gst'], 2),
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'BOQ product updated successfully.');
    }

    public function productImage(BoqProduct $product)
    {
        $company = $this->ensureAccess();
        abort_unless((int) $product->company_id === (int) $company->id, 403);
        abort_if(blank($product->image_path), 404);

        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';
        $path = ltrim(str_replace('\\', '/', (string) $product->image_path), '/');
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path);
    }

    private function gstRateRule(Company $company): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($company): void {
            if ((float) $value === 0.0) {
                return;
            }

            $exists = CompanyGstRate::query()
                ->where('company_id', $company->id)
                ->where('rate', $value)
                ->exists();

            if (! $exists) {
                $fail('The selected GST rate is invalid.');
            }
        };
    }

    public function destroyProduct(BoqProduct $product)
    {
        $company = $this->ensureAccess();
        abort_unless((int) $product->company_id === (int) $company->id, 403);

        $product->delete();

        return back()->with('success', 'BOQ product deleted successfully.');
    }
}
