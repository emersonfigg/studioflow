<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePdvSaleRequest extends FormRequest
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
            'appointment_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'payment_method' => ['required', Rule::in(Payment::PAYMENT_METHODS)],
            'notes' => ['nullable', 'string'],
            'service_items' => ['nullable', 'array'],
            'service_items.*.service_id' => ['required', 'integer'],
            'items' => ['nullable', 'array'],
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

            if ($this->filled('appointment_id') && ! $validator->errors()->has('appointment_id') && $serviceIds->isEmpty()) {
                $validator->errors()->add(
                    'service_items',
                    'Fechamento com agendamento exige ao menos um servico (subtotal de servicos nao pode ser zero).'
                );
            }

            if ($serviceIds->isNotEmpty()) {
                $valid = Service::query()
                    ->where('company_id', $companyId)
                    ->where('active', true)
                    ->whereIn('id', $serviceIds)
                    ->count();
                if ($valid !== $serviceIds->count()) {
                    $validator->errors()->add('service_items', 'Um ou mais servicos sao invalidos.');
                }
            }

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
                }
            }

            if ($serviceIds->isEmpty() && $productIds->isEmpty() && ! $this->filled('appointment_id')) {
                $validator->errors()->add('items', 'Adicione ao menos um servico ou produto.');
            }
        });
    }

    /**
     * @return array{
     *     client_id:int,
     *     user_id:int,
     *     appointment_id:int|null,
     *     payment_method:string,
     *     notes:?string,
     *     service_items:array<int,array{service_id:int}>,
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
            'notes' => $this->filled('notes') ? (string) $this->input('notes') : null,
            'service_items' => collect($this->input('service_items', []))
                ->filter(fn (array $row): bool => ! empty($row['service_id']))
                ->map(fn (array $row): array => ['service_id' => (int) $row['service_id']])
                ->values()
                ->all(),
            'items' => collect($this->input('items', []))
                ->filter(fn (array $row): bool => ! empty($row['product_id']) && (int) ($row['quantity'] ?? 0) > 0)
                ->map(fn (array $row): array => [
                    'product_id' => (int) $row['product_id'],
                    'quantity' => max(1, (int) $row['quantity']),
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
}
