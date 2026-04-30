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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection as SupportCollection;

class PublicBookingController extends Controller
{
    private const DEFAULT_BOOKING_DURATION_MINUTES = 30;

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
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $rawSelectedServiceIds = collect($request->old('service_ids', $request->input('service_ids', [])))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->values();

        $selectedServices = $this->orderedSelectedServices($services, $rawSelectedServiceIds);
        $selectedServiceIds = $selectedServices->pluck('id')->values();
        $selectedDate = $request->filled('date')
            ? CarbonImmutable::parse((string) $request->input('date'))->toDateString()
            : CarbonImmutable::today()->toDateString();
        $usingEstimatedDuration = $selectedServices->isEmpty();
        $totalDurationMinutes = $usingEstimatedDuration
            ? self::DEFAULT_BOOKING_DURATION_MINUTES
            : (int) $selectedServices->sum('duration_minutes');
        $totalPrice = (float) $selectedServices->sum(fn (Service $service): float => (float) $service->price);

        [$selectedUser, $slotOptions] = $this->resolveSelectedUserAndSlots(
            $request,
            $company,
            $users,
            $availabilityService,
            $totalDurationMinutes,
            $selectedDate,
        );
        $availableSlots = collect($slotOptions)
            ->where('available', true)
            ->pluck('time')
            ->values()
            ->all();

        $quickDates = collect(range(0, 5))
            ->map(function (int $offset): array {
                $date = CarbonImmutable::today()->addDays($offset);

                return [
                    'value' => $date->toDateString(),
                    'label' => match ($offset) {
                        0 => 'Hoje',
                        1 => 'Amanha',
                        default => ucfirst($date->translatedFormat('D')),
                    },
                    'subtitle' => $date->format('d/m'),
                ];
            });

        return view('public-bookings.create', [
            'company' => $company,
            'services' => $services,
            'users' => $users,
            'selectedServices' => $selectedServices,
            'selectedServiceIds' => $selectedServiceIds,
            'selectedUser' => $selectedUser,
            'selectedUserId' => $selectedUser?->id,
            'selectedDate' => $selectedDate,
            'selectedTime' => old('time'),
            'slotOptions' => $slotOptions,
            'availableSlots' => $availableSlots,
            'quickDates' => $quickDates,
            'totalDurationMinutes' => $totalDurationMinutes,
            'totalPrice' => $totalPrice,
            'usingEstimatedDuration' => $usingEstimatedDuration,
            'servicesCatalog' => $services->map(fn (Service $service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'duration' => (int) $service->duration_minutes,
                'price' => number_format((float) $service->price, 2, ',', '.'),
                'price_value' => (float) $service->price,
                'image_url' => $service->image_url,
            ])->values(),
        ]);
    }

    /**
     * Store a new public booking.
     */
    public function store(StorePublicBookingRequest $request, Company $company): RedirectResponse
    {
        $data = $request->validated();
        $services = Service::query()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->whereIn('id', $data['service_ids'])
            ->get();
        $orderedServices = $this->orderedSelectedServices(
            $services,
            collect($data['service_ids'])->map(fn (mixed $id): int => (int) $id)
        );
        $user = User::query()
            ->where('company_id', $company->id)
            ->where('active', true)
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

        $appointment = DB::transaction(function () use ($company, $client, $user, $orderedServices, $startTime, $data): Appointment {
            $appointment = Appointment::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'user_id' => $user->id,
                'service_id' => $orderedServices->firstOrFail()->id,
                'start_time' => $startTime,
                'end_time' => $startTime->copy()->addMinutes((int) $orderedServices->sum('duration_minutes')),
                'status' => 'scheduled',
                'source' => 'public_booking',
                'notes' => $data['notes'] ?? null,
            ]);

            $appointment->services()->attach(
                $orderedServices->values()->mapWithKeys(
                    fn (Service $service, int $index): array => [
                        $service->id => [
                            'price_snapshot' => number_format((float) $service->price, 2, '.', ''),
                            'duration_snapshot' => (int) $service->duration_minutes,
                            'order' => $index + 1,
                        ],
                    ]
                )->all()
            );

            return $appointment;
        });

        return redirect()
            ->route('public-bookings.success', [
                'company' => $company,
                'appointment' => $appointment,
            ])
            ->with('status', 'public-booking-created');
    }

    /**
     * Show the public booking success page.
     */
    public function success(Company $company, Appointment $appointment): View
    {
        abort_unless($appointment->company_id === $company->id, 404);

        $appointment->load(['client', 'service', 'user', 'services']);

        return view('public-bookings.success', [
            'company' => $company,
            'appointment' => $appointment,
            'whatsAppUrl' => 'https://wa.me/' . preg_replace('/\D+/', '', (string) $appointment->client->phone),
        ]);
    }

    /**
     * Keep selected services ordered according to the submitted ids.
     *
     * @param  Collection<int, Service>  $services
     * @param  SupportCollection<int, int>  $selectedIds
     * @return Collection<int, Service>
     */
    private function orderedSelectedServices(Collection $services, SupportCollection $selectedIds): Collection
    {
        if ($selectedIds->isEmpty()) {
            return new Collection();
        }

        $selectedMap = $services->keyBy('id');

        return new Collection(
            $selectedIds
                ->map(fn (int $id): ?Service => $selectedMap->get($id))
                ->filter()
                ->values()
                ->all()
        );
    }

    /**
     * Resolve the selected professional and the slots for the chosen date.
     *
     * @param  Collection<int, User>  $users
     * @return array{0: ?User, 1: array<int, array{time: string, available: bool, reason: ?string}>}
     */
    private function resolveSelectedUserAndSlots(
        Request $request,
        Company $company,
        Collection $users,
        AvailabilityService $availabilityService,
        int $totalDurationMinutes,
        string $selectedDate,
    ): array {
        if ($users->isEmpty() || $totalDurationMinutes <= 0) {
            return [null, []];
        }

        $requestedUser = $users->firstWhere('id', (int) $request->input('user_id'));

        if ($requestedUser) {
            return [
                $requestedUser,
                $availabilityService->slotOptionsForDuration(
                    $company,
                    $requestedUser,
                    $totalDurationMinutes,
                    $selectedDate,
                    false,
                ),
            ];
        }

        $prioritizedUsers = $users
            ->filter(fn (User $user): bool => filled($user->photo_path))
            ->values()
            ->concat(
                $users
                    ->reject(fn (User $user): bool => filled($user->photo_path))
                    ->values()
            )
            ->values();

        foreach ($prioritizedUsers as $user) {
            $slots = $availabilityService->slotOptionsForDuration(
                $company,
                $user,
                $totalDurationMinutes,
                $selectedDate,
                false,
            );

            if (collect($slots)->contains(fn (array $slot): bool => $slot['available'])) {
                return [$user, $slots];
            }
        }

        $fallbackUser = $prioritizedUsers->first();

        if (! $fallbackUser) {
            return [null, []];
        }

        return [
            $fallbackUser,
            $availabilityService->slotOptionsForDuration(
                $company,
                $fallbackUser,
                $totalDurationMinutes,
                $selectedDate,
                false,
            ),
        ];
    }
}
