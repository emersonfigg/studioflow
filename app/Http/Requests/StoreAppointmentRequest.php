<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->company_id !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'client_id' => [
                'required',
                Rule::exists('clients', 'id')->where('company_id', $companyId),
            ],
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where('company_id', $companyId),
            ],
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('company_id', $companyId)],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'distinct', Rule::exists('services', 'id')->where('company_id', $companyId)->where('active', true)],
            'product_items' => ['nullable', 'array'],
            'product_items.*.product_id' => ['required_with:product_items', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)->where('active', true)],
            'product_items.*.quantity' => ['required_with:product_items', 'integer', 'min:1', 'max:999'],
            'start_time' => ['required', 'date'],
            'status' => ['required', Rule::in(array_values(array_diff(Appointment::STATUSES, ['no_show'])))],
            'source' => ['required', Rule::in(Appointment::SOURCES)],
            'notes' => ['nullable', 'string'],
            'force_blocked_client' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_ids.required' => 'Adicione pelo menos um serviço para continuar.',
            'service_ids.array' => 'Adicione pelo menos um serviço para continuar.',
            'service_ids.min' => 'Adicione pelo menos um serviço para continuar.',
            'service_ids.*.exists' => 'Um dos serviços selecionados não está disponível ou está inativo.',
            'service_ids.*.distinct' => 'Não é possível repetir o mesmo serviço duas vezes.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'service_ids' => 'serviços',
            'service_ids.*' => 'serviço',
            'client_id' => 'cliente',
            'user_id' => 'profissional',
            'start_time' => 'data e hora',
        ];
    }

    protected function prepareForValidation(): void
    {
        $serviceIds = collect((array) $this->input('service_ids', []))
            ->filter()
            ->values()
            ->all();

        if ($serviceIds === [] && $this->filled('service_id')) {
            $serviceIds = [$this->input('service_id')];
        }

        $productItems = collect((array) $this->input('product_items', []))
            ->filter(fn (mixed $item): bool => is_array($item) && ! empty($item['product_id']))
            ->map(fn (array $item): array => [
                'product_id' => $item['product_id'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
            ])
            ->values()
            ->all();

        $this->merge([
            'service_ids' => $serviceIds,
            'service_id' => $serviceIds[0] ?? $this->input('service_id'),
            'product_items' => $productItems,
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $services = Service::query()
                ->where('company_id', $this->user()->company_id)
                ->whereIn('id', (array) $this->input('service_ids', []))
                ->get();
            $professional = User::query()
                ->where('company_id', $this->user()->company_id)
                ->find($this->integer('user_id'));

            if ($services->isEmpty() || ! $professional) {
                return;
            }

            $this->validateProductStock($validator);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $startTime = CarbonImmutable::parse((string) $this->input('start_time'));
            $endTime = $startTime->addMinutes((int) $services->sum('duration_minutes'));
            $clientConflict = Appointment::findClientScheduleConflict(
                (int) $this->user()->company_id,
                $this->integer('client_id'),
                $startTime,
                $endTime,
            );

            if ($clientConflict) {
                $validator->errors()->add(
                    'start_time',
                    'Este cliente já possui um agendamento ativo nesse horário.'
                );

                return;
            }

            if (! $this->user()->isAdmin() && Appointment::clientHasActiveAppointmentSameCalendarDate(
                (int) $this->user()->company_id,
                $this->integer('client_id'),
                $startTime,
            )) {
                $validator->errors()->add(
                    'start_time',
                    'Este cliente já possui outro agendamento ativo neste dia. Escolha outra data ou ajuste o agendamento existente.',
                );

                return;
            }

            $availableSlots = app(AvailabilityService::class)->availableSlotsForDuration(
                $this->user()->company,
                $professional,
                (int) $services->sum('duration_minutes'),
                $startTime,
                false,
            );

            if (! in_array($startTime->format('H:i'), $availableSlots, true)) {
                $validator->errors()->add(
                    'start_time',
                    'Este horário não está disponível para a agenda real deste profissional.'
                );
            }
        });
    }

    private function validateProductStock(Validator $validator): void
    {
        $items = collect((array) $this->input('product_items', []));

        if ($items->isEmpty()) {
            return;
        }

        $products = Product::query()
            ->where('company_id', $this->user()->company_id)
            ->whereIn('id', $items->pluck('product_id')->filter()->all())
            ->get()
            ->keyBy('id');

        foreach ($items->groupBy('product_id') as $productId => $groupedItems) {
            $product = $products->get((int) $productId);
            $quantity = (float) $groupedItems->sum('quantity');

            if (! $product) {
                $validator->errors()->add(
                    'product_items',
                    'Estoque insuficiente para um dos produtos selecionados.'
                );

                continue;
            }

            if ($product->tracksStock() && round((float) $product->stock_quantity, 2) + 1e-6 < round($quantity, 2)) {
                $validator->errors()->add(
                    'product_items',
                    'Estoque insuficiente para um dos produtos selecionados.'
                );
            }
        }
    }
}
