<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Factory as ViewFactory;
use Symfony\Component\HttpFoundation\Response;

class ApplySupportMode
{
    public function __construct(
        protected ViewFactory $view,
    ) {
    }

    /**
     * Apply super admin support mode to company routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportMode = $request->session()->get('support_mode');
        $originalUser = $request->user();

        if (! is_array($supportMode) || ! $originalUser?->isSuperAdmin()) {
            return $next($request);
        }

        if (($supportMode['original_user_id'] ?? null) !== $originalUser->id) {
            $request->session()->forget('support_mode');

            return $next($request);
        }

        $supportUser = User::query()
            ->with('company')
            ->find($supportMode['user_id'] ?? null);

        if (! $supportUser || $supportUser->company_id !== ($supportMode['company_id'] ?? null)) {
            $request->session()->forget('support_mode');

            return $next($request);
        }

        Auth::setUser($supportUser);
        $request->setUserResolver(fn () => $supportUser);
        $request->attributes->set('support_mode_active', true);
        $request->attributes->set('support_mode', [
            'original_user_id' => $originalUser->id,
            'original_user_name' => $originalUser->name,
            'company_id' => $supportUser->company_id,
            'company_name' => $supportUser->company?->name,
            'support_user_id' => $supportUser->id,
            'support_user_name' => $supportUser->name,
            'entered_at' => $supportMode['entered_at'] ?? now()->toIso8601String(),
        ]);

        $this->view->share('supportMode', $request->attributes->get('support_mode'));

        return $next($request);
    }
}
