<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true && $this->user()->company_id !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $clientId = $this->route('client')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'size:11', Rule::unique('clients', 'cpf_normalized')->where('company_id', $companyId)->ignore($clientId)],
            'birthday' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'last_visit_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D+/', '', (string) $this->input('cpf'));

        $this->merge([
            'cpf' => $digits === '' ? null : $digits,
        ]);
    }
}
