<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:30'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'favicon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,ico', 'max:512'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'public_headline' => ['nullable', 'string', 'max:255'],
            'public_subheadline' => ['nullable', 'string', 'max:500'],
            'welcome_message' => ['nullable', 'string', 'max:2000'],
            'custom_footer_text' => ['nullable', 'string', 'max:500'],
            'brand_enabled' => ['nullable', 'boolean'],
            'auto_print_receipt' => ['nullable', 'boolean'],
            'online_booking_payment_enabled' => ['nullable', 'boolean'],
            'booking_payment_mode' => ['nullable', 'in:none,deposit,full'],
            'booking_deposit_type' => ['nullable', 'in:fixed,percentage'],
            'booking_deposit_value' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'booking_payment_expiration_minutes' => ['nullable', 'integer', 'min:5', 'max:180'],
            'booking_auto_cancel_unpaid' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $company = $this->user()?->company;

        $this->merge([
            'auto_print_receipt' => $this->boolean('auto_print_receipt'),
            'brand_enabled' => $this->has('brand_enabled') ? $this->boolean('brand_enabled') : (bool) ($company?->brand_enabled ?? true),
            'online_booking_payment_enabled' => $this->boolean('online_booking_payment_enabled'),
            'booking_auto_cancel_unpaid' => $this->boolean('booking_auto_cancel_unpaid', true),
        ]);

        foreach ([
            'primary_color',
            'secondary_color',
            'accent_color',
            'public_headline',
            'public_subheadline',
            'welcome_message',
            'custom_footer_text',
            'booking_deposit_type',
        ] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if ($this->input('booking_payment_mode') !== 'deposit') {
            $this->merge([
                'booking_deposit_type' => null,
                'booking_deposit_value' => null,
            ]);
        }

        if (! $this->boolean('online_booking_payment_enabled')) {
            $this->merge([
                'booking_payment_mode' => 'none',
                'booking_deposit_type' => null,
                'booking_deposit_value' => null,
            ]);
        }
    }
}
