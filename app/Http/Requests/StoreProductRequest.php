<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBrazilianCurrency;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    use NormalizesBrazilianCurrency;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'stock_quantity' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'unit' => ['nullable', 'string', 'max:32'],
            'track_stock' => ['nullable', 'boolean'],
            'low_stock_alert' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'commission_type' => ['nullable', Rule::in(Product::COMMISSION_TYPES)],
            'commission_value' => ['nullable', 'numeric', 'min:0'],
            'recommended_repurchase_days' => ['nullable', 'integer', 'min:1', 'max:730'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('commission_type');
            $rawValue = $this->input('commission_value');
            $value = $rawValue === null || $rawValue === '' ? null : (float) $rawValue;

            if ($type === Product::COMMISSION_TYPE_PERCENTAGE && $value !== null && $value > 100) {
                $validator->errors()->add('commission_value', 'O percentual de comissao nao pode ser maior que 100%.');
            }

            if ($type !== null && $type !== '' && ($value === null || $value <= 0)) {
                $validator->errors()->add('commission_value', 'Informe um valor de comissao maior que zero ou selecione "Sem comissao".');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCurrencyFields(['price', 'commission_value', 'cost_price']);

        $this->merge([
            'track_stock' => (int) $this->input('track_stock', 1) === 1,
            'low_stock_alert' => (int) $this->input('low_stock_alert', 1) === 1,
        ]);

        foreach (['minimum_stock', 'cost_price'] as $field) {
            if ($this->input($field) === '' || $this->input($field) === null) {
                $this->merge([$field => null]);
            }
        }

        $type = $this->normalizeCommissionType($this->input('commission_type'));
        $this->merge(['commission_type' => $type]);

        if ($type === '' || $type === 'none') {
            $this->merge([
                'commission_type' => null,
                'commission_value' => null,
            ]);
        }

        $value = $this->input('commission_value');
        if ($this->input('commission_type') === null && ($value === '' || $value === null)) {
            $this->merge(['commission_value' => null]);
        }

        $repurchase = $this->input('recommended_repurchase_days');
        if ($repurchase === '' || $repurchase === '0' || $repurchase === 0) {
            $this->merge(['recommended_repurchase_days' => null]);
        }
    }

    private function normalizeCommissionType(mixed $type): mixed
    {
        return match ($type) {
            'percent' => Product::COMMISSION_TYPE_PERCENTAGE,
            default => $type,
        };
    }
}
