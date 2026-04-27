<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
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

            if (! $service) {
                return;
            }

            $appointment = $this->route('appointment');
            $startTime = Carbon::parse($this->input('start_time'));
            $endTime = $startTime->copy()->addMinutes($service->duration_minutes);

            $hasConflict = Appointment::query()
                ->where('company_id', $this->user()->company_id)
                ->where('user_id', $this->integer('user_id'))
                ->where('status', '!=', 'cancelled')
                ->whereKeyNot($appointment->id)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->exists();

            if ($hasConflict) {
                $validator->errors()->add(
                    'start_time',
                    'Este profissional já possui um agendamento nesse horário.'
                );
            }
        });
    }
}
