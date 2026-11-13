<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Company;
use App\Models\Lead;

class QuotationTaxCalculator
{
    public static function enrichClientTaxContext(array $formData, Lead $lead, ?Client $client = null): array
    {
        $clientBlock = is_array($formData['client'] ?? null) ? $formData['client'] : [];

        $stateId = $client?->state_id ?? $lead->state_id;
        $stateName = $client?->state?->name ?? $lead->state?->name ?? ($clientBlock['state_name'] ?? $clientBlock['state'] ?? null);
        $cityId = $client?->city_id ?? $lead->city_id;
        $cityName = $client?->city?->name ?? $lead->city?->name ?? ($clientBlock['city_name'] ?? $clientBlock['city'] ?? null);

        $formData['client'] = array_merge($clientBlock, array_filter([
            'state_id' => $stateId,
            'state_name' => $stateName,
            'city_id' => $cityId,
            'city_name' => $cityName,
        ], static fn ($value) => $value !== null && $value !== ''));

        return $formData;
    }

    public static function resolveCustomerStateName(array $formData, Lead $lead, ?Client $client = null): string
    {
        return trim((string) (
            $client?->state?->name
            ?? $lead->state?->name
            ?? data_get($formData, 'client.state_name')
            ?? data_get($formData, 'client.state')
            ?? ''
        ));
    }

    public static function resolveCompanyStateName(?Company $company): string
    {
        return trim((string) ($company?->state ?? ''));
    }

    public static function isIntraState(?string $companyStateName, ?string $customerStateName): bool
    {
        $companyState = trim((string) $companyStateName);
        $customerState = trim((string) $customerStateName);

        return $companyState !== ''
            && $customerState !== ''
            && strcasecmp($companyState, $customerState) === 0;
    }

    public static function calculate(iterable $items, float $discount = 0, bool $isIntraState = false): array
    {
        $normalizedItems = [];

        foreach ($items as $item) {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? $item['rate'] ?? $item['unit_price'] ?? 0);
            $baseTotal = (float) ($item['base_total'] ?? ($qty * $price));
            $gstRate = min(100, max(0, (float) ($item['gst'] ?? 0)));
            $gstAmount = (float) ($item['gst_amount'] ?? (($baseTotal * $gstRate) / 100));

            $normalizedItems[] = array_merge($item, [
                'qty' => $qty,
                'price' => $price,
                'base_total' => $baseTotal,
                'gst' => $gstRate,
                'gst_amount' => $gstAmount,
                'total' => (float) ($item['total'] ?? $item['amount'] ?? ($baseTotal + $gstAmount)),
            ]);
        }

        $subtotal = array_sum(array_column($normalizedItems, 'base_total'));
        $effectiveDiscount = min(max(0, $discount), max(0, $subtotal));
        $taxableAmount = max(0, $subtotal - $effectiveDiscount);
        $discountRatio = $subtotal > 0 ? ($effectiveDiscount / $subtotal) : 0.0;

        // GST is calculated at full item price; discount only reduces the product base total
        $gstAmount = 0.0;
        foreach ($normalizedItems as &$normalizedItem) {
            $discountedBaseTotal = $normalizedItem['base_total'] * (1 - $discountRatio);
            $normalizedItem['discounted_base_total'] = $discountedBaseTotal;
            $normalizedItem['discounted_gst_amount'] = $normalizedItem['gst_amount'];
            $gstAmount += $normalizedItem['gst_amount'];
        }
        unset($normalizedItem);

        $gstAmount = round($gstAmount, 2);
        $grandTotal = round($taxableAmount + $gstAmount, 2);
        $derivedGstPercent = $taxableAmount > 0 ? round(($gstAmount / $taxableAmount) * 100, 2) : 0.0;

        $sgstAmount = $isIntraState ? round($gstAmount / 2, 2) : 0.0;
        $cgstAmount = $isIntraState ? round($gstAmount - $sgstAmount, 2) : 0.0;
        $igstAmount = $isIntraState ? 0.0 : $gstAmount;

        return [
            'items' => $normalizedItems,
            'subtotal' => round($subtotal, 2),
            'discount' => round($effectiveDiscount, 2),
            'taxable_amount' => round($taxableAmount, 2),
            'gst_amount' => $gstAmount,
            'grand_total' => $grandTotal,
            'derived_gst_percent' => $derivedGstPercent,
            'is_intra_state' => $isIntraState,
            'sgst_amount' => $sgstAmount,
            'cgst_amount' => $cgstAmount,
            'igst_amount' => $igstAmount,
        ];
    }
}
