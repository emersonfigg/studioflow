<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    /**
     * Show the current company settings form.
     */
    public function edit(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return view('company.edit', [
            'company' => auth()->user()->company,
            'isOnboarding' => false,
        ]);
    }

    /**
     * Show the first company setup flow.
     */
    public function onboarding(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $company = $request->user()->company;
        abort_unless($company, 404);

        if ($company->onboardingCompleted()) {
            return redirect()->route('company.edit');
        }

        return view('company.edit', [
            'company' => $company,
            'isOnboarding' => true,
        ]);
    }

    /**
     * Update the current company settings.
     */
    public function update(UpdateCompanyRequest $request): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company, 404);

        $data = $request->validated();

        if ($request->hasFile('logo')) {
            if ($company->normalizedLogoPath()) {
                Storage::disk('public')->delete($company->normalizedLogoPath());
            }

            $data['logo'] = $request->file('logo')->store('companies', 'public');
        }

        $wasOnboardingIncomplete = ! $company->onboardingCompleted();

        if ($wasOnboardingIncomplete) {
            $data['onboarding_completed_at'] = now();
        }

        $company->update($data);

        return redirect()
            ->route($wasOnboardingIncomplete ? 'dashboard' : 'company.edit')
            ->with('status', $wasOnboardingIncomplete ? 'company-onboarded' : 'company-updated');
    }
}
