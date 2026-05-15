<?php

namespace App\Providers;

use App\Models\Company;
use App\Services\BrandingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app()->setLocale('pt_BR');
        Carbon::setLocale('pt_BR');

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        View::composer(['layouts.app', 'layouts.pdv'], function ($view): void {
            $user = auth()->user();
            $company = ($user && $user->company_id)
                ? Company::query()->find($user->company_id)
                : null;
            $branding = app(BrandingService::class)->getCurrentCompanyBranding($company);
            $favicon = $branding['favicon_url'] ?: app(BrandingService::class)->defaultFaviconHref() ?: asset('favicon.ico');
            $view->with([
                'tenantBranding' => $branding,
                'tenantFaviconHref' => $favicon,
                'tenantThemeLight' => $branding['theme_light'],
            ]);
        });
    }
}
