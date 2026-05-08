<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use App\Services\ServiceOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceOrderController extends Controller
{
    public function show(Request $request, Appointment $appointment, ServiceOrderService $orders): View
    {
        $this->ensureAppointmentAccess($request, $appointment);

        $order = $orders->ensureForAppointment($appointment);
        $companyId = $request->user()->company_id;

        return view('service-orders.show', [
            'appointment' => $appointment->load(['client', 'user', 'service', 'services', 'payment']),
            'order' => $order->load(['items.service', 'items.product', 'items.professional', 'client', 'professional']),
            'services' => Service::query()->where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'products' => Product::query()->where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'professionals' => User::query()->where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'paymentMethods' => Payment::paymentMethodOptions(),
        ]);
    }

    public function addService(Request $request, ServiceOrder $order, ServiceOrderService $orders): RedirectResponse
    {
        $this->ensureOrderAccess($request, $order);

        $data = $request->validate([
            'service_id' => ['required', Rule::exists('services', 'id')->where('company_id', $order->company_id)->where('active', true)],
            'professional_id' => ['nullable', Rule::exists('users', 'id')->where('company_id', $order->company_id)->where('active', true)],
        ]);

        $service = Service::query()->where('company_id', $order->company_id)->findOrFail($data['service_id']);
        $professional = isset($data['professional_id'])
            ? User::query()->where('company_id', $order->company_id)->findOrFail($data['professional_id'])
            : null;

        $orders->addService($order, $service, $professional);

        return back()->with('status', 'order-service-added');
    }

    public function addProduct(Request $request, ServiceOrder $order, ServiceOrderService $orders): RedirectResponse
    {
        $this->ensureOrderAccess($request, $order);

        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $order->company_id)->where('active', true)],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::query()->where('company_id', $order->company_id)->findOrFail($data['product_id']);
        $orders->addProduct($order, $product, (int) $data['quantity']);

        return back()->with('status', 'order-product-added');
    }

    public function removeItem(Request $request, ServiceOrder $order, ServiceOrderItem $item, ServiceOrderService $orders): RedirectResponse
    {
        $this->ensureOrderAccess($request, $order);
        abort_unless($item->service_order_id === $order->id, 404);

        $orders->removeItem($item);

        return back()->with('status', 'order-item-removed');
    }

    public function close(Request $request, ServiceOrder $order, ServiceOrderService $orders): RedirectResponse
    {
        $this->ensureOrderAccess($request, $order);

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(Payment::PAYMENT_METHODS)],
            'notes' => ['nullable', 'string'],
        ]);

        $orders->close($order, $request->user(), $data['payment_method'], $data['notes'] ?? null);

        return redirect()
            ->route('appointments.show', $order->appointment_id)
            ->with('status', 'order-paid');
    }

    private function ensureAppointmentAccess(Request $request, Appointment $appointment): void
    {
        abort_unless($appointment->company_id === $request->user()->company_id, 404);

        if (! $request->user()->isAdmin()) {
            abort_unless($appointment->user_id === $request->user()->id, 403);
        }
    }

    private function ensureOrderAccess(Request $request, ServiceOrder $order): void
    {
        abort_unless($order->company_id === $request->user()->company_id, 404);

        if (! $request->user()->isAdmin()) {
            abort_unless($order->professional_id === $request->user()->id, 403);
        }
    }
}
