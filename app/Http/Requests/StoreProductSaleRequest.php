<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
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
            'service_items' => ['nullable', 'array'],
            'service_items.*.service_id' => ['required', 'integer'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.seller_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $companyId = $this->user()?->company_id;

            if ($companyId === null || $validator->errors()->isNotEmpty()) {
                return;
            }

            $client = Client::query()
                ->where('company_id', $companyId)
                ->whereKey($this->integer('client_id'))
                ->first();

            if (! $client) {
                $validator->errors()->add('client_id', 'Selecione um cliente valido da sua empresa.');
            } elseif (! $client->isOperationallyActive()) {
                $validator->errors()->add('client_id', 'Este cliente esta desativado e nao pode receber novas vendas.');
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

            $serviceIds = collect($this->input('service_items', []))
                ->pluck('service_id')
                ->filter()
                ->unique()
                ->values();

            $productItems = collect($this->input('items', []));

            if ($serviceIds->isEmpty() && $productItems->isEmpty()) {
                $validator->errors()->add('items', 'Adicione pelo menos um servico ou produto para salvar a venda.');
            }

            if ($serviceIds->isNotEmpty()) {
                $validServiceIds = Service::query()
                    ->where('company_id', $companyId)
                    ->where('active', true)
                    ->whereIn('id', $serviceIds)
                    ->pluck('id');

                if ($validServiceIds->count() !== $serviceIds->count()) {
                    $validator->errors()->add('service_items', 'Um ou mais servicos nao pertencem a sua empresa ou estao inativos.');
                }
            }

            $productIds = collect($this->input('items', []))
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values();

            $validProductIds = Product::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->whereIn('id', $productIds)
                ->pluck('id');

            if ($validProductIds->count() !== $productIds->count()) {
                $validator->errors()->add('items', 'Um ou mais produtos nao pertencem a sua empresa.');
            }

            $products = Product::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->whereIn('id', $productIds)
                ->get(['id', 'stock_quantity', 'commission_type', 'commission_value'])
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

            $rawItems = collect($this->input('items', []));
            $sellerIds = $rawItems->pluck('seller_id')->filter()->unique()->values();
            if ($sellerIds->isNotEmpty()) {
                $validSellers = \App\Models\User::query()
                    ->where('company_id', $companyId)
                    ->where('active', true)
                    ->whereIn('id', $sellerIds)
                    ->pluck('id');
                if ($validSellers->count() !== $sellerIds->count()) {
                    $validator->errors()->add('items', 'Um ou mais vendedores sao invalidos para sua empresa.');
                }
            }

            foreach ($rawItems as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $product = $products->get($productId);
                if ($product && $product->hasCommission() && empty($item['seller_id'])) {
                    $validator->errors()->add('items', 'Selecione o vendedor responsavel para produtos com comissao.');
                    break;
                }
            }
        });
    }
}
