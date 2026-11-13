<?php

namespace App\Services;

use App\Models\BoqProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingDocumentItemBuilder
{
    /**
     * Validate the submitted product rows against the company's BOQ catalog and build
     * the normalized items array stored on ticket_documents.items.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildItems(array $validated, int $companyId, bool $manualProductEntry = false): array
    {
        $productIds = is_array($validated['product_id'] ?? null) ? array_values($validated['product_id']) : [];
        $names = is_array($validated['name'] ?? null) ? array_values($validated['name']) : [];
        $quantities = is_array($validated['qty'] ?? null) ? array_values($validated['qty']) : [];
        $prices = is_array($validated['price'] ?? null) ? array_values($validated['price']) : [];
        $gstRates = is_array($validated['gst'] ?? null) ? array_values($validated['gst']) : [];
        $notes = is_array($validated['note'] ?? null) ? array_values($validated['note']) : [];
        $descriptions = is_array($validated['description'] ?? null) ? array_values($validated['description']) : [];
        $brandModels = is_array($validated['brand_model'] ?? null) ? array_values($validated['brand_model']) : [];
        $units = is_array($validated['unit'] ?? null) ? array_values($validated['unit']) : [];

        $rowCount = max(
            count($productIds),
            count($names),
            count($quantities),
            count($prices),
            count($gstRates),
            count($notes),
            count($descriptions),
            count($brandModels),
            count($units)
        );

        if ($rowCount < 1) {
            throw ValidationException::withMessages([$manualProductEntry ? 'name' : 'product_id' => 'Please add at least one item.']);
        }

        $normalizedProductIds = collect($productIds)
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->values();

        if (! $manualProductEntry && $normalizedProductIds->isEmpty() && collect($names)->filter(fn ($value) => filled($value))->isEmpty()) {
            throw ValidationException::withMessages(['product_id' => 'Please select at least one service.']);
        }

        if ($manualProductEntry && collect($names)->filter(fn ($value) => filled($value))->isEmpty()) {
            throw ValidationException::withMessages(['name' => 'Please enter at least one product name.']);
        }

        $services = BoqProduct::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $normalizedProductIds->unique()->all())
            ->get(['id', 'name', 'brand', 'model', 'unit', 'price', 'gst', 'description'])
            ->keyBy('id');

        if ($services->count() !== $normalizedProductIds->unique()->count()) {
            throw ValidationException::withMessages(['product_id' => 'Some selected services are not available.']);
        }

        $items = [];

        for ($index = 0; $index < $rowCount; $index++) {
            $productId = (int) ($productIds[$index] ?? 0);
            $service = $productId > 0 ? $services->get($productId) : null;
            $submittedName = trim((string) ($names[$index] ?? ''));
            $submittedUnit = trim((string) ($units[$index] ?? ''));
            $qty = array_key_exists($index, $quantities) && $quantities[$index] !== null && $quantities[$index] !== ''
                ? max(0.01, round((float) $quantities[$index], 4))
                : 1;
            $price = array_key_exists($index, $prices) && $prices[$index] !== null && $prices[$index] !== ''
                ? round((float) $prices[$index], 2)
                : round((float) ($service->price ?? 0), 2);
            $baseTotal = round($qty * $price, 2);
            $gstRate = array_key_exists($index, $gstRates) && $gstRates[$index] !== null && $gstRates[$index] !== ''
                ? round((float) $gstRates[$index], 2)
                : round((float) ($service->gst ?? 0), 2);
            $gstAmount = round(($baseTotal * $gstRate) / 100, 2);
            $itemName = $service?->name ?: $submittedName;

            if ($itemName === '') {
                continue;
            }

            $items[] = [
                'product_id' => $service ? (int) $service->id : null,
                'name' => $itemName,
                'brand_model' => filled($brandModels[$index] ?? null)
                    ? Str::squish((string) $brandModels[$index])
                    : trim(implode(' / ', array_filter([(string) ($service->brand ?? ''), (string) ($service->model ?? '')]))),
                'qty' => $qty,
                'price' => $price,
                'base_total' => $baseTotal,
                'unit' => $submittedUnit !== '' ? $submittedUnit : (string) ($service->unit ?? ''),
                'gst' => $gstRate,
                'gst_amount' => $gstAmount,
                'total' => round($baseTotal + $gstAmount, 2),
                'note' => filled($notes[$index] ?? null) ? Str::squish((string) $notes[$index]) : null,
                'description' => filled($descriptions[$index] ?? null)
                    ? Str::squish((string) $descriptions[$index])
                    : ($service?->description ?? null),
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages([$manualProductEntry ? 'name' : 'product_id' => 'Please add at least one item.']);
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{subtotal: float, discount: float, gst_amount: float, total: float}
     */
    public function buildSummary(array $items, float $discount): array
    {
        $subtotal = round(collect($items)->sum(fn (array $item) => (float) ($item['base_total'] ?? 0)), 2);
        $gstAmount = round(collect($items)->sum(fn (array $item) => (float) ($item['gst_amount'] ?? 0)), 2);
        $discount = min(round($discount, 2), $subtotal + $gstAmount);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'gst_amount' => $gstAmount,
            'total' => max(0, round($subtotal - $discount + $gstAmount, 2)),
        ];
    }
}
