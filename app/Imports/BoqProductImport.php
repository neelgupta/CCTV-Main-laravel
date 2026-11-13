<?php

namespace App\Imports;

use App\Models\BoqCategory;
use App\Models\BoqProduct;
use App\Models\CompanyGstRate;
use App\Models\CompanyUnit;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BoqProductImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    private array $summary = [
        'total_rows' => 0,
        'imported_products' => 0,
        'created_categories' => 0,
        'created_units' => 0,
        'skipped_duplicate_products' => 0,
        'skipped_products' => 0,
        'failed_products' => [],
    ];

    /**
     * @var array<string, BoqCategory>
     */
    private array $categoryCache = [];

    /**
     * @var array<string, CompanyUnit>
     */
    private array $unitCache = [];

    /**
     * @var Collection<int, BoqProduct>
     */
    private Collection $existingProducts;

    /**
     * @var array<string, true>
     */
    private array $existingProductSignatures = [];

    /**
     * @var array<string, true>
     */
    private array $seenProductSignatures = [];

    public function __construct(private readonly User $user)
    {
    }

    public function collection(Collection $rows): void
    {
        $companyId = (int) $this->user->company_id;
        $this->summary['total_rows'] = $rows->count();

        $this->categoryCache = BoqCategory::query()
            ->where('company_id', $companyId)
            ->get()
            ->mapWithKeys(fn (BoqCategory $category) => [$this->normalizeLookupKey($category->name) => $category])
            ->all();

        $this->unitCache = CompanyUnit::query()
            ->where('company_id', $companyId)
            ->get()
            ->mapWithKeys(fn (CompanyUnit $unit) => [$this->normalizeLookupKey($unit->name) => $unit])
            ->all();

        $this->existingProducts = BoqProduct::query()
            ->where('company_id', $companyId)
            ->get();

        $this->existingProductSignatures = $this->existingProducts
            ->mapWithKeys(fn (BoqProduct $product) => [$this->productSignatureFromModel($product, $companyId) => true])
            ->all();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = $this->normalizeRow($row->toArray());

            try {
                $this->processRow($companyId, $rowNumber, $data);
            } catch (\Throwable $exception) {
                $this->recordFailure($rowNumber, $exception->getMessage());
            }
        }
    }

    public function summary(): array
    {
        return $this->summary;
    }

    private function processRow(int $companyId, int $rowNumber, array $data): void
    {
        $categoryName = $this->requiredValue($data, ['category', 'category_name']);
        $name = $this->requiredValue($data, ['name', 'product_name']);
        $unitName = $this->requiredValue($data, ['unit']);
        $price = $this->numericValue($data, ['price', 'unit_price']);

        if ($categoryName === null || $name === null || $unitName === null || $price === null) {
            $this->recordFailure($rowNumber, 'category, name, unit, and price are required.');

            return;
        }

        if (mb_strlen($categoryName) > 191) {
            $this->recordFailure($rowNumber, 'category must not exceed 191 characters.');

            return;
        }

        if (mb_strlen($name) > 191) {
            $this->recordFailure($rowNumber, 'name must not exceed 191 characters.');

            return;
        }

        if (mb_strlen($unitName) > 50) {
            $this->recordFailure($rowNumber, 'unit must not exceed 50 characters.');

            return;
        }

        $brand = $this->optionalValue($data, ['brand']);
        $model = $this->optionalValue($data, ['model']);
        $description = $this->optionalValue($data, ['description']);

        if ($brand !== null && mb_strlen($brand) > 191) {
            $this->recordFailure($rowNumber, 'brand must not exceed 191 characters.');

            return;
        }

        if ($model !== null && mb_strlen($model) > 191) {
            $this->recordFailure($rowNumber, 'model must not exceed 191 characters.');

            return;
        }

        if ($description !== null && mb_strlen($description) > 1000) {
            $this->recordFailure($rowNumber, 'description must not exceed 1000 characters.');

            return;
        }

        $gst = $this->numericValue($data, ['gst']);
        $normalizedGst = round($gst ?? 0.0, 2);

        if ($gst !== null && ($normalizedGst < 0 || $normalizedGst > 100)) {
            $this->recordFailure($rowNumber, 'gst must be between 0 and 100.');

            return;
        }

        $gstRates = CompanyGstRate::query()
            ->where('company_id', $companyId)
            ->pluck('rate')
            ->map(fn ($rate) => round((float) $rate, 2))
            ->push(0.0)
            ->unique()
            ->values();

        if (! $gstRates->contains($normalizedGst)) {
            $this->recordFailure($rowNumber, "gst '{$normalizedGst}' is not available for this company.");

            return;
        }

        if ($price < 0) {
            $this->recordFailure($rowNumber, 'price must be 0 or greater.');

            return;
        }

        $isActive = $this->booleanValue($data, ['is_active', 'active'], true);

        $createdCategory = null;
        $createdUnit = null;

        $result = DB::transaction(function () use (
            $companyId,
            $categoryName,
            $name,
            $unitName,
            $price,
            $brand,
            $model,
            $description,
            $normalizedGst,
            $isActive,
            &$createdCategory,
            &$createdUnit
        ) {
            [$category, $categoryWasCreated] = $this->resolveCategory($companyId, $categoryName);
            [$unit, $unitWasCreated] = $this->resolveUnit($companyId, $unitName);

            $createdCategory = $categoryWasCreated ? $category : null;
            $createdUnit = $unitWasCreated ? $unit : null;

            $payload = [
                'company_id' => $companyId,
                'category_id' => $category->id,
                'name' => $name,
                'brand' => $brand,
                'model' => $model,
                'unit' => $unit->name,
                'price' => round($price, 2),
                'gst' => $normalizedGst,
                'description' => $description,
                'is_active' => $isActive,
            ];

            $signature = $this->productSignature($payload, $companyId, $unit->name);

            if (isset($this->existingProductSignatures[$signature]) || isset($this->seenProductSignatures[$signature])) {
                return [
                    'product' => null,
                    'duplicate_skipped' => true,
                    'category' => $category,
                    'category_was_created' => $categoryWasCreated,
                    'unit' => $unit,
                    'unit_was_created' => $unitWasCreated,
                ];
            }

            app(SubscriptionLimitService::class)->ensureWithinLimit($companyId, 'products', 1, 'name');

            $product = BoqProduct::create($payload);
            $this->existingProducts->push($product);
            $this->existingProductSignatures[$signature] = true;
            $this->seenProductSignatures[$signature] = true;

            return [
                'product' => $product,
                'product_was_created' => true,
                'duplicate_skipped' => false,
                'category' => $category,
                'category_was_created' => $categoryWasCreated,
                'unit' => $unit,
                'unit_was_created' => $unitWasCreated,
            ];
        });

        if ($result['category_was_created']) {
            $this->summary['created_categories']++;
            $this->categoryCache[$this->normalizeLookupKey($result['category']->name)] = $result['category'];
        } else {
            $this->categoryCache[$this->normalizeLookupKey($result['category']->name)] = $result['category'];
        }

        if ($result['unit_was_created']) {
            $this->summary['created_units']++;
            $this->unitCache[$this->normalizeLookupKey($result['unit']->name)] = $result['unit'];
        } else {
            $this->unitCache[$this->normalizeLookupKey($result['unit']->name)] = $result['unit'];
        }

        if (! empty($result['duplicate_skipped'])) {
            $this->summary['skipped_duplicate_products']++;
            $this->summary['skipped_products'] = $this->summary['skipped_duplicate_products'];

            return;
        }

        $this->summary['imported_products']++;
    }

    /**
     * @return array{0: BoqCategory, 1: bool}
     */
    private function resolveCategory(int $companyId, string $categoryName): array
    {
        $normalized = $this->normalizeLookupKey($categoryName);

        if (isset($this->categoryCache[$normalized])) {
            return [$this->categoryCache[$normalized], false];
        }

        $existing = BoqCategory::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
            ->first();

        if ($existing) {
            $this->categoryCache[$normalized] = $existing;

            return [$existing, false];
        }

        $category = BoqCategory::create([
            'company_id' => $companyId,
            'name' => trim($categoryName),
        ]);

        return [$category, true];
    }

    /**
     * @return array{0: CompanyUnit, 1: bool}
     */
    private function resolveUnit(int $companyId, string $unitName): array
    {
        $normalized = $this->normalizeLookupKey($unitName);

        if (isset($this->unitCache[$normalized])) {
            return [$this->unitCache[$normalized], false];
        }

        $existing = CompanyUnit::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
            ->first();

        if ($existing) {
            $this->unitCache[$normalized] = $existing;

            return [$existing, false];
        }

        $unit = CompanyUnit::create([
            'company_id' => $companyId,
            'name' => trim($unitName),
        ]);

        return [$unit, true];
    }

    private function recordFailure(int $rowNumber, string $reason): void
    {
        $message = "Row {$rowNumber}: {$reason}";

        $this->summary['failed_products'][] = $message;
        logger()->warning('BOQ product import row failed.', [
            'row' => $rowNumber,
            'reason' => $reason,
        ]);
    }

    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalizedKey = Str::of((string) $key)
                ->trim()
                ->lower()
                ->replace(['-', ' '], '_')
                ->toString();

            $normalized[$normalizedKey] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }

    private function normalizeLookupKey(mixed $value): string
    {
        return Str::lower(trim((string) $value));
    }

    private function productSignatureFromModel(BoqProduct $product, int $companyId): string
    {
        return $this->productSignature([
            'company_id' => $companyId,
            'category_id' => (int) $product->category_id,
            'name' => $product->name,
            'brand' => $product->brand,
            'model' => $product->model,
            'unit' => $product->unit,
            'price' => $product->price,
            'gst' => $product->gst,
            'description' => $product->description,
            'is_active' => (bool) $product->is_active,
        ], $companyId, (string) $product->unit);
    }

    private function productSignature(array $payload, int $companyId, string $unitName): string
    {
        $signatureData = [
            'company_id' => $companyId,
            'category_id' => (int) ($payload['category_id'] ?? 0),
            'name' => $this->normalizeComparableText($payload['name'] ?? null),
            'brand' => $this->normalizeComparableText($payload['brand'] ?? null),
            'model' => $this->normalizeComparableText($payload['model'] ?? null),
            'unit' => $this->normalizeComparableText($unitName),
            'price' => $this->normalizeComparableNumber($payload['price'] ?? null),
            'gst' => $this->normalizeComparableNumber($payload['gst'] ?? null),
            'description' => $this->normalizeComparableText($payload['description'] ?? null),
            'is_active' => (bool) ($payload['is_active'] ?? false),
        ];

        return md5(json_encode($signatureData, JSON_THROW_ON_ERROR));
    }

    private function normalizeComparableText(mixed $value): string
    {
        return Str::lower(trim((string) ($value ?? '')));
    }

    private function normalizeComparableNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function requiredValue(array $data, array $keys): ?string
    {
        return $this->optionalValue($data, $keys);
    }

    private function optionalValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if ($value === null) {
                continue;
            }

            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private function numericValue(array $data, array $keys): ?float
    {
        $value = $this->optionalValue($data, $keys);

        return $value !== null && is_numeric($value) ? (float) $value : null;
    }

    private function booleanValue(array $data, array $keys, bool $default = false): bool
    {
        $value = $this->optionalValue($data, $keys);

        if ($value === null) {
            return $default;
        }

        return in_array(Str::lower($value), ['1', 'true', 'yes', 'active'], true);
    }
}
