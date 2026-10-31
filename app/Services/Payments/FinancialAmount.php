<?php

namespace App\Services\Payments;

final class FinancialAmount
{
    /** Exact cents, without floats in arithmetic or an extra PHP extension. */
    public static function cents(mixed $value): ?int
    {
        if ((! is_string($value) && ! is_int($value) && ! is_float($value))
            || ! preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', (string) $value, $parts)) {
            return null;
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }
}
