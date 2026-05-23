<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicBookingRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\MembershipPlan;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingPaymentService;
use App\Services\BrandingService;
use App\Services\CustomerBlockService;
use App\Services\MembershipService;
use App\Services\ReviewService;
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
        $company->load(['bookingCoverImages']);

        $bookingPaymentService = app(BookingPaymentService::class);
        $reviewService = app(ReviewService::class);
        $services = Service::query()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $users = User::query()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->with('workingHours')
            ->orderBy('name')
            ->get();
        $membershipPlans = MembershipPlan::query()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->with('services')
            ->orderBy('price')
            ->orderBy('name')
            ->get();
        $reviewSummary = $reviewService->getCompanyReviewSummary((int) $company->id);

        $rawSelectedServiceIds = collect($request->old('service_ids', $request->input('service_ids', [])))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->values();

        $selectedServices = $this->orderedSelectedServices($services, $rawSelectedServiceIds);
        $selectedServiceIds = $selectedServices->pluck('id')->values();
        $selectedDate = $request->filled('date')
            ? CarbonImmutable::parse((string) $request->input('date'))->toDateString()
            : CarbonImmutable::today()->toDateString();
        $identifiedClient = $this->identifiedClient($company);
        $hasSelectedServices = $selectedServices->isNotEmpty();
        $totalDurationMinutes = $hasSelectedServices
            ? $this->totalDurationForClient($company, $selectedServices, $identifiedClient?->id, CarbonImmutable::parse($selectedDate))
            : self::DEFAULT_BOOKING_DURATION_MINUTES;
        $totalPrice = (float) $selectedServices->sum(fn (Service $service): float => (float) $service->price);
        $onlineBookingPaymentConfigured = $bookingPaymentService->onlinePaymentEnabled($company);
        $canOfferOnlineBookingPayment = $hasSelectedServices
            && $company->canOfferOnlineBookingPayment($totalPrice);
        $shouldRequireOnlineBookingPayment = $hasSelectedServices
            && $company->shouldRequireOnlineBookingPayment($totalPrice);
        $depositAmount = $canOfferOnlineBookingPayment
            ? $bookingPaymentService->depositAmountFor($company, $totalPrice)
            : 0.0;
        $remainingAmount = max(0, round($totalPrice - $depositAmount, 2));
        $bookingDepositType = (string) ($company->booking_deposit_type ?: 'fixed');
        $bookingDepositValue = (float) ($company->booking_deposit_value ?: 0);
        $depositSummaryText = $bookingPaymentService->paymentMode($company) === 'full'
            ? 'R$ '.number_format($depositAmount, 2, ',', '.')
            : ($bookingDepositType === 'percentage'
                ? rtrim(rtrim(number_format($bookingDepositValue, 2, ',', '.'), '0'), ',').'% (R$ '.number_format($depositAmount, 2, ',', '.').')'
                : 'R$ '.number_format($depositAmount, 2, ',', '.'));
        $selectedPaymentChoice = old(
            'payment_choice',
            $shouldRequireOnlineBookingPayment
                ? 'online'
                : ($company->bookingPaymentOptional() && $canOfferOnlineBookingPayment ? null : 'on_site')
        );
        [$selectedUser, $slotOptions] = $this->resolveSelectedUserAndSlots(
            $request,
            $company,
            $users,
            $availabilityService,
            $totalDurationMinutes,
            $selectedDate,
            $identifiedClient?->id,
        );
        $availableSlots = collect($slotOptions)
            ->where('available', true)
            ->pluck('time')
            ->values()
            ->all();

        $quickDates = collect(range(0, 6))
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

        $brandingService = app(BrandingService::class);

        return view('public-bookings.create', [
            'company' => $company,
            'publicBranding' => $brandingService->getCurrentCompanyBranding($company),
            'publicFaviconHref' => $brandingService->faviconHrefFor($company),
            'services' => $services,
            'users' => $users,
            'membershipPlans' => $membershipPlans,
            'reviewSummary' => $reviewSummary,
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
            'onlineBookingPaymentEnabled' => $canOfferOnlineBookingPayment,
            'onlineBookingPaymentConfigured' => $onlineBookingPaymentConfigured,
            'canOfferOnlineBookingPayment' => $canOfferOnlineBookingPayment,
            'shouldRequireOnlineBookingPayment' => $shouldRequireOnlineBookingPayment,
            'bookingPaymentRequirement' => $company->booking_payment_requirement ?: 'disabled',
            'bookingPaymentMode' => $onlineBookingPaymentConfigured ? (string) $company->booking_payment_mode : 'none',
            'bookingDepositType' => $bookingDepositType,
            'bookingDepositValue' => $bookingDepositValue,
            'depositAmount' => $depositAmount,
            'depositSummaryText' => $depositSummaryText,
            'remainingAmount' => $remainingAmount,
            'bookingPaymentExpirationMinutes' => (int) ($company->booking_payment_expiration_minutes ?: 15),
            'selectedPaymentChoice' => $selectedPaymentChoice,
            'identifiedClient' => $identifiedClient,
            'googleConfigured' => (bool) (config('services.google.client_id') && config('services.google.client_secret')),
            'servicesCatalog' => $services->map(fn (Service $service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description ? (string) $service->description : null,
                'category' => 'Serviços',
                'duration' => $this->durationForClient($company, $service, $identifiedClient?->id, CarbonImmutable::parse($selectedDate)),
                'price' => $service->publicPriceLabel(),
                'price_value' => (float) $service->price,
                'image_url' => $service->image_url,
            ])->values(),
        ]);
    }

    /**
     * Return available public booking slots without refreshing the wizard.
     */
    public function slots(Request $request, Company $company, AvailabilityService $availabilityService): \Illuminate\Http\JsonResponse
    {
        $serviceIds = collect($request->input('service_ids', []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->values();

        $services = Service::query()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->whereIn('id', $serviceIds)
            ->get();

        $user = User::query()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->find((int) $request->input('user_id'));

        $selectedDate = $request->filled('date')
            ? CarbonImmutable::parse((string) $request->input('date'))->toDateString()
            : CarbonImmutable::today()->toDateString();

        if ($services->isEmpty() || ! $user) {
            return response()->json([
                'slots' => [],
                'available_slots' => [],
                'next_available_slot' => null,
            ]);
        }

        $slotOptions = $availabilityService->slotOptionsForDuration(
            $company,
            $user,
            $this->totalDurationForClient($company, $services, $this->identifiedClient($company)?->id, CarbonImmutable::parse($selectedDate)),
            $selectedDate,
            true,
            null,
            $this->identifiedClient($company)?->id,
        );

        $availableSlotOptions = collect($slotOptions)
            ->where('available', true)
            ->map(fn (array $slot): array => [
                'time' => $slot['time'],
                'available' => true,
                'public_label' => $slot['public_label'] ?? null,
            ])
            ->values();
        $availableSlots = $availableSlotOptions
            ->where('available', true)
            ->pluck('time')
            ->values();

        return response()->json([
            'slots' => $availableSlotOptions,
            'available_slots' => $availableSlots,
            'next_available_slot' => $availableSlots->first(),
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
        } catch (ValidationException $exception) {
            return redirect()
                ->route('public-bookings.create', ['company' => $company, ...($payload['query'] ?? [])])
                ->withErrors($exception->errors());
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
    public function store(
        StorePublicBookingRequest $request,
        Company $company,
        AvailabilityService $availabilityService,
        ServiceOrderService $serviceOrders,
        BookingPaymentService $bookingPaymentService,
    ): RedirectResponse {
        $data = $request->validated();
        $startTime = Carbon::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['time']}");
        $onlinePaymentConfigured = $bookingPaymentService->onlinePaymentEnabled($company);
        $servicesTotal = (float) Service::query()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->whereIn('id', $data['service_ids'])
            ->sum('price');
        $canOfferOnlineBookingPayment = $company->canOfferOnlineBookingPayment($servicesTotal);
        $shouldRequireOnlineBookingPayment = $company->shouldRequireOnlineBookingPayment($servicesTotal);
        $paymentChoice = $data['payment_choice'] ?? null;
        $shouldCreateOnlinePayment = null;

        if ($company->bookingPaymentRequired()) {
            try {
                $bookingPaymentService->ensureCompanyCanAcceptOnlineBooking($company, $servicesTotal);
                $shouldRequireOnlineBookingPayment = true;
                $shouldCreateOnlinePayment = true;
                $paymentChoice = 'online';
            } catch (\Throwable $e) {
                return redirect()
                    ->route('public-bookings.create', [
                        'company' => $company,
                        'service_ids' => $data['service_ids'],
                        'user_id' => $data['user_id'],
                        'date' => $data['date'],
                        'filters_submitted' => 1,
                    ])
                    ->withInput($request->except(['time']))
                    ->withErrors(['payment' => $e->getMessage()]);
            }
        } elseif ($company->bookingPaymentOptional() && $canOfferOnlineBookingPayment && ! in_array($paymentChoice, ['online', 'on_site'], true)) {
            return redirect()
                ->route('public-bookings.create', [
                    'company' => $company,
                    'service_ids' => $data['service_ids'],
                    'user_id' => $data['user_id'],
                    'date' => $data['date'],
                    'filters_submitted' => 1,
                ])
                ->withInput($request->except(['time']))
                ->withErrors(['payment_choice' => 'Escolha se deseja pagar agora ou pagar no local.']);
        }

        $shouldCreateOnlinePayment = $shouldCreateOnlinePayment
            ?? ($shouldRequireOnlineBookingPayment
                || ($company->bookingPaymentOptional() && $canOfferOnlineBookingPayment && $paymentChoice === 'online'));

        if (! $company->bookingPaymentRequired() && $shouldCreateOnlinePayment) {
            try {
                $bookingPaymentService->ensureCompanyCanAcceptOnlineBooking($company, $servicesTotal);
            } catch (\Throwable $e) {
                return redirect()
                    ->route('public-bookings.create', [
                        'company' => $company,
                        'service_ids' => $data['service_ids'],
                        'user_id' => $data['user_id'],
                        'date' => $data['date'],
                        'filters_submitted' => 1,
                    ])
                    ->withInput($request->except(['time']))
                    ->withErrors(['payment' => $e->getMessage()]);
            }
        }

        $appointment = DB::transaction(function () use ($company, $availabilityService, $startTime, $data, $request, $shouldCreateOnlinePayment, $paymentChoice): Appointment {
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
            $client = $this->resolveBookingClient($request, $company, $data);
            $totalDurationMinutes = $this->totalDurationForClient($company, $orderedServices, (int) $client->id, $startTime);

            $availableSlots = $availabilityService->availableSlotsForDuration(
                $company,
                $user,
                $totalDurationMinutes,
                $startTime,
                true,
                null,
                null,
            );

            if (! in_array($startTime->format('H:i'), $availableSlots, true)) {
                throw ValidationException::withMessages([
                    'time' => 'Este horário não está mais disponível.',
                ]);
            }

            if (! $client->isOperationallyActive()) {
                throw ValidationException::withMessages([
                    'client_phone' => 'Este cadastro esta desativado. Entre em contato com a empresa.',
                ]);
            }

            if (app(CustomerBlockService::class)->isBlocked($client)) {
                throw ValidationException::withMessages([
                    'client_phone' => 'Seu cadastro esta temporariamente bloqueado para novos agendamentos online. Entre em contato com a empresa.',
                ]);
            }

            $endTime = $startTime->copy()->addMinutes($totalDurationMinutes);
            $amountTotal = round((float) $orderedServices->sum(fn (Service $service): float => (float) $service->price), 2);

            Client::query()
                ->where('company_id', $company->id)
                ->whereKey($client->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (Appointment::findClientScheduleConflict($company->id, $client->id, $startTime, $endTime)) {
                throw ValidationException::withMessages([
                    'time' => 'Você já possui um agendamento neste horário. Cancele ou escolha outro horário.',
                ]);
            }

            $appointment = Appointment::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'user_id' => $user->id,
                'service_id' => $orderedServices->firstOrFail()->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => $shouldCreateOnlinePayment ? 'pending_payment' : 'scheduled',
                'payment_status' => $shouldCreateOnlinePayment ? 'pending' : ($paymentChoice === 'on_site' ? 'unpaid' : null),
                'payment_gateway' => $shouldCreateOnlinePayment ? 'mercado_pago' : ($paymentChoice === 'on_site' ? 'on_site' : null),
                'amount_total' => $amountTotal,
                'amount_paid' => 0,
                'deposit_amount' => 0,
                'confirmed_at' => $shouldCreateOnlinePayment ? null : now(),
                'source' => 'public_booking',
                'notes' => $data['notes'] ?? null,
            ]);

            $appointment->services()->attach(
                $orderedServices->values()->mapWithKeys(
                    fn (Service $service, int $index): array => [
                        $service->id => [
                            'price_snapshot' => number_format((float) $service->price, 2, '.', ''),
                            'duration_snapshot' => $this->durationForClient($company, $service, (int) $client->id, $startTime),
                            'order' => $index + 1,
                        ],
                    ]
                )->all()
            );

            return $appointment;
        });

        $serviceOrders->ensureForAppointment($appointment);

        if ($shouldCreateOnlinePayment) {
            try {
                $bookingPayment = $bookingPaymentService->createCheckoutForAppointment(
                    $company,
                    $appointment->fresh(['company', 'client', 'service', 'services'])
                );
            } catch (\Throwable $e) {
                return redirect()
                    ->route('public-bookings.create', [
                        'company' => $company,
                        'service_ids' => $data['service_ids'],
                        'user_id' => $data['user_id'],
                        'date' => $data['date'],
                        'filters_submitted' => 1,
                    ])
                    ->withInput($request->except(['time']))
                    ->withErrors(['payment' => 'Nao foi possivel gerar o pagamento online agora. Tente novamente em instantes.']);
            }

            return redirect()
                ->route('public-bookings.payment.pending', [
                    'company' => $company,
                    'reference' => $bookingPayment->external_reference,
                ])
                ->with('status', 'public-booking-payment-pending');
        }

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
    public function success(Company $company, Appointment $appointment): View|RedirectResponse
    {
        abort_unless($appointment->company_id === $company->id, 404);

        if ($appointment->status === 'pending_payment' && filled($appointment->payment_reference)) {
            return redirect()->route('public-bookings.payment.pending', [
                'company' => $company,
                'reference' => $appointment->payment_reference,
            ]);
        }

        $appointment->load(['client', 'service', 'user', 'services']);

        $brandingService = app(BrandingService::class);

        return view('public-bookings.success', [
            'company' => $company,
            'appointment' => $appointment,
            'publicBranding' => $brandingService->getCurrentCompanyBranding($company),
            'publicFaviconHref' => $brandingService->faviconHrefFor($company),
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
        ?int $clientId = null,
    ): array {
        if ($users->isEmpty() || $totalDurationMinutes <= 0) {
            return [null, []];
        }

        $requestedUser = $request->filled('user_id')
            ? $users->firstWhere('id', (int) $request->input('user_id'))
            : ($users->count() === 1 ? $users->first() : null);

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
                null,
                $clientId,
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

        if ($client && $client->exists && ! $client->isOperationallyActive()) {
            throw ValidationException::withMessages([
                'client_email' => 'Este cadastro esta desativado. Entre em contato com a empresa.',
            ]);
        }

        $client ??= new Client([
            'company_id' => $company->id,
            'active' => true,
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

        if ($client && $client->exists && ! $client->isOperationallyActive()) {
            throw ValidationException::withMessages([
                'client_email' => 'Este cadastro esta desativado. Entre em contato com a empresa.',
            ]);
        }

        $client ??= new Client([
            'company_id' => $company->id,
            'phone' => '',
            'active' => true,
        ]);

        $client->fill([
            'name' => $client->exists ? $client->name : $googleClient['name'],
            'email' => $googleClient['email'],
            'google_id' => $googleClient['id'],
            'avatar' => $googleClient['avatar'],
            'email_verified_at' => now(),
            'active' => true,
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
            ->where('active', true)
            ->find($clientId);
    }

    private function publicClientSessionKey(Company $company): string
    {
        return 'public_booking_client_'.$company->id;
    }

    /**
     * @param  iterable<Service>  $services
     */
    private function totalDurationForClient(Company $company, iterable $services, ?int $clientId, CarbonImmutable|Carbon $at): int
    {
        $total = 0;

        foreach ($services as $service) {
            $total += $this->durationForClient($company, $service, $clientId, $at);
        }

        return max(1, $total);
    }

    private function durationForClient(Company $company, Service $service, ?int $clientId, CarbonImmutable|Carbon $at): int
    {
        if ($clientId) {
            $special = app(MembershipService::class)->specialDurationForService((int) $company->id, $clientId, $service, $at);
            if ($special !== null) {
                return $special;
            }
        }

        return max(1, (int) $service->duration_minutes);
    }

    private function normalizeEmail(mixed $email): ?string
    {
        $email = trim(strtolower((string) $email));

        return $email !== '' ? $email : null;
    }
}
