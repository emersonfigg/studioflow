<?php

namespace App\Http\Requests\Concerns;

use App\Support\BrazilianCurrency;

trait NormalizesBrazilianCurrency
{
    /**
     * @param  list<string>  $fields
     */
    protected function normalizeCurrencyFields(array $fields): void
    {
        $normalized = [];

        foreach ($fields as $field) {
            if ($this->has($field)) {
                $normalized[$field] = BrazilianCurrency::normalize($this->input($field));
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
