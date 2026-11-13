<?php

namespace App\Support;

/**
 * Subscription plan prices exclude GST; GST is added on top.
 *
 * GST is calculated per license and then multiplied by the license count, so a one-time
 * purchase and a Razorpay subscription (per-license amount x quantity) always charge the same.
 */
class SubscriptionPricing
{
    public static function gstRate(): float
    {
        return max(0.0, (float) setting('subscription_gst_percentage', 18));
    }

    /**
     * @return array{total: float, subtotal: float, gst_rate: float, gst_amount: float, unit_total: float}
     */
    public static function forPlan(float $unitPrice, int $quantity = 1, ?float $gstRate = null): array
    {
        $gstRate ??= self::gstRate();
        $unitPrice = round(max(0.0, $unitPrice), 2);
        $quantity = max(0, $quantity);
        $unitGst = round($unitPrice * $gstRate / 100, 2);
        $subtotal = round($unitPrice * $quantity, 2);
        $gstAmount = round($unitGst * $quantity, 2);

        return [
            'total' => round($subtotal + $gstAmount, 2),
            'subtotal' => $subtotal,
            'gst_rate' => $gstRate,
            'gst_amount' => $gstAmount,
            'unit_total' => round($unitPrice + $unitGst, 2),
        ];
    }

    /**
     * Splits an amount that already includes GST (e.g. a captured payment) into its parts,
     * the same way the subscription invoice shows it.
     *
     * @return array{total: float, subtotal: float, gst_rate: float, gst_amount: float}
     */
    public static function fromTotal(float $total, ?float $gstRate = null): array
    {
        $gstRate ??= self::gstRate();
        $total = round(max(0.0, $total), 2);
        $subtotal = $total > 0 && $gstRate > 0
            ? round($total / (1 + $gstRate / 100), 2)
            : $total;

        return [
            'total' => $total,
            'subtotal' => $subtotal,
            'gst_rate' => $gstRate,
            'gst_amount' => round($total - $subtotal, 2),
        ];
    }
}
