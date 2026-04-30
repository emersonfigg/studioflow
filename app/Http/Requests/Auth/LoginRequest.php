<?php

namespace App\Http\Requests\Auth;

use App\Models\Company;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if ($user && ! $user->isSuperAdmin() && ! $user->company_id) {
            $company = Company::create([
                'name' => 'Empresa de ' . $user->name,
                'active' => true,
                'onboarding_completed_at' => null,
            ]);

            $user->forceFill([
                'company_id' => $company->id,
                'role' => $user->role ?: 'admin',
                'active' => $user->active ?? true,
            ])->save();

            $user->setRelation('company', $company);
        }

        if (
            $user
            && ! $user->isSuperAdmin()
            && $user->company
            && $user->role !== 'admin'
            && $user->company->name === 'Empresa de ' . $user->name
            && $user->company->users()->count() === 1
        ) {
            $user->forceFill([
                'role' => 'admin',
                'active' => $user->active ?? true,
            ])->save();
        }

        if ($user && ! $user->isSuperAdmin() && ! $user->company?->active) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Sua empresa esta inativa. Entre em contato com o suporte do StudioFlow.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
