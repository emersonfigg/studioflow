<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProductConsumption;
use App\Support\MediaStorage;
use App\Support\ServiceImageLibrary;
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
            'products' => Product::query()
                ->where('company_id', $request->user()->company_id)
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'consumptionRows' => old('consumptions', $this->consumptionFormRows(null)),
        ]);
    }

    /**
     * Store a newly created service.
     */
    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $consumptions = $data['consumptions'] ?? [];
        unset($data['consumptions']);
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

        $this->syncProductConsumptions($service, $consumptions);

        return redirect()->route('services.show', $service)->with('status', 'service-created');
    }

    /**
     * Display the specified service.
     */
    public function show(Request $request, Service $service): View
    {
        $this->ensureServiceBelongsToUserCompany($request, $service);
        $service->load(['productConsumptions.product']);
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
            'products' => Product::query()
                ->where('company_id', $request->user()->company_id)
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'consumptionRows' => old('consumptions', $this->consumptionFormRows($service)),
        ]);
    }

    /**
     * Update the specified service.
     */
    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $this->ensureServiceBelongsToUserCompany($request, $service);

        $data = $request->validated();
        $consumptions = $data['consumptions'] ?? [];
        unset($data['consumptions']);
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

        $this->syncProductConsumptions($service->fresh(), $consumptions);

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
     * @return list<array{product_id: mixed, quantity: string, unit: string, active: bool}>
     */
    private function consumptionFormRows(?Service $service): array
    {
        if ($service === null) {
            return array_fill(0, 5, [
                'product_id' => '',
                'quantity' => '1',
                'unit' => '',
                'active' => true,
            ]);
        }

        $service->loadMissing('productConsumptions');

        $rows = $service->productConsumptions
            ->map(fn (ServiceProductConsumption $row): array => [
                'product_id' => $row->product_id,
                'quantity' => (string) $row->quantity,
                'unit' => (string) ($row->unit ?? ''),
                'active' => (bool) $row->active,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return $this->consumptionFormRows(null);
        }

        while (count($rows) < 5) {
            $rows[] = ['product_id' => '', 'quantity' => '1', 'unit' => '', 'active' => true];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncProductConsumptions(Service $service, array $rows): void
    {
        $companyId = (int) $service->company_id;

        ServiceProductConsumption::query()->where('service_id', $service->id)->delete();

        foreach (collect($rows)->filter(fn (mixed $row): bool => is_array($row) && ! empty($row['product_id'])) as $row) {
            $productId = (int) $row['product_id'];

            abort_unless(
                Product::query()->where('company_id', $companyId)->whereKey($productId)->exists(),
                422,
            );

            ServiceProductConsumption::create([
                'company_id' => $companyId,
                'service_id' => $service->id,
                'product_id' => $productId,
                'quantity' => round(max(0.01, (float) ($row['quantity'] ?? 1)), 2),
                'unit' => isset($row['unit']) && $row['unit'] !== '' ? (string) $row['unit'] : null,
                'active' => ((int) ($row['active'] ?? 1)) === 1,
            ]);
        }
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
        return collect(ServiceImageLibrary::relativePaths())
            ->map(function (string $path): array {
                $fallbackUrl = null;
                try {
                    if (Storage::disk('public')->exists($path)) {
                        $fallbackUrl = Storage::disk('public')->url($path);
                    }
                } catch (\Throwable) {
                    $fallbackUrl = null;
                }

                return [
                    'path' => $path,
                    'url' => ServiceImageLibrary::publicWebUrl($path)
                        ?? MediaStorage::url($path)
                        ?? $fallbackUrl,
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

        MediaStorage::put($destination, ServiceImageLibrary::getContents($path));

        return $destination;
    }
}
