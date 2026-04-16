<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Display a listing of services for the authenticated user's company.
     */
    public function index(Request $request): View
    {
        $services = Service::query()
            ->where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->paginate(10);

        return view('services.index', [
            'services' => $services,
        ]);
    }

    /**
     * Show the form for creating a new service.
     */
    public function create(Request $request): View
    {
        abort_unless($request->user()->isAdmin() && $request->user()->company_id !== null, 403);

        return view('services.create');
    }

    /**
     * Store a newly created service.
     */
    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['active'] = $request->boolean('active');

        $service = Service::create([
            ...$data,
            'company_id' => $request->user()->company_id,
        ]);

        return redirect()->route('services.show', $service)->with('status', 'service-created');
    }

    /**
     * Display the specified service.
     */
    public function show(Request $request, Service $service): View
    {
        $this->ensureServiceBelongsToUserCompany($request, $service);

        return view('services.show', [
            'service' => $service,
        ]);
    }

    /**
     * Show the form for editing the specified service.
     */
    public function edit(Request $request, Service $service): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureServiceBelongsToUserCompany($request, $service);

        return view('services.edit', [
            'service' => $service,
        ]);
    }

    /**
     * Update the specified service.
     */
    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $this->ensureServiceBelongsToUserCompany($request, $service);

        $data = $request->validated();
        $data['active'] = $request->boolean('active');

        $service->update($data);

        return redirect()->route('services.show', $service)->with('status', 'service-updated');
    }

    /**
     * Remove the specified service.
     */
    public function destroy(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureServiceBelongsToUserCompany($request, $service);

        $service->delete();

        return redirect()->route('services.index')->with('status', 'service-deleted');
    }

    /**
     * Ensure a service belongs to the authenticated user's company.
     */
    private function ensureServiceBelongsToUserCompany(Request $request, Service $service): void
    {
        abort_unless($service->company_id === $request->user()->company_id, 404);
    }
}
