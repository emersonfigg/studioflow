<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdateAppointmentPaymentMethodRequest;
use App\Models\Appointment;
use App\Models\CashMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSale;
use App\Services\PaymentService;
use App\Services\ServiceOrderService;
use App\Support\BrazilianCurrency;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Show the payment form for the appointment conclusion flow.
     */
    public function create(Request $request, Appointment $appointment, ServiceOrderService $serviceOrders): View|RedirectResponse
    {
        $this->ensurePaymentAccess($request, $appointment);

        abort_if($appointment->payment()->exists(), 422, 'Este atendimento ja possui pagamento registrado.');
        abort_if($appointment->status === 'cancelled', 422, 'Nao e possivel registrar pagamento para atendimento cancelado.');

        // Fluxo principal de fechamento: PDV com agendamento carregado.
        if ($appointment->status !== 'completed') {
            return redirect()->route('pdv.index', ['appointment_id' => $appointment->id]);
        }

        $order = $serviceOrders->ensureForAppointment($appointment);

        return view('payments.create', [
            'appointment' => $appointment->load(['client', 'service', 'services', 'user']),
            'order' => $order,
            'products' => Product::query()
                ->where('company_id', $request->user()->company_id)
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'paymentMethods' => Payment::paymentMethodOptions(),
            'defaultGrossAmount' => BrazilianCurrency::input((float) $order->subtotal_services),
        ]);
    }

    /**
     * Store a newly created payment.
     */
    public function store(StorePaymentRequest $request, Appointment $appointment, PaymentService $paymentService): RedirectResponse
    {
        $this->ensurePaymentAccess($request, $appointment);

        $paymentService->register($appointment->loadMissing(['user', 'service', 'services', 'client']), $request->user(), $request->validated());

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('status', 'payment-created');
    }

    /**
     * Adjust payment method after the service is completed (amounts stay the same).
     */
    public function updatePaymentMethod(UpdateAppointmentPaymentMethodRequest $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($appointment->company_id === $request->user()->company_id, 404);
        abort_unless($appointment->status === 'completed', 422);

        $payment = $appointment->payment;
        abort_if($payment === null, 422);

        $newMethod = $request->validated('payment_method');

        DB::transaction(function () use ($appointment, $payment, $newMethod, $request): void {
            $payment->refresh();
            $oldMethod = (string) $payment->payment_method;

            if ($oldMethod === $newMethod) {
                $payment->touch();

                return;
            }

            $suffix = sprintf(
                "\n[Correcao forma pagamento] %s por %s (id %s): %s -> %s",
                now()->format('d/m/Y H:i'),
                $request->user()->name,
                $request->user()->id,
                Payment::labelForPaymentMethod($oldMethod),
                Payment::labelForPaymentMethod($newMethod),
            );

            $payment->update([
                'payment_method' => $newMethod,
                'notes' => trim((string) ($payment->notes ?? '').$suffix),
            ]);

            CashMovement::query()
                ->where('company_id', $payment->company_id)
                ->where('source_type', Payment::class)
                ->where('source_id', $payment->id)
                ->update([
                    'payment_method' => $newMethod,
                    'updated_at' => now(),
                ]);

            $saleIds = ProductSale::query()
                ->where('company_id', $appointment->company_id)
                ->where(function ($query) use ($appointment, $payment): void {
                    $query->where('appointment_id', $appointment->id);

                    if ($payment->service_order_id) {
                        $query->orWhere('service_order_id', $payment->service_order_id);
                    }
                })
                ->pluck('id');

            if ($saleIds->isEmpty()) {
                return;
            }

            ProductSale::query()
                ->whereIn('id', $saleIds->all())
                ->update([
                    'payment_method' => $newMethod,
                    'updated_at' => now(),
                ]);

            CashMovement::query()
                ->where('company_id', $payment->company_id)
                ->where('source_type', ProductSale::class)
                ->whereIn('source_id', $saleIds->all())
                ->update([
                    'payment_method' => $newMethod,
                    'updated_at' => now(),
                ]);
        });

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('status', 'payment-method-updated');
    }

    /**
     * Ensure payment access is scoped to company and role.
     */
    private function ensurePaymentAccess(Request $request, Appointment $appointment): void
    {
        abort_unless($appointment->company_id === $request->user()->company_id, 404);

        if (! $request->user()->isAdmin()) {
            abort_unless($appointment->user_id === $request->user()->id, 403);
        }
    }
}
