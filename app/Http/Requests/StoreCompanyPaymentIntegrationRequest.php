<?php

namespace App\Http\Requests;

use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyPaymentIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(PaymentProvider::class)],
            'name' => ['nullable', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'access_token' => ['nullable', 'string', 'max:2000'],
            'refresh_token' => ['nullable', 'string', 'max:2000'],
            'public_key' => ['nullable', 'string', 'max:2000'],
            'webhook_secret' => ['nullable', 'string', 'max:2000'],
            'account_identifier' => ['nullable', 'string', 'max:255'],
            'environment' => ['required', Rule::enum(PaymentIntegrationEnvironment::class)],
            'active' => ['sometimes', 'boolean'],
            'default_for_memberships' => ['sometimes', 'boolean'],
        ];
    }
}
