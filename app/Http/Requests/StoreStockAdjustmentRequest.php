<?php

namespace App\Http\Requests;

use App\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasFinancialPrivileges() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $this->user()?->company_id)],
            'direction' => ['required', Rule::in([StockMovement::DIRECTION_IN, StockMovement::DIRECTION_OUT])],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'reason' => ['required', Rule::in([StockMovement::TYPE_PURCHASE, StockMovement::TYPE_LOSS, StockMovement::TYPE_INTERNAL_USE, 'correction', 'other'])],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'daily_stock_check_id' => ['nullable', 'integer', Rule::exists('daily_stock_checks', 'id')->where('company_id', $this->user()?->company_id)],
            'stock_count_id' => ['nullable', 'integer', Rule::exists('stock_counts', 'id')->where('company_id', $this->user()?->company_id)],
            'notes' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('reason'), [StockMovement::TYPE_LOSS, 'correction'], true) || $this->filled('daily_stock_check_id') || $this->filled('stock_count_id')),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
