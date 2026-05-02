<?php

namespace App\Support;

class BrazilianCurrency
{
    public static function format(float|int|string|null $value): string
    {
        return 'R$ '.number_format((float) ($value ?? 0), 2, ',', '.');
    }

    public static function input(float|int|string|null $value): string
    {
        return number_format((float) ($value ?? 0), 2, ',', '.');
    }

    public static function normalize(float|int|string|null $value): float|string|null
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        $normalized = str_replace(['R$', ' '], '', $value);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : $value;
    }
}
