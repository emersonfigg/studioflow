<?php

namespace App\Http\Requests;

use App\Models\Appointment;
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
            'service_id' => [
                'required',
                Rule::exists('services', 'id')->where('company_id', $companyId),
            ],
            'start_time' => ['required', 'date'],
            'status' => ['required', Rule::in(Appointment::STATUSES)],
            'source' => ['required', Rule::in(Appointment::SOURCES)],
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

            $service = Service::query()
                ->where('company_id', $this->user()->company_id)
                ->find($this->integer('service_id'));
            $professional = User::query()
                ->where('company_id', $this->user()->company_id)
                ->find($this->integer('user_id'));

            if (! $service || ! $professional) {
                return;
            }

            $startTime = CarbonImmutable::parse((string) $this->input('start_time'));
            $availableSlots = app(AvailabilityService::class)->availableSlots(
                $this->user()->company,
                $professional,
                $service,
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
}
