<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePublicBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('company') instanceof Company;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Company $company */
        $company = $this->route('company');
        $hasIdentifiedClient = $this->hasIdentifiedClient($company);

        return [
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => [
                'required',
                Rule::exists('services', 'id')
                    ->where('company_id', $company->id)
                    ->where('active', true),
            ],
            'user_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('company_id', $company->id)
                    ->where('active', true),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'payment_choice' => ['nullable', 'in:online,on_site'],
            'client_name' => [
                $hasIdentifiedClient ? 'nullable' : 'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($hasIdentifiedClient): void {
                    if ($hasIdentifiedClient) {
                        return;
                    }
                    $tokens = preg_split('/\s+/u', trim((string) $value)) ?: [];
                    $parts = [];
                    foreach ($tokens as $token) {
                        $part = trim((string) $token);
                        if ($part === '' || mb_strlen($part) < 2) {
                            continue;
                        }
                        $parts[] = $part;
                    }

                    if (count($parts) < 2) {
                        $fail('O nome completo com sobrenome é obrigatório.');
                    }
                },
            ],
            'client_phone' => [$hasIdentifiedClient ? 'nullable' : 'required', 'string', 'max:255'],
            'client_email' => [$hasIdentifiedClient ? 'nullable' : 'required', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
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

            /** @var Company $company */
            $company = $this->route('company');
            $services = Service::query()
                ->where('company_id', $company->id)
                ->where('active', true)
                ->whereIn('id', $this->input('service_ids', []))
                ->get();
            $user = User::query()
                ->where('company_id', $company->id)
                ->where('active', true)
                ->find($this->integer('user_id'));

            if ($services->isEmpty() || ! $user) {
                return;
            }

            $totalDurationMinutes = (int) $services->sum('duration_minutes');

            $availableSlots = app(AvailabilityService::class)->availableSlotsForDuration(
                $company,
                $user,
                $totalDurationMinutes,
                (string) $this->input('date'),
                true,
                null,
                $this->identifiedClientId($company),
            );

            if (! in_array((string) $this->input('time'), $availableSlots, true)) {
                $validator->errors()->add('time', 'Este horário não está mais disponível.');
            }
        });
    }

    private function hasIdentifiedClient(Company $company): bool
    {
        return $this->identifiedClientId($company) !== null;
    }

    private function identifiedClientId(Company $company): ?int
    {
        $clientId = session('public_booking_client_'.$company->id);

        if (! $clientId) {
            return null;
        }

        $exists = Client::query()
            ->where('company_id', $company->id)
            ->whereKey($clientId)
            ->exists();

        return $exists ? (int) $clientId : null;
    }
}
