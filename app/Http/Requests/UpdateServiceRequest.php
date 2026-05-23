<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBrazilianCurrency;
use App\Support\ServiceImageLibrary;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'price_mode' => ['nullable', Rule::in(Service::PRICE_MODES)],
            'allow_pdv_price_edit' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'library_image' => ['nullable', 'string', Rule::in($this->availableLibraryImages())],
            'recommended_return_days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'consumptions' => ['nullable', 'array'],
            'consumptions.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('company_id', (int) $this->user()->company_id)],
            'consumptions.*.quantity' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'consumptions.*.unit' => ['nullable', 'string', 'max:32'],
            'consumptions.*.active' => ['nullable', 'in:0,1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rows = collect((array) $this->input('consumptions', []))
                ->filter(fn (mixed $row): bool => is_array($row) && ! empty($row['product_id']));

            $ids = $rows->pluck('product_id')->map(fn ($id): int => (int) $id);

            if ($ids->duplicates()->isNotEmpty()) {
                $validator->errors()->add('consumptions', 'Não repita o mesmo produto na lista de consumo.');
            }

            foreach ($rows as $row) {
                if (empty($row['quantity']) || (float) $row['quantity'] <= 0) {
                    $validator->errors()->add('consumptions', 'Cada produto vinculado precisa de quantidade maior que zero.');

                    return;
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCurrencyFields(['price']);
        $this->merge([
            'price_mode' => $this->input('price_mode') ?: Service::PRICE_MODE_FIXED,
            'allow_pdv_price_edit' => $this->boolean('allow_pdv_price_edit') || $this->input('price_mode') === Service::PRICE_MODE_FROM,
        ]);

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
