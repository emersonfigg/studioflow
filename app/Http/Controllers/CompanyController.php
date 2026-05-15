<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyRequest;
use App\Services\BrandingService;
use App\Support\MediaStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Text fields that should never persist broken DOM object strings.
     *
     * @var array<int, string>
     */
    private const TEXT_FIELDS = [
        'name',
        'phone',
        'address',
        'cnpj',
        'instagram',
        'description',
        'public_headline',
        'public_subheadline',
        'welcome_message',
        'custom_footer_text',
    ];

    /**
     * Show the current company settings form.
     */
    public function edit(Request $request, BrandingService $brandingService): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $company = auth()->user()->company;
        abort_unless($company, 404);

        return view('company.edit', [
            'company' => $company,
            'formValues' => $this->companyFormValues($request, $company),
            'isOnboarding' => false,
            'brandingPreviewVars' => $brandingService->previewThemeStylePairs(
                $request->old('primary_color') ?: $company->primary_color,
                $request->old('secondary_color') ?: $company->secondary_color,
                $request->old('accent_color') ?: $company->accent_color,
                (bool) $request->old('brand_enabled', $company->brand_enabled ?? true),
            ),
        ]);
    }

    /**
     * Show the first company setup flow.
     */
    public function onboarding(Request $request, BrandingService $brandingService): View|RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $company = $request->user()->company;
        abort_unless($company, 404);

        if ($company->onboardingCompleted()) {
            return redirect()->route('company.edit');
        }

        return view('company.edit', [
            'company' => $company,
            'formValues' => $this->companyFormValues($request, $company),
            'isOnboarding' => true,
            'brandingPreviewVars' => $brandingService->previewThemeStylePairs(
                $request->old('primary_color') ?: $company->primary_color,
                $request->old('secondary_color') ?: $company->secondary_color,
                $request->old('accent_color') ?: $company->accent_color,
                (bool) $request->old('brand_enabled', $company->brand_enabled ?? true),
            ),
        ]);
    }

    /**
     * Pré-visualização do tema (mesmas variáveis que o HTML raiz) para o painel /company.
     */
    public function previewBrandingStyle(Request $request, BrandingService $brandingService): JsonResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'primary_color' => ['nullable', 'string', 'max:7'],
            'secondary_color' => ['nullable', 'string', 'max:7'],
            'accent_color' => ['nullable', 'string', 'max:7'],
            'brand_enabled' => ['sometimes'],
        ]);

        $enabled = filter_var($request->input('brand_enabled', true), FILTER_VALIDATE_BOOLEAN);

        $vars = $brandingService->previewThemeStylePairs(
            $validated['primary_color'] ?? null,
            $validated['secondary_color'] ?? null,
            $validated['accent_color'] ?? null,
            $enabled,
        );

        return response()->json(['vars' => $vars]);
    }

    /**
     * Update the current company settings.
     */
    public function update(UpdateCompanyRequest $request, BrandingService $brandingService): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company, 404);

        $data = $request->safe()->except(['logo', 'favicon', 'cover_image']);

        foreach (self::TEXT_FIELDS as $field) {
            $sanitized = $this->sanitizeTextualField($data[$field] ?? null, $field === 'name');

            if ($field === 'name') {
                $data[$field] = $sanitized !== '' ? $sanitized : (string) $company->name;
            } else {
                $data[$field] = $sanitized;
            }
        }

        foreach (['primary_color', 'secondary_color', 'accent_color'] as $colorField) {
            $data[$colorField] = $brandingService->sanitizeColors($data[$colorField] ?? null);
        }

        if ($request->hasFile('logo')) {
            if ($company->normalizedLogoPath()) {
                MediaStorage::delete($company->normalizedLogoPath());
            }

            $data['logo'] = MediaStorage::putFile('companies', $request->file('logo'));
        }

        if ($request->hasFile('favicon')) {
            if ($company->normalizedFaviconPath()) {
                MediaStorage::delete($company->normalizedFaviconPath());
            }

            $data['favicon_path'] = MediaStorage::putFile('companies/favicons', $request->file('favicon'));
        }

        if ($request->hasFile('cover_image')) {
            if ($company->normalizedCoverImagePath()) {
                MediaStorage::delete($company->normalizedCoverImagePath());
            }

            $data['cover_image_path'] = MediaStorage::putFile('companies/covers', $request->file('cover_image'));
        }

        $wasOnboardingIncomplete = ! $company->onboardingCompleted();

        if ($wasOnboardingIncomplete) {
            $data['onboarding_completed_at'] = now();
        }

        $company->update($data);

        $company->refresh();

        $request->user()->unsetRelation('company');

        return redirect()
            ->route($wasOnboardingIncomplete ? 'dashboard' : 'company.edit')
            ->with('status', $wasOnboardingIncomplete ? 'company-onboarded' : 'company-updated');
    }

    /**
     * Prepare safe values for the company form.
     *
     * @return array<string, mixed>
     */
    private function companyFormValues(Request $request, $company): array
    {
        return [
            'name' => $this->sanitizeTextualField($request->old('name', $company->name), true) ?: (string) $company->name,
            'phone' => $this->sanitizeTextualField($request->old('phone', $company->phone)),
            'address' => $this->sanitizeTextualField($request->old('address', $company->address)),
            'cnpj' => $this->sanitizeTextualField($request->old('cnpj', $company->cnpj)),
            'instagram' => $this->sanitizeTextualField($request->old('instagram', $company->instagram)),
            'description' => $this->sanitizeTextualField($request->old('description', $company->description)),
            'primary_color' => $request->old('primary_color', $company->primary_color),
            'secondary_color' => $request->old('secondary_color', $company->secondary_color),
            'accent_color' => $request->old('accent_color', $company->accent_color),
            'public_headline' => $this->sanitizeTextualField($request->old('public_headline', $company->public_headline)),
            'public_subheadline' => $this->sanitizeTextualField($request->old('public_subheadline', $company->public_subheadline)),
            'welcome_message' => $this->sanitizeTextualField($request->old('welcome_message', $company->welcome_message)),
            'custom_footer_text' => $this->sanitizeTextualField($request->old('custom_footer_text', $company->custom_footer_text)),
            'brand_enabled' => (bool) $request->old('brand_enabled', $company->brand_enabled ?? true),
            'auto_print_receipt' => (bool) $request->old('auto_print_receipt', $company->auto_print_receipt),
        ];
    }

    /**
     * Remove invalid DOM object string values before rendering or persisting.
     */
    private function sanitizeTextualField(mixed $value, bool $required = false): ?string
    {
        if ($value === null) {
            return $required ? '' : null;
        }

        if (is_object($value) || is_array($value)) {
            return $required ? '' : null;
        }

        $text = trim((string) $value);

        if ($text === '' || preg_match('/^\[object\s+HTML[\w-]*Element\]$/i', $text) === 1 || preg_match('/^\[object\s+[\w-]+\]$/', $text) === 1) {
            return $required ? '' : null;
        }

        return $text;
    }
}
