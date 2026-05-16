<?php

namespace App\Http\Controllers;

use App\Models\BookingPayment;
use App\Models\Company;
use App\Services\BrandingService;
use Illuminate\Contracts\View\View;

class PublicBookingPaymentController extends Controller
{
    public function pending(Company $company, string $reference, BrandingService $brandingService): View
    {
        return $this->statusView($company, $reference, $brandingService, 'pending');
    }

    public function success(Company $company, string $reference, BrandingService $brandingService): View
    {
        return $this->statusView($company, $reference, $brandingService, 'success');
    }

    public function failure(Company $company, string $reference, BrandingService $brandingService): View
    {
        return $this->statusView($company, $reference, $brandingService, 'failure');
    }

    private function statusView(Company $company, string $reference, BrandingService $brandingService, string $screen): View
    {
        $bookingPayment = BookingPayment::query()
            ->with(['appointment.client', 'appointment.user', 'appointment.service', 'appointment.services'])
            ->where('company_id', $company->id)
            ->where('external_reference', $reference)
            ->firstOrFail();

        $appointment = $bookingPayment->appointment;
        abort_unless($appointment && (int) $appointment->company_id === (int) $company->id, 404);

        return view('public-bookings.payment-status', [
            'company' => $company,
            'appointment' => $appointment,
            'bookingPayment' => $bookingPayment,
            'screen' => $screen,
            'publicBranding' => $brandingService->getCurrentCompanyBranding($company),
            'publicFaviconHref' => $brandingService->faviconHrefFor($company),
        ]);
    }
}
