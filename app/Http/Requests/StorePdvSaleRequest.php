<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBrazilianCurrency;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePdvSaleRequest extends FormRequest
{
    use NormalizesBrazilianCurrency;

    public function authorize(): bool
    {
        return $this->user()?->company_id !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCurrencyFields(['discount', 'discount_value']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'appointment_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'payment_method' => ['required', Rule::in(Payment::PAYMENT_METHODS)],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'service_items' => ['nullable', 'array'],
            'service_items.*.service_id' => ['required', 'integer'],
            'service_items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'service_items.*.price_adjustment_reason' => ['nullable', 'string', 'max:255'],
            'membership_items' => ['nullable', 'array'],
            'membership_items.*.membership_plan_id' => ['required', 'integer'],
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

            if ($this->filled('appointment_id')) {
                $appointment = Appointment::query()
                    ->where('company_id', $companyId)
                    ->whereKey($this->integer('appointment_id'))
                    ->first();
                if (! $appointment) {
                    $validator->errors()->add('appointment_id', 'Agendamento invalido para sua empresa.');
                } elseif (in_array($appointment->status, ['completed', 'cancelled'], true)) {
                    $validator->errors()->add('appointment_id', 'Este agendamento nao pode ser finalizado pelo PDV.');
                } else {
                    $hasPaidOrder = ServiceOrder::query()
                        ->where('company_id', $companyId)
                        ->where('appointment_id', $this->integer('appointment_id'))
                        ->where('status', ServiceOrder::STATUS_PAID)
                        ->exists();
                    if ($hasPaidOrder) {
                        $validator->errors()->add(
                            'appointment_id',
                            'Este agendamento ja foi finalizado. Nao e possivel registrar outra venda para o mesmo atendimento.'
                        );
                    }
                }
            }

            if ($this->filled('client_id')) {
                $exists = Client::query()
                    ->where('company_id', $companyId)
                    ->active()
                    ->whereKey($this->integer('client_id'))
                    ->exists();
                if (! $exists) {
                    $validator->errors()->add('client_id', 'Cliente invalido para sua empresa.');
                }
            }

            if ($this->filled('user_id')) {
                $exists = $this->user()->company->users()
                    ->where('active', true)
                    ->whereKey($this->integer('user_id'))
                    ->exists();
                if (! $exists) {
                    $validator->errors()->add('user_id', 'Profissional invalido para sua empresa.');
                }
            }

            $serviceIds = collect($this->input('service_items', []))
                ->pluck('service_id')
                ->filter()
                ->unique()
                ->values();

            if ($serviceIds->isNotEmpty()) {
                $servicesForValidation = Service::query()
                    ->where('company_id', $companyId)
                    ->availableForPos()
                    ->whereIn('id', $serviceIds)
                    ->get()
                    ->keyBy('id');
                if ($servicesForValidation->count() !== $serviceIds->count()) {
                    $validator->errors()->add('service_items', 'Um ou mais servicos sao invalidos.');
                } else {
                    foreach (collect($this->input('service_items', [])) as $row) {
                        $service = $servicesForValidation->get((int) ($row['service_id'] ?? 0));
                        if (! $service || ! array_key_exists('unit_price', $row) || $row['unit_price'] === null || $row['unit_price'] === '') {
                            continue;
                        }

                        if (abs(round((float) $row['unit_price'], 2) - round((float) $service->price, 2)) > 0.009 && ! $service->allowsPdvPriceEdit()) {
                            $validator->errors()->add('service_items', 'Um dos servicos nao permite alteracao de preco.');
                            break;
                        }
                    }
                }
            }

            $membershipPlanIds = collect($this->input('membership_items', []))
                ->pluck('membership_plan_id')
                ->filter()
                ->unique()
                ->values();

            if ($membershipPlanIds->isNotEmpty()) {
                $validPlans = MembershipPlan::query()
                    ->where('company_id', $companyId)
                    ->where('active', true)
                    ->whereIn('id', $membershipPlanIds)
                    ->count();
                if ($validPlans !== $membershipPlanIds->count()) {
                    $validator->errors()->add('membership_items', 'Um ou mais planos de assinatura sao invalidos.');
                }

                if (! $this->filled('client_id') && ! $this->filled('appointment_id')) {
                    $validator->errors()->add('client_id', 'Selecione um cliente para vender assinatura.');
                }
            }

            $rawItems = collect($this->input('items', []));

            $productIds = collect($this->input('items', []))
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values();
            if ($productIds->isNotEmpty()) {
                $valid = Product::query()
                    ->where('company_id', $companyId)
                    ->where('active', true)
                    ->whereIn('id', $productIds)
                    ->count();
                if ($valid !== $productIds->count()) {
                    $validator->errors()->add('items', 'Um ou mais produtos sao invalidos.');
                } else {
                    $productsForStock = Product::query()
                        ->where('company_id', $companyId)
                        ->whereIn('id', $productIds)
                        ->get()
                        ->keyBy('id');

                    $grouped = $rawItems->groupBy('product_id');

                    foreach ($grouped as $productId => $lines) {
                        /** @var Product|null $product */
                        $product = $productsForStock->get((int) $productId);

                        if (! $product || ! $product->tracksStock()) {
                            continue;
                        }

                        $needed = $lines->sum(fn (array $row): int => max(1, (int) ($row['quantity'] ?? 1)));

                        if (round((float) $product->stock_quantity, 2) + 1e-6 < $needed) {
                            $validator->errors()->add('items', 'Estoque insuficiente para um ou mais produtos selecionados.');
                            break;
                        }
                    }
                }
            }

            $sellerIds = $rawItems->pluck('seller_id')->filter()->unique()->values();
            if ($sellerIds->isNotEmpty()) {
                $validSellerIds = User::query()
                    ->where('company_id', $companyId)
                    ->where('active', true)
                    ->whereIn('id', $sellerIds)
                    ->pluck('id');

                if ($validSellerIds->count() !== $sellerIds->count()) {
                    $validator->errors()->add('items', 'Um ou mais vendedores sao invalidos para sua empresa.');
                }
            }

            if ($productIds->isNotEmpty()) {
                $commissionProducts = Product::query()
                    ->where('company_id', $companyId)
                    ->whereIn('id', $productIds)
                    ->whereNotNull('commission_type')
                    ->where('commission_value', '>', 0)
                    ->pluck('id')
                    ->all();

                if ($commissionProducts !== []) {
                    foreach ($rawItems as $item) {
                        $productId = (int) ($item['product_id'] ?? 0);
                        if ($productId === 0 || ! in_array($productId, $commissionProducts, true)) {
                            continue;
                        }

                        if (empty($item['seller_id'])) {
                            $validator->errors()->add('items', 'Selecione o vendedor responsavel para produtos com comissao.');
                            break;
                        }
                    }
                }
            }

            if ($serviceIds->isEmpty() && $productIds->isEmpty() && $membershipPlanIds->isEmpty()) {
                $validator->errors()->add('items', 'Adicione ao menos um servico, produto ou assinatura.');
            }

            $subtotal = $this->resolveSubtotal($companyId, $serviceIds, $productIds);
            $discountType = (string) $this->input('discount_type', 'fixed');
            $discountValue = round((float) ($this->input('discount_value') ?? $this->input('discount') ?? 0), 2);

            if ($discountType === 'percent' && $discountValue > 100) {
                $validator->errors()->add('discount_value', 'Percentual de desconto nao pode ser maior que 100%.');

                return;
            }

            $discount = $this->resolveDiscountAmount($subtotal);

            if ($discount > $subtotal) {
                $validator->errors()->add('discount_value', 'O desconto nao pode ser maior que a soma dos subtotais.');
            }
        });
    }

    /**
     * @return array{
     *     client_id:int,
     *     user_id:int,
     *     appointment_id:int|null,
     *     payment_method:string,
     *     discount:float,
     *     notes:?string,
     *     service_items:array<int,array{service_id:int, unit_price?:float|null, price_adjustment_reason?:string|null}>,
     *     membership_items:array<int,array{membership_plan_id:int}>,
     *     items:array<int,array{product_id:int,quantity:int}>
     * }
     */
    public function payload(): array
    {
        $companyId = $this->user()->company_id;
        $walkInClientId = $this->resolveWalkInClientId($companyId);

        $appointmentId = $this->filled('appointment_id') ? $this->integer('appointment_id') : null;

        $clientId = $this->integer('client_id') ?: $walkInClientId;
        $userId = $this->filled('user_id') ? $this->integer('user_id') : null;

        if ($appointmentId !== null) {
            $appointment = Appointment::query()
                ->where('company_id', $companyId)
                ->whereKey($appointmentId)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->first();
            if ($appointment) {
                $clientId = (int) $appointment->client_id;
                if ($userId === null) {
                    $userId = (int) $appointment->user_id;
                }
            }
        }

        if ($userId === null) {
            $userId = (int) $this->user()->id;
        }

        return [
            'client_id' => $clientId,
            'user_id' => $userId,
            'appointment_id' => $appointmentId,
            'payment_method' => (string) $this->input('payment_method'),
            'discount' => round($this->resolveDiscountAmount($this->resolveSubtotalFromPayload($companyId)), 2),
            'notes' => $this->filled('notes') ? (string) $this->input('notes') : null,
            'service_items' => collect($this->input('service_items', []))
                ->filter(fn (array $row): bool => ! empty($row['service_id']))
                ->map(fn (array $row): array => [
                    'service_id' => (int) $row['service_id'],
                    'unit_price' => array_key_exists('unit_price', $row) && $row['unit_price'] !== '' ? round((float) $row['unit_price'], 2) : null,
                    'price_adjustment_reason' => filled($row['price_adjustment_reason'] ?? null) ? (string) $row['price_adjustment_reason'] : null,
                ])
                ->values()
                ->all(),
            'membership_items' => collect($this->input('membership_items', []))
                ->filter(fn (array $row): bool => ! empty($row['membership_plan_id']))
                ->map(fn (array $row): array => ['membership_plan_id' => (int) $row['membership_plan_id']])
                ->values()
                ->all(),
            'items' => collect($this->input('items', []))
                ->filter(fn (array $row): bool => ! empty($row['product_id']) && (int) ($row['quantity'] ?? 0) > 0)
                ->map(fn (array $row): array => [
                    'product_id' => (int) $row['product_id'],
                    'quantity' => max(1, (int) $row['quantity']),
                    'seller_id' => ! empty($row['seller_id']) ? (int) $row['seller_id'] : null,
                ])
                ->values()
                ->all(),
        ];
    }

    private function resolveWalkInClientId(int $companyId): int
    {
        $client = Client::query()
            ->firstOrCreate(
                ['company_id' => $companyId, 'phone' => '0000000000'],
                ['name' => 'Cliente Balcao', 'email' => null]
            );

        return $client->id;
    }

    private function resolveSubtotalFromPayload(int $companyId): float
    {
        $serviceIds = collect($this->input('service_items', []))
            ->pluck('service_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        $productIds = collect($this->input('items', []))
            ->pluck('product_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        return $this->resolveSubtotal($companyId, $serviceIds, $productIds);
    }

    /**
     * @param  Collection<int, int>  $serviceIds
     * @param  Collection<int, int>  $productIds
     */
    private function resolveSubtotal(int $companyId, $serviceIds, $productIds): float
    {
        $servicePrices = Service::query()
            ->where('company_id', $companyId)
            ->availableForPos()
            ->whereIn('id', $serviceIds->all())
            ->get()
            ->keyBy('id');

        $servicesSubtotal = collect($this->input('service_items', []))
            ->sum(function (array $item) use ($servicePrices): float {
                $serviceId = (int) ($item['service_id'] ?? 0);
                $service = $servicePrices->get($serviceId);
                if (! $service) {
                    return 0.0;
                }

                return array_key_exists('unit_price', $item) && $item['unit_price'] !== '' && $service->allowsPdvPriceEdit()
                    ? (float) $item['unit_price']
                    : (float) $service->price;
            });

        $products = Product::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->whereIn('id', $productIds->all())
            ->pluck('price', 'id');

        $productsSubtotal = collect($this->input('items', []))
            ->sum(function (array $item) use ($products): float {
                $productId = (int) ($item['product_id'] ?? 0);
                $quantity = max(1, (int) ($item['quantity'] ?? 1));

                return (float) ($products[$productId] ?? 0) * $quantity;
            });

        $membershipSubtotal = collect($this->input('membership_items', []))
            ->pluck('membership_plan_id')
            ->filter()
            ->sum(function ($planId) use ($companyId): float {
                return (float) MembershipPlan::query()
                    ->where('company_id', $companyId)
                    ->where('active', true)
                    ->whereKey((int) $planId)
                    ->value('price');
            });

        return round($servicesSubtotal + $productsSubtotal + $membershipSubtotal, 2);
    }

    private function resolveDiscountAmount(float $subtotal): float
    {
        $type = (string) $this->input('discount_type', 'fixed');
        $legacyDiscount = round((float) ($this->input('discount') ?? 0), 2);
        $value = round((float) ($this->input('discount_value') ?? $legacyDiscount), 2);

        if ($value <= 0 || $subtotal <= 0) {
            return 0.0;
        }

        if ($type === 'percent') {
            if ($value > 100) {
                return $subtotal + 0.01;
            }

            return round($subtotal * ($value / 100), 2);
        }

        return $value;
    }
}
