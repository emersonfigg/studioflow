<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBrazilianCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
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
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'library_image' => ['nullable', 'string', Rule::in($this->availableLibraryImages())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCurrencyFields(['price']);
    }

    /**
     * Get the list of available built-in service images.
     *
     * @return list<string>
     */
    private function availableLibraryImages(): array
    {
        return collect(Storage::disk('public')->files('service-library/services'))
            ->filter(function (string $path): bool {
                return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'svg'], true);
            })
            ->values()
            ->all();
    }
}
