<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicBookingRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicBookingController extends Controller
{
    /**
     * Show the public booking form for a company.
     */
    public function create(Request $request, Company $company, AvailabilityService $availabilityService): View
    {
        $services = Service::query()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $users = User::query()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get();

        $selectedService = $services->firstWhere('id', $request->integer('service_id'));
        $selectedUser = $users->firstWhere('id', $request->integer('user_id'));
        $selectedDate = $request->filled('date')
            ? CarbonImmutable::parse($request->string('date'))->toDateString()
            : CarbonImmutable::today()->toDateString();

        $availableSlots = [];

        if ($selectedService && $selectedUser) {
            $availableSlots = $availabilityService->availableSlots(
                $company,
                $selectedUser,
                $selectedService,
                $selectedDate,
            );
        }

        return view('public-bookings.create', [
            'company' => $company,
            'services' => $services,
            'users' => $users,
            'selectedServiceId' => $selectedService?->id,
            'selectedUserId' => $selectedUser?->id,
            'selectedDate' => $selectedDate,
            'availableSlots' => $availableSlots,
        ]);
    }

    /**
     * Store a new public booking.
     */
    public function store(StorePublicBookingRequest $request, Company $company): RedirectResponse
    {
        $data = $request->validated();
        $service = Service::query()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->findOrFail($data['service_id']);
        $user = User::query()
            ->where('company_id', $company->id)
            ->findOrFail($data['user_id']);

        $client = Client::query()->firstOrNew([
            'company_id' => $company->id,
            'phone' => $data['client_phone'],
        ]);

        $client->fill([
            'name' => $data['client_name'],
        ]);
        $client->save();

        $startTime = Carbon::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['time']}");

        Appointment::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'user_id' => $user->id,
            'service_id' => $service->id,
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addMinutes($service->duration_minutes),
            'status' => 'scheduled',
            'source' => 'public_booking',
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('public-bookings.create', [
                'company' => $company,
                'service_id' => $service->id,
                'user_id' => $user->id,
                'date' => $data['date'],
            ])
            ->with('status', 'public-booking-created');
    }
}
