<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePdvSalePaymentMethodRequest extends FormRequest
{
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
            'payment_method' => ['required', Rule::in(Payment::PAYMENT_METHODS)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
