<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBrazilianCurrency;
use App\Support\ServiceImageLibrary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    use NormalizesBrazilianCurrency;

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
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'library_image' => ['nullable', 'string', Rule::in($this->availableLibraryImages())],
            'recommended_return_days' => ['nullable', 'integer', 'min:1', 'max:730'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCurrencyFields(['price']);

        $returnDays = $this->input('recommended_return_days');
        if ($returnDays === '' || $returnDays === '0' || $returnDays === 0) {
            $this->merge(['recommended_return_days' => null]);
        }
    }

    /**
     * Get the list of available built-in service images.
     *
     * @return list<string>
     */
    private function availableLibraryImages(): array
    {
        return ServiceImageLibrary::relativePaths();
    }
}
