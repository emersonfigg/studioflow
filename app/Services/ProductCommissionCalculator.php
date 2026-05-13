<?php

namespace App\Services;

use App\Models\Product;

class ProductCommissionCalculator
{
    /**
     * Calculate the commission amount for a sold product line, freezing the
     * commission settings as snapshots so historical records never change when
     * the product setup is updated later.
     *
     * Behavior:
     * - Returns 0 when the product has no active commission setting.
     * - "fixed" commission multiplies the configured value by quantity, regardless of discounts.
     * - "percentage" commission applies the configured rate over the effective (post discount) line subtotal.
     *
     * @return array{type: ?string, value: ?float, amount: float}
     */
    public function calculate(Product $product, int $quantity, float $effectiveSubtotal): array
    {
        $type = $product->commission_type;
        $value = $product->commission_value !== null ? round((float) $product->commission_value, 2) : null;

        if (! $product->hasCommission() || $value === null) {
            return [
                'type' => null,
                'value' => null,
                'amount' => 0.0,
            ];
        }

        $quantity = max(1, $quantity);

        $amount = match ($type) {
            Product::COMMISSION_TYPE_FIXED => round($value * $quantity, 2),
            Product::COMMISSION_TYPE_PERCENTAGE => round(max(0.0, $effectiveSubtotal) * ($value / 100), 2),
            default => 0.0,
        };

        return [
            'type' => $type,
            'value' => $value,
            'amount' => $amount,
        ];
    }
}
