<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicBookingRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\ServiceOrderService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        $hasSelectedServices = $selectedServices->isNotEmpty();
        $totalDurationMinutes = $hasSelectedServices
            ? (int) $selectedServices->sum('duration_minutes')
            : self::DEFAULT_BOOKING_DURATION_MINUTES;
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
                        1 => 'Amanhã',
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
            'usingEstimatedDuration' => ! $hasSelectedServices,
            'identifiedClient' => $this->identifiedClient($company),
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
     * Redirect the external client to Google OAuth without touching internal User auth.
     */
    public function redirectToGoogle(Request $request, Company $company): RedirectResponse
    {
        $clientId = config('services.google.client_id');
        $redirectUri = config('services.google.redirect') ?: route('public-bookings.google.callback');

        if (! $clientId || ! config('services.google.client_secret')) {
            return redirect()
                ->route('public-bookings.create', $company)
                ->withErrors(['client_email' => 'Login com Google ainda não está configurado.']);
        }

        $state = Str::random(40);
        $request->session()->put('public_google_oauth_'.$state, [
            'company_id' => $company->id,
            'query' => $request->except(['state', 'code', 'scope', 'authuser', 'prompt']),
        ]);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    /**
     * Receive Google OAuth callback and identify a Client inside the intended company.
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state', '');
        $sessionKey = 'public_google_oauth_'.$state;
        $payload = $request->session()->pull($sessionKey);

        if (! is_array($payload) || ! isset($payload['company_id'])) {
            return redirect()
                ->to('/')
                ->withErrors(['client_email' => 'Não foi possível validar o retorno do Google. Tente novamente.']);
        }

        $company = Company::query()->find($payload['company_id']);

        if (! $company) {
            return redirect()
                ->to('/')
                ->withErrors(['client_email' => 'Empresa não encontrada para este agendamento.']);
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()
                ->route('public-bookings.create', ['company' => $company, ...($payload['query'] ?? [])])
                ->withErrors(['client_email' => 'Não foi possível continuar com Google. Você pode preencher seus dados manualmente.']);
        }

        try {
            $googleClient = $this->googleClientFromCode((string) $request->query('code'));
            $client = $this->resolveGoogleClient($company, $googleClient);
        } catch (\Throwable) {
            return redirect()
                ->route('public-bookings.create', ['company' => $company, ...($payload['query'] ?? [])])
                ->withErrors(['client_email' => 'Falha ao consultar sua conta Google. Tente novamente ou use o formulário manual.']);
        }

        $request->session()->put($this->publicClientSessionKey($company), $client->id);

        return redirect()
            ->route('public-bookings.create', ['company' => $company, ...($payload['query'] ?? [])])
            ->with('status', 'public-client-authenticated');
    }

    /**
     * Store a new public booking.
     */
    public function store(StorePublicBookingRequest $request, Company $company, AvailabilityService $availabilityService, ServiceOrderService $serviceOrders): RedirectResponse
    {
        $data = $request->validated();
        $startTime = Carbon::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['time']}");

        $appointment = DB::transaction(function () use ($company, $availabilityService, $startTime, $data, $request): Appointment {
            $user = User::query()
                ->where('company_id', $company->id)
                ->where('active', true)
                ->whereKey($data['user_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $services = Service::query()
                ->where('company_id', $company->id)
                ->where('active', true)
                ->whereIn('id', $data['service_ids'])
                ->get();
            $orderedServices = $this->orderedSelectedServices(
                $services,
                collect($data['service_ids'])->map(fn (mixed $id): int => (int) $id)
            );
            $totalDurationMinutes = (int) $orderedServices->sum('duration_minutes');

            $availableSlots = $availabilityService->availableSlotsForDuration(
                $company,
                $user,
                $totalDurationMinutes,
                $startTime,
                true,
            );

            if (! in_array($startTime->format('H:i'), $availableSlots, true)) {
                throw ValidationException::withMessages([
                    'time' => 'Este horário não está mais disponível.',
                ]);
            }

            $client = $this->resolveBookingClient($request, $company, $data);

            $appointment = Appointment::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'user_id' => $user->id,
                'service_id' => $orderedServices->firstOrFail()->id,
                'start_time' => $startTime,
                'end_time' => $startTime->copy()->addMinutes($totalDurationMinutes),
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

        $serviceOrders->ensureForAppointment($appointment);

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
            'whatsAppUrl' => 'https://wa.me/'.preg_replace('/\D+/', '', (string) $appointment->client->phone),
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
            return new Collection;
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

        if (! $requestedUser || collect($request->input('service_ids', []))->filter()->isEmpty()) {
            return [$requestedUser, []];
        }

        return [
            $requestedUser,
            $availabilityService->slotOptionsForDuration(
                $company,
                $requestedUser,
                $totalDurationMinutes,
                $selectedDate,
                true,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveBookingClient(Request $request, Company $company, array $data): Client
    {
        $identifiedClient = $this->identifiedClient($company);

        if ($identifiedClient) {
            return $identifiedClient;
        }

        $email = $this->normalizeEmail($data['client_email'] ?? null);
        $phone = trim((string) ($data['client_phone'] ?? ''));

        $client = null;

        if ($email) {
            $client = Client::query()
                ->where('company_id', $company->id)
                ->where('email', $email)
                ->first();
        }

        if (! $client && $phone !== '') {
            $client = Client::query()
                ->where('company_id', $company->id)
                ->where('phone', $phone)
                ->first();
        }

        $client ??= new Client([
            'company_id' => $company->id,
        ]);

        $client->fill([
            'name' => $data['client_name'],
            'phone' => $phone,
            'email' => $email,
        ]);
        $client->save();

        $request->session()->put($this->publicClientSessionKey($company), $client->id);

        return $client;
    }

    /**
     * @return array{id: string, name: string, email: string, avatar: ?string}
     */
    private function googleClientFromCode(string $code): array
    {
        $redirectUri = config('services.google.redirect') ?: route('public-bookings.google.callback');

        $tokenResponse = Http::asForm()
            ->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ])
            ->throw()
            ->json();

        $profile = Http::withToken($tokenResponse['access_token'] ?? '')
            ->get('https://openidconnect.googleapis.com/v1/userinfo')
            ->throw()
            ->json();

        if (empty($profile['sub']) || empty($profile['email'])) {
            throw ValidationException::withMessages([
                'client_email' => 'Sua conta Google não retornou e-mail válido.',
            ]);
        }

        return [
            'id' => (string) $profile['sub'],
            'name' => (string) ($profile['name'] ?? $profile['email']),
            'email' => $this->normalizeEmail($profile['email']),
            'avatar' => $profile['picture'] ?? null,
        ];
    }

    /**
     * @param  array{id: string, name: string, email: string, avatar: ?string}  $googleClient
     */
    private function resolveGoogleClient(Company $company, array $googleClient): Client
    {
        $client = Client::query()
            ->where('company_id', $company->id)
            ->where('google_id', $googleClient['id'])
            ->first();

        if (! $client) {
            $client = Client::query()
                ->where('company_id', $company->id)
                ->where('email', $googleClient['email'])
                ->first();
        }

        $client ??= new Client([
            'company_id' => $company->id,
            'phone' => '',
        ]);

        $client->fill([
            'name' => $client->exists ? $client->name : $googleClient['name'],
            'email' => $googleClient['email'],
            'google_id' => $googleClient['id'],
            'avatar' => $googleClient['avatar'],
            'email_verified_at' => now(),
        ]);
        $client->save();

        return $client;
    }

    private function identifiedClient(Company $company): ?Client
    {
        $clientId = session($this->publicClientSessionKey($company));

        if (! $clientId) {
            return null;
        }

        return Client::query()
            ->where('company_id', $company->id)
            ->find($clientId);
    }

    private function publicClientSessionKey(Company $company): string
    {
        return 'public_booking_client_'.$company->id;
    }

    private function normalizeEmail(mixed $email): ?string
    {
        $email = trim(strtolower((string) $email));

        return $email !== '' ? $email : null;
    }
}
