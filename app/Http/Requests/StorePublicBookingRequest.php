<?php

namespace App\Http\Requests;

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

        return [
            'service_id' => [
                'required',
                Rule::exists('services', 'id')
                    ->where('company_id', $company->id)
                    ->where('active', true),
            ],
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where('company_id', $company->id),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_phone' => ['required', 'string', 'max:255'],
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
            $service = Service::query()
                ->where('company_id', $company->id)
                ->where('active', true)
                ->find($this->integer('service_id'));
            $user = User::query()
                ->where('company_id', $company->id)
                ->find($this->integer('user_id'));

            if (! $service || ! $user) {
                return;
            }

            $availableSlots = app(AvailabilityService::class)->availableSlots(
                $company,
                $user,
                $service,
                $this->string('date')->toString(),
            );

            if (! in_array($this->string('time')->toString(), $availableSlots, true)) {
                $validator->errors()->add('time', 'Este horário não está mais disponível.');
            }
        });
    }
}
