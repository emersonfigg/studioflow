<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\ServiceOrderService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
        $serviceIds = $data['service_ids'];
        $productItems = $data['product_items'];
        unset($data['service_ids'], $data['product_items']);

        $appointment = DB::transaction(function () use ($request, $availabilityService, $serviceOrders, $data, $serviceIds, $productItems): Appointment {
            $this->ensureSlotStillAvailable($request, $availabilityService, $data);
            $this->ensureClientStillAvailable($request, $data);

            $appointment = Appointment::create([
                ...$data,
                'company_id' => $request->user()->company_id,
            ]);

            $this->syncAppointmentServices($appointment, $serviceIds);

            $order = $serviceOrders->ensureForAppointment($appointment->load(['service', 'services']));

            foreach ($productItems as $item) {
                $product = Product::query()
                    ->where('company_id', $request->user()->company_id)
                    ->whereKey($item['product_id'])
                    ->firstOrFail();

                $serviceOrders->addProduct($order, $product, (int) $item['quantity']);
            }

            return $appointment;
        });

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
        $serviceIds = $data['service_ids'];
        unset($data['service_ids'], $data['product_items']);

        DB::transaction(function () use ($request, $appointment, $availabilityService, $data, $serviceIds): void {
            $this->ensureSlotStillAvailable($request, $availabilityService, $data, $appointment);
            $this->ensureClientStillAvailable($request, $data, $appointment);

            $appointment->update(Arr::only($data, $appointment->getFillable()));
            $this->syncAppointmentServices($appointment->fresh(), $serviceIds);
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

    public function clientHistory(Request $request, Client $client): JsonResponse
    {
        abort_unless($client->company_id === $request->user()->company_id, 404);

        $appointments = $client->appointments()
            ->with(['services', 'service', 'user'])
            ->where('company_id', $request->user()->company_id)
            ->latest('start_time')
            ->limit(5)
            ->get();
        $productSales = $client->productSales()
            ->with('items.product')
            ->where('company_id', $request->user()->company_id)
            ->latest('sold_at')
            ->limit(5)
            ->get();
        $standaloneServiceOrders = $client->serviceOrders()
            ->with('items.service')
            ->where('company_id', $request->user()->company_id)
            ->whereNull('appointment_id')
            ->whereHas('items', fn ($query) => $query->where('type', 'service'))
            ->latest('closed_at')
            ->limit(5)
            ->get();
        $serviceSpent = (float) $client->payments()
            ->where('company_id', $request->user()->company_id)
            ->sum('gross_amount');
        $productSpent = (float) $client->productSales()
            ->where('company_id', $request->user()->company_id)
            ->sum('gross_amount');
        $totalSpent = $serviceSpent + $productSpent;
        $interactionCount = max(1, $appointments->where('status', 'completed')->count() + $productSales->count() + $standaloneServiceOrders->count());
        $lastAppointment = $appointments->first();
        $lastVisit = $lastAppointment?->start_time ?? $client->last_visit_at;

        return response()->json([
            'has_history' => $appointments->isNotEmpty() || $productSales->isNotEmpty() || $standaloneServiceOrders->isNotEmpty(),
            'last_visit' => $lastVisit?->format('d/m/Y H:i'),
            'last_services' => $appointments
                ->flatMap(fn (Appointment $appointment) => $appointment->bookedServices()->pluck('name'))
                ->merge($standaloneServiceOrders->flatMap(fn ($order) => $order->items->pluck('service.name')->filter()))
                ->unique()
                ->take(5)
                ->values(),
            'last_products' => $productSales
                ->flatMap(fn ($sale) => $sale->items->pluck('product.name')->filter())
                ->unique()
                ->take(5)
                ->values(),
            'total_spent' => round($totalSpent, 2),
            'average_ticket' => round($totalSpent / $interactionCount, 2),
            'notes' => $client->notes,
            'repeat_service_ids' => $lastAppointment?->bookedServices()->pluck('id')->values() ?? [],
            'empty_message' => 'Este cliente ainda não possui histórico de atendimento.',
        ]);
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
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'products' => Product::query()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'users' => User::query()
                ->where('company_id', $companyId)
                ->where('active', true)
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
        $serviceIds = collect($data['service_ids'] ?? [$data['service_id']])
            ->filter()
            ->map(fn (mixed $serviceId): int => (int) $serviceId)
            ->unique()
            ->values();
        $services = Service::query()
            ->where('company_id', $request->user()->company_id)
            ->whereIn('id', $serviceIds)
            ->get()
            ->keyBy('id');
        $orderedServices = $serviceIds->map(fn (int $serviceId) => $services->get($serviceId))->filter()->values();

        $startTime = Carbon::parse($data['start_time']);
        $data['service_id'] = $orderedServices->first()?->id ?? $data['service_id'];
        $data['service_ids'] = $orderedServices->pluck('id')->all();
        $data['product_items'] = $data['product_items'] ?? [];
        $data['end_time'] = $startTime->copy()->addMinutes((int) $orderedServices->sum('duration_minutes'));

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

        $durationMinutes = (int) Service::query()
            ->where('company_id', $request->user()->company_id)
            ->whereIn('id', $data['service_ids'] ?? [$data['service_id']])
            ->sum('duration_minutes');
        $startTime = CarbonImmutable::parse((string) $data['start_time']);

        $availableSlots = $availabilityService->availableSlotsForDuration(
            $request->user()->company,
            $professional,
            $durationMinutes,
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

    /**
     * Re-check client schedule conflicts inside the write transaction.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function ensureClientStillAvailable(
        Request $request,
        array $data,
        ?Appointment $ignoreAppointment = null,
    ): void {
        Client::query()
            ->where('company_id', $request->user()->company_id)
            ->whereKey($data['client_id'])
            ->lockForUpdate()
            ->firstOrFail();

        $conflict = Appointment::findClientScheduleConflict(
            (int) $request->user()->company_id,
            (int) $data['client_id'],
            $data['start_time'],
            $data['end_time'],
            $ignoreAppointment?->id,
        );

        if ($conflict) {
            throw ValidationException::withMessages([
                'start_time' => 'Este cliente já possui um agendamento ativo nesse horário.',
            ]);
        }
    }

    /**
     * @param  array<int, int>  $serviceIds
     */
    private function syncAppointmentServices(Appointment $appointment, array $serviceIds): void
    {
        $services = Service::query()
            ->where('company_id', $appointment->company_id)
            ->whereIn('id', $serviceIds)
            ->get()
            ->keyBy('id');

        $syncData = [];

        foreach (array_values($serviceIds) as $index => $serviceId) {
            $service = $services->get($serviceId);

            if (! $service) {
                continue;
            }

            $syncData[$service->id] = [
                'price_snapshot' => $service->price,
                'duration_snapshot' => $service->duration_minutes,
                'order' => $index + 1,
            ];
        }

        $appointment->services()->sync($syncData);
    }
}
