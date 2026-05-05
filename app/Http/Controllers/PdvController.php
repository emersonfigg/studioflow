<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePdvSaleRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\ProductSaleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PdvController extends Controller
{
    /**
     * Display the POS quick sale screen.
     */
    public function index(Request $request, CashRegisterService $cashRegisterService): View
    {
        $companyId = $request->user()->company_id;

        $pdvAppointment = null;
        if ($request->filled('appointment_id')) {
            $pdvAppointment = Appointment::query()
                ->where('company_id', $companyId)
                ->whereKey($request->integer('appointment_id'))
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->with(['client', 'service', 'services', 'user'])
                ->first();
        }

        $openOrderForAppointment = null;
        if ($pdvAppointment) {
            $openOrderForAppointment = ServiceOrder::query()
                ->where('company_id', $companyId)
                ->where('appointment_id', $pdvAppointment->id)
                ->where('status', ServiceOrder::STATUS_OPEN)
                ->with(['items.service', 'items.product'])
                ->first();
        }

        $initialCart = $this->buildInitialCart($pdvAppointment, $openOrderForAppointment);

        if ($pdvAppointment !== null && $initialCart === []) {
            $pdvAppointment->loadMissing(['service', 'services']);
            $initialCart = $this->markAppointmentServiceRows(
                $this->appointmentToPdvCart($pdvAppointment),
                $pdvAppointment
            );
        }

        $appointmentSummary = null;
        if ($pdvAppointment) {
            $pdvAppointment->loadMissing(['service', 'services']);
            $servicesTotal = $pdvAppointment->totalPriceAmount();
            $professional = $pdvAppointment->user;
            $commissionReference = null;
            if ($professional && filled($professional->commission_type)) {
                $rate = (float) ($professional->commission_value ?? 0);
                $commissionReference = match ($professional->commission_type) {
                    'percent' => 'Comissao (referencia): '.number_format($rate, 2, ',', '.').'% sobre subtotal de servicos',
                    'fixed' => 'Comissao (referencia): R$ '.number_format($rate, 2, ',', '.').' fixa por atendimento',
                    default => null,
                };
            }

            $appointmentSummary = [
                'id' => $pdvAppointment->id,
                'client_name' => $pdvAppointment->client?->name,
                'professional_name' => $professional?->name,
                'start_time' => $pdvAppointment->start_time?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                'end_time' => $pdvAppointment->end_time?->timezone(config('app.timezone'))->format('H:i'),
                'services_total' => $servicesTotal,
                'services_total_formatted' => number_format($servicesTotal, 2, ',', '.'),
                'service_labels' => $pdvAppointment->bookedServices()->map(fn (Service $s): string => $s->name)->values()->all(),
                'commission_reference' => $commissionReference,
            ];
        }

        $cashRegister = $cashRegisterService->registerForDate($companyId, now());
        $clients = Client::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'phone']);
        $products = Product::query()->where('company_id', $companyId)->where('active', true)->orderBy('name')->get([
            'id', 'name', 'sku', 'price', 'stock_quantity', 'image_path',
        ]);
        $services = Service::query()->where('company_id', $companyId)->where('active', true)->orderBy('name')->get([
            'id', 'name', 'price', 'duration_minutes', 'image_path',
        ]);
        $professionals = User::query()->where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name']);
        $catalog = [
            'products' => $products->map(function ($product) {
                $code = $product->sku ? (string) $product->sku : 'P'.$product->id;

                return [
                    'id' => $product->id,
                    'code' => $code,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'stock' => $product->stock_quantity,
                    'type' => 'product',
                    'image_url' => $product->image_url,
                ];
            })->values(),
            'services' => $services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'code' => 'S'.$service->id,
                    'sku' => null,
                    'name' => $service->name,
                    'price' => (float) $service->price,
                    'duration' => $service->duration_minutes,
                    'type' => 'service',
                    'image_url' => $service->image_url,
                ];
            })->values(),
        ];

        return view('pdv.index', [
            'catalog' => $catalog,
            'clients' => $clients,
            'products' => $products,
            'services' => $services,
            'professionals' => $professionals,
            'pdvAppointment' => $pdvAppointment,
            'appointmentSummary' => $appointmentSummary,
            'initialCart' => $initialCart,
            'paymentMethods' => collect(Payment::PAYMENT_METHODS)->mapWithKeys(fn (string $method): array => [$method => match ($method) {
                'cash' => 'Dinheiro',
                'pix' => 'Pix',
                'card' => 'Cartao',
                default => ucfirst($method),
            }])->all(),
            'cashRegister' => $cashRegister,
        ]);
    }

    /**
     * Printable receipt for a closed POS / service order (comanda).
     */
    public function receipt(Request $request, ServiceOrder $serviceOrder): View
    {
        abort_unless($serviceOrder->company_id === $request->user()->company_id, 403);
        abort_unless($serviceOrder->status === ServiceOrder::STATUS_PAID, 404);

        $order = $serviceOrder->loadMissing([
            'company',
            'client',
            'professional',
            'appointment',
            'items.service',
            'items.product',
            'payment',
            'productSale',
        ]);

        return view('pdv.receipt', ['order' => $order]);
    }

    /**
     * Store a POS standalone sale using existing service order logic.
     */
    public function store(StorePdvSaleRequest $request, ProductSaleService $productSaleService): RedirectResponse
    {
        $order = $productSaleService->registerStandaloneOrder($request->user(), $request->payload());

        $paymentMethod = (string) $request->input('payment_method');
        $paymentLabel = match ($paymentMethod) {
            'cash' => 'Dinheiro',
            'pix' => 'Pix',
            'card' => 'Cartao',
            default => ucfirst($paymentMethod),
        };

        $appointmentCompleted = $order->appointment_id !== null;
        $company = $request->user()->company;

        return redirect()
            ->route('pdv.index')
            ->with('pdv_sale_result', [
                'service_order_id' => $order->id,
                'total' => number_format((float) $order->total, 2, ',', '.'),
                'total_raw' => (string) $order->total,
                'payment_method' => $paymentMethod,
                'payment_label' => $paymentLabel,
                'notes' => $request->input('notes'),
                'receipt_url' => route('pdv.receipt', $order, absolute: true),
                'appointment_completed' => $appointmentCompleted,
                'appointment_id' => $order->appointment_id,
                'auto_print_receipt' => (bool) ($company?->auto_print_receipt ?? false),
            ]);
    }

    /**
     * Hydrate PDV cart from an open comanda and/or the appointment's booked services.
     *
     * If an open order exists but has no service lines (only products or empty), services from the
     * appointment are merged in so the operator always sees the booked services without duplication.
     *
     * @return list<array<string, mixed>>
     */
    private function buildInitialCart(?Appointment $appointment, ?ServiceOrder $openOrder): array
    {
        if (! $appointment) {
            return $openOrder ? $this->orderToPdvCart($openOrder) : [];
        }

        $appointment->loadMissing(['service', 'services']);
        $fromOrder = $openOrder ? $this->orderToPdvCart($openOrder) : [];
        $fromAppointment = $this->appointmentToPdvCart($appointment);

        $orderServiceIds = collect($fromOrder)
            ->filter(fn (array $row): bool => ($row['type'] ?? '') === 'service')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($orderServiceIds !== []) {
            return $this->markAppointmentServiceRows($fromOrder, $appointment);
        }

        $merged = $fromOrder;
        foreach ($fromAppointment as $row) {
            if (($row['type'] ?? '') === 'service' && in_array((int) $row['id'], $orderServiceIds, true)) {
                continue;
            }
            $merged[] = $row;
        }

        return $this->markAppointmentServiceRows($merged, $appointment);
    }

    /**
     * Tag rows that correspond to booked appointment services (fixed lines in the PDV).
     *
     * @param  list<array<string, mixed>>  $cart
     * @return list<array<string, mixed>>
     */
    private function markAppointmentServiceRows(array $cart, Appointment $appointment): array
    {
        $bookedIds = $appointment->bookedServices()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return array_map(function (array $row) use ($bookedIds): array {
            if (($row['type'] ?? '') === 'service' && in_array((int) ($row['id'] ?? 0), $bookedIds, true)) {
                $row['source'] = 'appointment';
            }

            return $row;
        }, $cart);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function appointmentToPdvCart(Appointment $appointment): array
    {
        $appointment->loadMissing(['service', 'services']);
        $cart = [];
        foreach ($appointment->bookedServices() as $service) {
            $price = (float) ($service->pivot->price_snapshot ?? $service->price);
            $qty = 1;
            $code = 'S'.$service->id;
            $cart[] = [
                'id' => $service->id,
                'service_id' => $service->id,
                'type' => 'service',
                'code' => $code,
                'name' => $service->name,
                'price' => $price,
                'quantity' => $qty,
                'total' => round($price * $qty, 2),
                'source' => 'appointment',
                'image_url' => $service->image_url,
            ];
        }

        return $cart;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orderToPdvCart(ServiceOrder $order): array
    {
        $order->loadMissing(['items.service', 'items.product']);
        $cart = [];
        foreach ($order->items as $line) {
            if ($line->type === ServiceOrderItem::TYPE_SERVICE && $line->service_id) {
                $s = $line->service;
                $qty = max(1, (int) $line->quantity);
                $unit = (float) $line->unit_price;
                $cart[] = [
                    'id' => (int) $line->service_id,
                    'service_id' => (int) $line->service_id,
                    'type' => 'service',
                    'code' => 'S'.(int) $line->service_id,
                    'name' => (string) $line->description,
                    'price' => $unit,
                    'quantity' => $qty,
                    'total' => round($unit * $qty, 2),
                    'image_url' => $s?->image_url,
                ];
            } elseif ($line->type === ServiceOrderItem::TYPE_PRODUCT && $line->product_id) {
                $p = $line->product;
                $qty = max(1, (int) $line->quantity);
                $unit = (float) $line->unit_price;
                $cart[] = [
                    'id' => (int) $line->product_id,
                    'type' => 'product',
                    'code' => $p ? ($p->sku ? (string) $p->sku : 'P'.$p->id) : 'P'.$line->product_id,
                    'name' => (string) $line->description,
                    'price' => $unit,
                    'quantity' => $qty,
                    'total' => round($unit * $qty, 2),
                    'image_url' => $p?->image_url,
                ];
            }
        }

        return $cart;
    }
}
