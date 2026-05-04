<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use App\Support\MediaStorage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    /**
     * Display a listing of services for the authenticated user's company.
     */
    public function index(Request $request): View
    {
        $baseQuery = Service::query()
            ->where('company_id', $request->user()->company_id);

        $services = (clone $baseQuery)
            ->orderBy('name')
            ->paginate(10);

        return view('services.index', [
            'services' => $services,
            'activeServicesCount' => (clone $baseQuery)->where('active', true)->count(),
            'averageTicket' => (float) ((clone $baseQuery)->avg('price') ?? 0),
            'averageDuration' => (float) ((clone $baseQuery)->avg('duration_minutes') ?? 0),
        ]);
    }

    /**
     * Show the form for creating a new service.
     */
    public function create(Request $request): View
    {
        abort_unless($request->user()->isAdmin() && $request->user()->company_id !== null, 403);

        return view('services.create', [
            'libraryImages' => $this->serviceLibraryImages(),
        ]);
    }

    /**
     * Store a newly created service.
     */
    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['active'] = $request->boolean('active');
        $image = $request->file('image') ?? ($data['image'] ?? null);
        $libraryImage = $data['library_image'] ?? null;
        unset($data['image']);
        unset($data['library_image']);

        if ($image) {
            $data['image_path'] = MediaStorage::putFile('services', $image);
        } elseif ($libraryImage) {
            $data['image_path'] = $this->copyLibraryImageToServices($libraryImage);
        }

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
        $monthStart = CarbonImmutable::now()->startOfMonth();
        $monthEnd = CarbonImmutable::now()->endOfMonth();

        $monthlyAppointmentsCount = $service->appointments()
            ->whereBetween('start_time', [$monthStart, $monthEnd])
            ->count();

        $monthlyRevenue = (float) ($service->payments()
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('gross_amount') ?? 0);

        return view('services.show', [
            'service' => $service,
            'monthlyAppointmentsCount' => $monthlyAppointmentsCount,
            'monthlyRevenue' => $monthlyRevenue,
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
            'libraryImages' => $this->serviceLibraryImages(),
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
        $image = $request->file('image') ?? ($data['image'] ?? null);
        $libraryImage = $data['library_image'] ?? null;
        unset($data['image']);
        unset($data['library_image']);

        if ($image) {
            $newPath = MediaStorage::putFile('services', $image);
        } elseif ($libraryImage) {
            $newPath = $this->copyLibraryImageToServices($libraryImage);
        }

        if (isset($newPath)) {
            if ($service->image_path) {
                MediaStorage::delete($service->normalizedImagePath() ?? $service->image_path);
            }

            $data['image_path'] = $newPath;
        }

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

        if ($service->image_path) {
            MediaStorage::delete($service->normalizedImagePath() ?? $service->image_path);
        }

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

    /**
     * Get the built-in image library available for services.
     *
     * @return list<array{path: string, url: string, label: string}>
     */
    private function serviceLibraryImages(): array
    {
        return collect(Storage::disk('public')->files('service-library/services'))
            ->filter(function (string $path): bool {
                return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'svg'], true);
            })
            ->sort()
            ->map(function (string $path): array {
                return [
                    'path' => $path,
                    'url' => MediaStorage::url($path) ?? Storage::disk('public')->url($path),
                    'label' => Str::of(pathinfo($path, PATHINFO_FILENAME))
                        ->replace(['-', '_'], ' ')
                        ->title()
                        ->toString(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Copy a library image into the service image storage.
     */
    private function copyLibraryImageToServices(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = Str::slug(pathinfo($path, PATHINFO_FILENAME));
        $destination = 'services/'.$filename.'-'.Str::lower(Str::random(10)).'.'.$extension;

        MediaStorage::put($destination, Storage::disk('public')->get($path));

        return $destination;
    }
}
