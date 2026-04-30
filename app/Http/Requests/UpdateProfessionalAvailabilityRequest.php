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
            'date' => ['required', 'date_format:Y-m-d'],
            'works_this_day' => ['required', 'boolean'],
            'intervals' => ['nullable', 'array', 'max:2'],
            'intervals.*' => ['array'],
            'intervals.*.start_time' => ['nullable', 'date_format:H:i'],
            'intervals.*.end_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $worksThisDay = filter_var($this->input('works_this_day', false), FILTER_VALIDATE_BOOLEAN);
            $intervals = collect($this->input('intervals', []))->values();

            if (! $worksThisDay) {
                return;
            }

            $firstStart = $intervals->get(0)['start_time'] ?? null;
            $firstEnd = $intervals->get(0)['end_time'] ?? null;
            $secondStart = $intervals->get(1)['start_time'] ?? null;
            $secondEnd = $intervals->get(1)['end_time'] ?? null;

            if (! $firstStart || ! $firstEnd) {
                $validator->errors()->add('intervals.0.start_time', 'Informe o primeiro turno para um dia de trabalho.');
            }

            foreach ($intervals as $index => $interval) {
                $startTime = $interval['start_time'] ?? null;
                $endTime = $interval['end_time'] ?? null;

                if (! $startTime && ! $endTime) {
                    continue;
                }

                if (! $startTime || ! $endTime) {
                    $validator->errors()->add("intervals.$index.end_time", 'Informe inicio e fim para cada turno.');
                    continue;
                }

                if ($endTime <= $startTime) {
                    $validator->errors()->add("intervals.$index.end_time", 'O horario final deve ser maior que o horario inicial.');
                }
            }

            if (($secondStart && ! $secondEnd) || (! $secondStart && $secondEnd)) {
                $validator->errors()->add('intervals.1.end_time', 'Complete o segundo turno ou deixe os dois campos vazios.');
            }

            if ($firstStart && $firstEnd && $secondStart && $secondEnd) {
                if ($secondStart < $firstEnd) {
                    $validator->errors()->add('intervals.1.start_time', 'O segundo turno precisa comecar depois do fim do primeiro.');
                }

                if ($secondEnd <= $secondStart) {
                    $validator->errors()->add('intervals.1.end_time', 'O horario final deve ser maior que o horario inicial.');
                }
            }
        });
    }
}
