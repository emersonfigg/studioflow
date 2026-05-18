<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupportModeController extends Controller
{
    /**
     * Enter support mode for the given company.
     */
    public function start(Request $request, Company $company): RedirectResponse
    {
        abort_if($company->isInternal(), 403);

        $supportUser = $company->users()
            ->orderByRaw("case when role = 'admin' then 0 else 1 end")
            ->orderByDesc('active')
            ->orderBy('name')
            ->first();

        if (! $supportUser) {
            return back()->withErrors([
                'support' => 'Essa empresa ainda nao possui usuarios para acessar em modo suporte.',
            ]);
        }

        $request->session()->put('support_mode', [
            'original_user_id' => $request->user()->id,
            'company_id' => $company->id,
            'user_id' => $supportUser->id,
            'entered_at' => now()->toIso8601String(),
        ]);

        return redirect()
            ->route('dashboard')
            ->with('status', 'support-mode-started');
    }

    /**
     * Leave support mode and return to the super admin panel.
     */
    public function stop(Request $request): RedirectResponse
    {
        $supportMode = $request->session()->get('support_mode');
        $request->session()->forget('support_mode');

        $companyId = $supportMode['company_id'] ?? null;

        if ($companyId && Company::query()->whereKey($companyId)->exists()) {
            return redirect()
                ->route('super-admin.companies.show', $companyId)
                ->with('status', 'support-mode-stopped');
        }

        return redirect()
            ->route('super-admin.companies.index')
            ->with('status', 'support-mode-stopped');
    }
}
