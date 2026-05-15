<?php

namespace App\Http\Controllers;

use App\Enums\MembershipPaymentStatus;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Appointment;
use App\Models\AppointmentReview;
use App\Models\Client;
use App\Models\ClientCommercialHistory;
use App\Models\CompanyPaymentIntegration;
use App\Models\MembershipPayment;
use App\Models\MembershipPlan;
use App\Models\MembershipUsage;
use App\Services\ClientRecommendationService;
use App\Services\CustomerBlockService;
use App\Services\MembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Display a listing of clients for the authenticated user's company.
     */
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;
        $search = trim((string) $request->string('search'));
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $clients = Client::query()
            ->where('company_id', $companyId)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $normalizedSearch = Client::normalizeCpf($search) ?? preg_replace('/\D+/', '', $search);

                $query->where(function (Builder $query) use ($search, $normalizedSearch): void {
                    $query
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('client_code', 'like', '%'.strtoupper($search).'%');

                    if ($normalizedSearch !== '') {
                        $query->orWhere('cpf_normalized', 'like', '%'.$normalizedSearch.'%');
                    }
                });
            })
            ->withCount([
                'appointments as visits_count' => fn (Builder $query) => $query->where('status', 'completed'),
            ])
            ->withSum('payments as total_spent', 'gross_amount')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $totalClients = Client::query()
            ->where('company_id', $companyId)
            ->count();

        $newClientsThisMonth = Client::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $returningClientsThisMonth = Appointment::query()
            ->where('company_id', $companyId)
            ->where('status', 'completed')
            ->whereBetween('start_time', [$monthStart, $monthEnd])
            ->distinct('client_id')
            ->count('client_id');

        $averageTicket = (float) Client::query()
            ->where('clients.company_id', $companyId)
            ->join('payments', 'payments.client_id', '=', 'clients.id')
            ->avg('payments.gross_amount');

        return view('clients.index', [
            'clients' => $clients,
            'search' => $search,
            'totalClients' => $totalClients,
            'newClientsThisMonth' => $newClientsThisMonth,
            'returningClientsThisMonth' => $returningClientsThisMonth,
            'averageTicket' => $averageTicket,
        ]);
    }

    /**
     * Show the form for creating a new client.
     */
    public function create(Request $request): View
    {
        abort_unless($request->user()->isAdmin() && $request->user()->company_id !== null, 403);

        return view('clients.create');
    }

    /**
     * Store a newly created client.
     */
    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = Client::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
            'active' => true,
        ]);

        return redirect()->route('clients.show', $client)->with('status', 'client-created');
    }

    /**
     * Store or reuse a client inline from the appointment flow.
     */
    public function storeInline(Request $request): JsonResponse
    {
        abort_unless($request->user()?->company_id !== null, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:20'],
            'birthday' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $client = Client::query()
            ->where('company_id', $request->user()->company_id)
            ->where('phone', $data['phone'])
            ->first();

        $wasRecentlyCreated = false;

        if ($client === null) {
            $client = Client::create([
                ...$data,
                'cpf' => Client::normalizeCpf($data['cpf'] ?? null),
                'company_id' => $request->user()->company_id,
                'active' => true,
            ]);

            $wasRecentlyCreated = true;
        } elseif (! $client->isOperationallyActive()) {
            return response()->json([
                'message' => 'Este cadastro esta desativado. Solicite reativacao a administracao.',
            ], 422);
        }

        return response()->json([
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'client_code' => $client->client_code,
            ],
            'reused' => ! $wasRecentlyCreated,
            'message' => $wasRecentlyCreated
                ? 'Cliente criado com sucesso.'
                : 'Cliente existente reutilizado com sucesso.',
        ], $wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Display the specified client.
     */
    public function show(Request $request, Client $client, ClientRecommendationService $recommendations): View
    {
        $this->ensureClientBelongsToUserCompany($request, $client);

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $client->loadCount([
            'appointments as completed_visits_count' => fn (Builder $query) => $query->where('status', 'completed'),
        ]);
        $client->loadSum('payments as total_spent', 'gross_amount');
        $client->loadSum('productSales as total_products_spent', 'gross_amount');

        $appointments = $client->appointments()
            ->with(['service', 'services', 'user', 'payment'])
            ->latest('start_time')
            ->get();
        $productSales = $client->productSales()
            ->with(['user', 'items.product'])
            ->latest('sold_at')
            ->get();

        $lastCompletedAppointment = $appointments->firstWhere('status', 'completed');
        $lastProductSale = $productSales->first();
        $servicesSpent = (float) ($client->total_spent ?? 0);
        $productsSpent = (float) ($client->total_products_spent ?? 0);
        $totalSpent = $servicesSpent + $productsSpent;
        $interactionsCount = (int) $client->completed_visits_count + $productSales->count();
        $averageTicket = $interactionsCount > 0
            ? $totalSpent / $interactionsCount
            : 0.0;

        $commercialHistories = ClientCommercialHistory::query()
            ->where('company_id', $client->company_id)
            ->where('client_id', $client->id)
            ->with('professional')
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get();

        $clientRecommendations = $recommendations->getRecommendationsForClient(
            (int) $client->company_id,
            (int) $client->id,
        );

        $activeBlock = app(CustomerBlockService::class)->getActiveBlock($client);
        $noShows = $client->noShows()
            ->where('company_id', $client->company_id)
            ->with(['appointment', 'recordedBy'])
            ->latest('occurred_at')
            ->limit(15)
            ->get();
        $membershipSummary = app(MembershipService::class)->membershipSummaryForClient((int) $client->company_id, (int) $client->id);
        $memberships = $client->customerMemberships()
            ->with('plan')
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get();
        $membershipUsages = MembershipUsage::query()
            ->where('company_id', $client->company_id)
            ->where('client_id', $client->id)
            ->with(['membership.plan', 'service', 'appointment'])
            ->latest('used_at')
            ->limit(30)
            ->get();
        $clientReviews = AppointmentReview::query()
            ->where('company_id', $client->company_id)
            ->where('client_id', $client->id)
            ->whereNotNull('submitted_at')
            ->with(['appointment.services', 'appointment.service'])
            ->latest('submitted_at')
            ->limit(15)
            ->get();
        $membershipPlans = MembershipPlan::query()
            ->where('company_id', $client->company_id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $hasMembershipPaymentGateway = CompanyPaymentIntegration::query()
            ->where('company_id', $client->company_id)
            ->where('active', true)
            ->exists();

        $pendingMembershipPayment = MembershipPayment::query()
            ->where('company_id', $client->company_id)
            ->where('client_id', $client->id)
            ->where('status', MembershipPaymentStatus::Pending)
            ->with(['membership.plan'])
            ->orderByDesc('id')
            ->first();

        return view('clients.show', [
            'client' => $client,
            'appointments' => $appointments,
            'productSales' => $productSales,
            'serviceSpent' => $servicesSpent,
            'productSpent' => $productsSpent,
            'totalSpent' => $totalSpent,
            'totalVisits' => (int) $client->completed_visits_count,
            'lastVisitAt' => $lastCompletedAppointment?->start_time ?? $lastProductSale?->sold_at ?? $client->last_visit_at,
            'averageTicket' => $averageTicket,
            'appointmentsThisMonth' => $appointments
                ->whereBetween('start_time', [$monthStart, $monthEnd])
                ->count(),
            'productSalesThisMonth' => $productSales
                ->whereBetween('sold_at', [$monthStart, $monthEnd])
                ->count(),
            'commercialHistories' => $commercialHistories,
            'clientRecommendations' => $clientRecommendations,
            'activeBlock' => $activeBlock,
            'noShows' => $noShows,
            'membershipSummary' => $membershipSummary,
            'memberships' => $memberships,
            'membershipUsages' => $membershipUsages,
            'clientReviews' => $clientReviews,
            'membershipPlans' => $membershipPlans,
            'hasMembershipPaymentGateway' => $hasMembershipPaymentGateway,
            'pendingMembershipPayment' => $pendingMembershipPayment,
        ]);
    }

    /**
     * Show the form for editing the specified client.
     */
    public function edit(Request $request, Client $client): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureClientBelongsToUserCompany($request, $client);

        return view('clients.edit', [
            'client' => $client,
        ]);
    }

    /**
     * Update the specified client.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->ensureClientBelongsToUserCompany($request, $client);

        $client->update($request->validated());

        return redirect()->route('clients.show', $client)->with('status', 'client-updated');
    }

    /**
     * Remove the specified client.
     */
    public function destroy(Request $request, Client $client): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureClientBelongsToUserCompany($request, $client);

        if ($client->hasOperationalHistory()) {
            return redirect()
                ->route('clients.show', $client)
                ->with('status', 'client-delete-blocked');
        }

        $client->delete();

        return redirect()->route('clients.index')->with('status', 'client-deleted');
    }

    public function deactivate(Request $request, Client $client): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureClientBelongsToUserCompany($request, $client);
        $client->update(['active' => false]);

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'client-deactivated');
    }

    public function reactivate(Request $request, Client $client): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureClientBelongsToUserCompany($request, $client);
        $client->update(['active' => true]);

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'client-reactivated');
    }

    public function unblock(Request $request, Client $client, CustomerBlockService $customerBlockService): RedirectResponse
    {
        abort_unless($request->user()->hasFinancialPrivileges(), 403);
        $this->ensureClientBelongsToUserCompany($request, $client);

        $active = $customerBlockService->getActiveBlock($client);

        if ($active) {
            $customerBlockService->unblock($active, $request->user());
        }

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'client-unblocked');
    }

    /**
     * Ensure a client belongs to the authenticated user's company.
     */
    private function ensureClientBelongsToUserCompany(Request $request, Client $client): void
    {
        abort_unless($client->company_id === $request->user()->company_id, 404);
    }
}
