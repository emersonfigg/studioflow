<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Display a listing of clients for the authenticated user's company.
     */
    public function index(Request $request): View
    {
        $clients = Client::query()
            ->where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->paginate(10);

        return view('clients.index', [
            'clients' => $clients,
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
     * Display the specified client.
     */
    public function show(Request $request, Client $client): View
    {
        $this->ensureClientBelongsToUserCompany($request, $client);

        return view('clients.show', [
            'client' => $client,
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
