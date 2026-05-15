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
        ];
    }

    protected function prepareForValidation(): void
    {
        $company = $this->user()?->company;

        $this->merge([
            'auto_print_receipt' => $this->boolean('auto_print_receipt'),
            'brand_enabled' => $this->has('brand_enabled') ? $this->boolean('brand_enabled') : (bool) ($company?->brand_enabled ?? true),
        ]);

        foreach (['primary_color', 'secondary_color', 'accent_color', 'public_headline', 'public_subheadline', 'welcome_message', 'custom_footer_text'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
