<?php

namespace App\View\Components;

use App\Models\Company;
use App\Services\BrandingService;
use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    public function __construct(public ?Company $brandCompany = null) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $branding = app(BrandingService::class)->getCurrentCompanyBranding($this->brandCompany);
        $favicon = $branding['favicon_url'] ?: app(BrandingService::class)->defaultFaviconHref() ?: asset('favicon.ico');

        return view('layouts.guest', [
            'tenantBranding' => $branding,
            'tenantFaviconHref' => $favicon,
            'tenantThemeLight' => $branding['theme_light'],
        ]);
    }
}
