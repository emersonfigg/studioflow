<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBrazilianCurrency;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashOutflowRequest extends FormRequest
{
    use NormalizesBrazilianCurrency;

    public function authorize(): bool
    {
        return $this->user()?->hasFinancialPrivileges() === true
            && $this->user()?->company_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cash_register_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['nullable', Rule::in(Payment::PAYMENT_METHODS)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCurrencyFields(['amount']);
    }
}
