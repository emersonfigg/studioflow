<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($request->attributes->get('support_mode_active')) {
            return $next($request);
        }

        if ($user->company?->active) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        /** @var RedirectResponse $response */
        $response = redirect()
            ->route('login')
            ->withErrors([
                'email' => 'Sua empresa esta inativa. Entre em contato com o suporte do StudioFlow.',
            ]);

        return $response;
    }
}
