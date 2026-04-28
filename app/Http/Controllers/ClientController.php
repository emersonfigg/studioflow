<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Appointment;
use App\Models\Client;
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
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
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
                'company_id' => $request->user()->company_id,
            ]);

            $wasRecentlyCreated = true;
        }

        return response()->json([
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
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
    public function show(Request $request, Client $client): View
    {
        $this->ensureClientBelongsToUserCompany($request, $client);

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $client->loadCount([
            'appointments as completed_visits_count' => fn (Builder $query) => $query->where('status', 'completed'),
        ]);
        $client->loadSum('payments as total_spent', 'gross_amount');

        $appointments = $client->appointments()
            ->with(['service', 'services', 'user', 'payment'])
            ->latest('start_time')
            ->get();

        $lastCompletedAppointment = $appointments->firstWhere('status', 'completed');
        $averageTicket = $client->completed_visits_count > 0
            ? (float) ($client->total_spent ?? 0) / $client->completed_visits_count
            : 0.0;

        return view('clients.show', [
            'client' => $client,
            'appointments' => $appointments,
            'totalSpent' => (float) ($client->total_spent ?? 0),
            'totalVisits' => (int) $client->completed_visits_count,
            'lastVisitAt' => $lastCompletedAppointment?->start_time ?? $client->last_visit_at,
            'averageTicket' => $averageTicket,
            'appointmentsThisMonth' => $appointments
                ->whereBetween('start_time', [$monthStart, $monthEnd])
                ->count(),
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

        $client->delete();

        return redirect()->route('clients.index')->with('status', 'client-deleted');
    }

    /**
     * Ensure a client belongs to the authenticated user's company.
     */
    private function ensureClientBelongsToUserCompany(Request $request, Client $client): void
    {
        abort_unless($client->company_id === $request->user()->company_id, 404);
    }
}
