<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBrazilianCurrency;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
{
    use NormalizesBrazilianCurrency;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->route('appointment') instanceof Appointment;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'gross_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(Payment::PAYMENT_METHODS)],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required_with:items', 'integer'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCurrencyFields(['gross_amount']);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Appointment|null $appointment */
            $appointment = $this->route('appointment');

            if (! $appointment || $validator->errors()->isNotEmpty()) {
                return;
            }

            if ($appointment->status === 'cancelled') {
                $validator->errors()->add('payment_method', 'Nao e possivel registrar pagamento para atendimento cancelado.');
            }

            if ($appointment->payment()->exists()) {
                $validator->errors()->add('payment_method', 'Este atendimento ja possui pagamento registrado.');
            }

            $productIds = collect($this->input('items', []))
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values();

            if ($productIds->isEmpty()) {
                return;
            }

            $validProductIds = Product::query()
                ->where('company_id', $appointment->company_id)
                ->where('active', true)
                ->whereIn('id', $productIds)
                ->pluck('id');

            if ($validProductIds->count() !== $productIds->count()) {
                $validator->errors()->add('items', 'Um ou mais produtos nao pertencem a sua empresa ou nao estao ativos.');
            }

            $products = Product::query()
                ->where('company_id', $appointment->company_id)
                ->whereIn('id', $productIds)
                ->get(['id', 'stock_quantity'])
                ->keyBy('id');

            collect($this->input('items', []))
                ->groupBy('product_id')
                ->each(function ($items, $productId) use ($products, $validator): void {
                    $product = $products->get((int) $productId);
                    $quantity = collect($items)->sum(fn (array $item): int => (int) ($item['quantity'] ?? 0));

                    if ($product && $product->stock_quantity < $quantity) {
                        $validator->errors()->add('items', 'Estoque insuficiente para um ou mais produtos selecionados.');
                    }
                });
        });
    }
}
