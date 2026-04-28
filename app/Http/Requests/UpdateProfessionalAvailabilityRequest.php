<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProfessionalAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'working_hours' => ['nullable', 'array'],
            'working_hours.*.weekday' => ['required', 'integer', 'between:0,6'],
            'working_hours.*.start_time' => ['required', 'date_format:H:i'],
            'working_hours.*.end_time' => ['required', 'date_format:H:i'],
            'working_hours.*.active' => ['nullable', 'boolean'],
            'overrides' => ['nullable', 'array'],
            'overrides.*.date' => ['required', 'date_format:Y-m-d'],
            'overrides.*.is_day_off' => ['nullable', 'boolean'],
            'overrides.*.start_time' => ['nullable', 'date_format:H:i'],
            'overrides.*.end_time' => ['nullable', 'date_format:H:i'],
            'overrides.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('working_hours', []) as $index => $workingHour) {
                $startTime = $workingHour['start_time'] ?? null;
                $endTime = $workingHour['end_time'] ?? null;

                if ($startTime && $endTime && $endTime <= $startTime) {
                    $validator->errors()->add("working_hours.$index.end_time", 'O horario final deve ser maior que o horario inicial.');
                }
            }

            $dates = [];

            foreach ($this->input('overrides', []) as $index => $override) {
                $date = $override['date'] ?? null;
                $isDayOff = filter_var($override['is_day_off'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $startTime = $override['start_time'] ?? null;
                $endTime = $override['end_time'] ?? null;

                if ($date) {
                    if (in_array($date, $dates, true)) {
                        $validator->errors()->add("overrides.$index.date", 'Nao repita a mesma data nas excecoes.');
                    }

                    $dates[] = $date;
                }

                if ($isDayOff) {
                    continue;
                }

                if (($startTime && ! $endTime) || (! $startTime && $endTime)) {
                    $validator->errors()->add("overrides.$index.end_time", 'Informe inicio e fim para o horario especial.');
                    continue;
                }

                if ($startTime && $endTime && $endTime <= $startTime) {
                    $validator->errors()->add("overrides.$index.end_time", 'O horario final deve ser maior que o horario inicial.');
                }
            }
        });
    }
}
