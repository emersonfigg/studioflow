<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\ServiceOrderService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    /**
     * Display a listing of appointments for the authenticated user's company.
     */
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;
        $selectedDate = $request->filled('date')
            ? CarbonImmutable::parse($request->string('date'))
            : CarbonImmutable::today();
        $selectedUserId = $request->integer('user_id') ?: null;

        $appointmentsQuery = Appointment::query()
            ->with(['client', 'service', 'services', 'user', 'payment'])
            ->where('company_id', $companyId)
            ->whereBetween('start_time', [
                $selectedDate->startOfDay(),
                $selectedDate->endOfDay(),
            ]);

        if ($selectedUserId !== null) {
            $appointmentsQuery->where('user_id', $selectedUserId);
        }

        $appointments = $appointmentsQuery
            ->orderBy('start_time')
            ->get();
        $appointmentsByTime = $appointments
            ->groupBy(fn (Appointment $appointment): string => $appointment->start_time->format('H:i'));

        $users = User::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return view('appointments.index', [
            'appointments' => $appointments,
            'appointmentsByTime' => $appointmentsByTime,
            'users' => $users,
            'selectedDate' => $selectedDate,
            'selectedUserId' => $selectedUserId,
        ]);
    }

    /**
     * Show the form for creating a new appointment.
     */
    public function create(Request $request): View
    {
        abort_unless($request->user()->company_id !== null, 403);

        return view('appointments.create', $this->formOptions($request));
    }

    /**
     * Store a newly created appointment.
     */
    public function store(StoreAppointmentRequest $request, AvailabilityService $availabilityService, ServiceOrderService $serviceOrders): RedirectResponse
    {
        $data = $this->appointmentData($request);

        $appointment = DB::transaction(function () use ($request, $availabilityService, $data): Appointment {
            $this->ensureSlotStillAvailable($request, $availabilityService, $data);

            return Appointment::create([
                ...$data,
                'company_id' => $request->user()->company_id,
            ]);
        });

        $serviceOrders->ensureForAppointment($appointment);

        return redirect()->route('appointments.show', $appointment)->with('status', 'appointment-created');
    }

    /**
     * Display the specified appointment.
     */
    public function show(Request $request, Appointment $appointment): View
    {
        $this->ensureAppointmentBelongsToUserCompany($request, $appointment);

        return view('appointments.show', [
            'appointment' => $appointment->load(['client', 'service', 'services', 'user', 'payment', 'serviceOrder.items']),
        ]);
    }

    /**
     * Show the form for editing the specified appointment.
     */
    public function edit(Request $request, Appointment $appointment): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureAppointmentBelongsToUserCompany($request, $appointment);

        return view('appointments.edit', [
            'appointment' => $appointment,
            ...$this->formOptions($request),
        ]);
    }

    /**
     * Update the specified appointment.
     */
    public function update(UpdateAppointmentRequest $request, Appointment $appointment, AvailabilityService $availabilityService): RedirectResponse
    {
        $this->ensureAppointmentBelongsToUserCompany($request, $appointment);

        $data = $this->appointmentData($request);

        DB::transaction(function () use ($request, $appointment, $availabilityService, $data): void {
            $this->ensureSlotStillAvailable($request, $availabilityService, $data, $appointment);

            $appointment->update($data);
        });

        return redirect()->route('appointments.show', $appointment)->with('status', 'appointment-updated');
    }

    /**
     * Remove the specified appointment.
     */
    public function destroy(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureAppointmentBelongsToUserCompany($request, $appointment);

        $appointment->delete();

        return redirect()->route('appointments.index')->with('status', 'appointment-deleted');
    }

    /**
     * Quickly update the status of an appointment.
     */
    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->ensureAppointmentBelongsToUserCompany($request, $appointment);

        $data = $request->validate([
            'status' => ['required', 'in:scheduled,confirmed,in_progress,cancelled'],
        ], [
            'status.required' => 'Selecione um status valido.',
            'status.in' => 'Selecione um status valido.',
        ]);

        $appointment->update([
            'status' => $data['status'],
        ]);

        return back()->with('status', 'appointment-status-updated');
    }

    /**
     * Get select options for appointment forms.
     *
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $companyId = $request->user()->company_id;

        return [
            'clients' => Client::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(),
            'services' => Service::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(),
            'users' => User::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(),
            'statuses' => Appointment::STATUSES,
            'sources' => Appointment::SOURCES,
        ];
    }

    /**
     * Ensure an appointment belongs to the authenticated user's company.
     */
    private function ensureAppointmentBelongsToUserCompany(Request $request, Appointment $appointment): void
    {
        abort_unless($appointment->company_id === $request->user()->company_id, 404);
    }

    /**
     * Get appointment data with end time calculated from service duration.
     *
     * @return array<string, mixed>
     */
    private function appointmentData(StoreAppointmentRequest|UpdateAppointmentRequest $request): array
    {
        $data = $request->validated();
        $service = Service::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($data['service_id']);

        $startTime = Carbon::parse($data['start_time']);
        $data['end_time'] = $startTime->copy()->addMinutes($service->duration_minutes);

        return $data;
    }

    /**
     * Re-check availability inside the write transaction while locking the professional row.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function ensureSlotStillAvailable(
        Request $request,
        AvailabilityService $availabilityService,
        array $data,
        ?Appointment $ignoreAppointment = null,
    ): void {
        $professional = User::query()
            ->where('company_id', $request->user()->company_id)
            ->whereKey($data['user_id'])
            ->lockForUpdate()
            ->firstOrFail();

        $service = Service::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($data['service_id']);
        $startTime = CarbonImmutable::parse((string) $data['start_time']);

        $availableSlots = $availabilityService->availableSlots(
            $request->user()->company,
            $professional,
            $service,
            $startTime,
            false,
            $ignoreAppointment?->id,
        );

        if (! in_array($startTime->format('H:i'), $availableSlots, true)) {
            throw ValidationException::withMessages([
                'start_time' => 'Este horário não está disponível para a agenda real deste profissional.',
            ]);
        }
    }
}
