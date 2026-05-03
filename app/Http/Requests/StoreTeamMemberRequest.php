<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBrazilianCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

class StoreTeamMemberRequest extends FormRequest
{
    use NormalizesBrazilianCurrency;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true && $this->user()?->company_id !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'active' => ['nullable', 'boolean'],
            'schedule_type' => ['required', Rule::in(['fixed', 'dynamic'])],
            'fixed_weekdays' => ['nullable', 'array'],
            'fixed_weekdays.*' => ['integer', 'between:0,6'],
            'fixed_intervals' => ['nullable', 'array', 'max:2'],
            'fixed_intervals.*.start_time' => ['nullable', 'date_format:H:i'],
            'fixed_intervals.*.end_time' => ['nullable', 'date_format:H:i', 'after:fixed_intervals.*.start_time'],
            'commission_type' => ['nullable', Rule::in(['none', 'percent', 'fixed'])],
            'commission_value' => [
                new RequiredIf(in_array($this->input('commission_type'), ['percent', 'fixed'], true)),
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (in_array($this->input('commission_type'), ['percent', 'fixed'], true)) {
            $this->normalizeCurrencyFields(['commission_value']);
        }
    }
}
