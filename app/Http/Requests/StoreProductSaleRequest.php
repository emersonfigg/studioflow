<?php

namespace App\Http\Requests;

use App\Models\Payment;
use App\Models\Product;
use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->company_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer'],
            'appointment_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'payment_method' => ['required', Rule::in(Payment::PAYMENT_METHODS)],
            'sold_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $companyId = $this->user()?->company_id;

            if ($companyId === null || $validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->user()->company->clients()->whereKey($this->integer('client_id'))->exists()) {
                $validator->errors()->add('client_id', 'Selecione um cliente valido da sua empresa.');
            }

            if ($this->filled('appointment_id')) {
                $appointment = Appointment::query()
                    ->where('company_id', $companyId)
                    ->find($this->integer('appointment_id'));

                if (! $appointment) {
                    $validator->errors()->add('appointment_id', 'Selecione um atendimento valido da sua empresa.');
                } elseif ((int) $appointment->client_id !== $this->integer('client_id')) {
                    $validator->errors()->add('appointment_id', 'O atendimento escolhido precisa pertencer ao mesmo cliente da venda.');
                }
            }

            if ($this->filled('user_id') && ! $this->user()->company->users()->whereKey($this->integer('user_id'))->exists()) {
                $validator->errors()->add('user_id', 'Selecione um profissional valido da sua empresa.');
            }

            $productIds = collect($this->input('items', []))
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values();

            $validProductIds = Product::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $productIds)
                ->pluck('id');

            if ($validProductIds->count() !== $productIds->count()) {
                $validator->errors()->add('items', 'Um ou mais produtos nao pertencem a sua empresa.');
            }
        });
    }
}
