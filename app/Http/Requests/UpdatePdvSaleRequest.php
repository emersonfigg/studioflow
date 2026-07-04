<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePdvSaleRequest extends FormRequest
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
            'client_id' => ['required', 'integer'],
            'professional_id' => ['required', 'integer'],
            'payment_method' => ['required', Rule::in(Payment::PAYMENT_METHODS)],
            'notes' => ['nullable', 'string'],
            'correction_reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $companyId = $this->user()?->company_id;
            if ($companyId === null || $validator->errors()->isNotEmpty()) {
                return;
            }

            $clientExists = Client::query()
                ->where('company_id', $companyId)
                ->active()
                ->whereKey($this->integer('client_id'))
                ->exists();

            if (! $clientExists) {
                $validator->errors()->add('client_id', 'Cliente invalido para sua empresa.');
            }

            $professionalExists = User::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->whereKey($this->integer('professional_id'))
                ->exists();

            if (! $professionalExists) {
                $validator->errors()->add('professional_id', 'Profissional invalido para sua empresa.');
            }
        });
    }
}
