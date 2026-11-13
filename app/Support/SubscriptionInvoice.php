<?php

namespace App\Support;

use App\Models\Company;
use App\Models\CompanySubscription;

/**
 * Builds the values printed on a subscription invoice, shared by the HTML invoice page
 * and the bulk PDF download so both always show the same figures.
 */
class SubscriptionInvoice
{
    /**
     * Freezes the seller, party, plan and GST details at purchase time so later edits to
     * settings, the company or the plan do not change invoices already issued.
     */
    public static function snapshot(CompanySubscription $subscription, ?Company $company = null): array
    {
        $partyCompany = $company ?? $subscription->company
            ?? ($subscription->company_id ? Company::query()->find($subscription->company_id) : null);
        $plan = $subscription->plan;

        $businessName = trim((string) setting('business_name', $partyCompany?->name));
        $businessEmail = trim((string) setting('support_email', $partyCompany?->email));
        $businessMobile = trim((string) setting('mobile_number', $partyCompany?->mobile_number ?? $partyCompany?->phone_number));

        return [
            'seller' => [
                'name' => $businessName,
                'gst' => trim((string) setting('gst_number', $partyCompany?->gst_number)),
                'email' => $businessEmail,
                'mobile' => $businessMobile,
                'address' => trim((string) setting('address', trim(collect([
                    $partyCompany?->address,
                    $partyCompany?->city,
                    $partyCompany?->state,
                ])->filter()->implode(', ')))),
            ],
            'party' => [
                'name' => trim((string) ($partyCompany?->name ?? $businessName)),
                'address' => trim((string) collect([
                    $partyCompany?->address,
                    $partyCompany?->city,
                    $partyCompany?->state,
                ])->filter()->implode(', ')),
                'mobile' => trim((string) ($partyCompany?->mobile_number ?? $partyCompany?->phone_number ?? $businessMobile)),
                'email' => trim((string) ($partyCompany?->email ?? $businessEmail)),
                'gst' => trim((string) ($partyCompany?->gst_number ?? '')),
            ],
            'plan' => [
                'name' => $plan?->name ?? 'Subscription Plan',
            ],
            'gst_rate' => SubscriptionPricing::gstRate(),
            'captured_at' => now()->toIso8601String(),
        ];
    }

    public static function data(CompanySubscription $subscription, ?Company $company = null): array
    {
        // Older subscriptions created before snapshots existed fall back to live details.
        $snapshot = $subscription->invoice_snapshot ?: self::snapshot($subscription, $subscription->company ?? $company);
        $seller = $snapshot['seller'] ?? [];
        $party = $snapshot['party'] ?? [];

        $amount = (float) $subscription->amount_paid;
        // Amount paid includes GST; split it out for the tax summary.
        $taxSplit = SubscriptionPricing::fromTotal($amount, isset($snapshot['gst_rate']) ? (float) $snapshot['gst_rate'] : null);

        $amountWords = trim(class_exists(\NumberFormatter::class)
            ? preg_replace('/\s+/', ' ', \NumberFormatter::create('en', \NumberFormatter::SPELLOUT)->format((int) round($amount)))
            : '');

        return [
            'businessName' => (string) ($seller['name'] ?? ''),
            'businessGst' => (string) ($seller['gst'] ?? ''),
            'businessEmail' => (string) ($seller['email'] ?? ''),
            'businessMobile' => (string) ($seller['mobile'] ?? ''),
            'businessAddress' => (string) ($seller['address'] ?? ''),
            'partyCompanyName' => (string) ($party['name'] ?? ''),
            'partyCompanyAddress' => (string) ($party['address'] ?? ''),
            'partyCompanyMobile' => (string) ($party['mobile'] ?? ''),
            'partyCompanyEmail' => (string) ($party['email'] ?? ''),
            'partyCompanyGst' => (string) ($party['gst'] ?? ''),
            'invoiceNumber' => $subscription->billing_reference ?: ('SUB-' . str_pad((string) $subscription->id, 6, '0', STR_PAD_LEFT)),
            'invoiceDate' => optional($subscription->created_at)->format('d-m-Y') ?: now()->format('d-m-Y'),
            'planName' => (string) ($snapshot['plan']['name'] ?? 'Subscription Plan'),
            'paymentMethod' => $subscription->payment_method ?: 'Cash',
            'status' => strtoupper(str($subscription->status)->headline()->toString()),
            'gstRate' => $taxSplit['gst_rate'],
            'subtotalFormatted' => number_format($taxSplit['subtotal'], 2),
            'gstAmountFormatted' => number_format($taxSplit['gst_amount'], 2),
            'grandTotal' => number_format($amount, 2),
            'amountWords' => $amountWords,
        ];
    }
}
