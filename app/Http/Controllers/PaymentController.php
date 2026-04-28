<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Appointment;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Show the payment form for the appointment conclusion flow.
     */
    public function create(Request $request, Appointment $appointment): View
    {
        $this->ensurePaymentAccess($request, $appointment);

        abort_if($appointment->payment()->exists(), 422, 'Este atendimento ja possui pagamento registrado.');
        abort_if($appointment->status === 'cancelled', 422, 'Nao e possivel registrar pagamento para atendimento cancelado.');

        return view('payments.create', [
            'appointment' => $appointment->load(['client', 'service', 'user']),
            'paymentMethods' => [
                'cash' => 'Dinheiro',
                'pix' => 'Pix',
                'card' => 'Cartao',
            ],
            'defaultGrossAmount' => number_format((float) $appointment->service->price, 2, '.', ''),
        ]);
    }

    /**
     * Store a newly created payment.
     */
    public function store(StorePaymentRequest $request, Appointment $appointment, PaymentService $paymentService): RedirectResponse
    {
        $this->ensurePaymentAccess($request, $appointment);

        $paymentService->register($appointment->loadMissing(['user', 'service']), $request->validated());

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('status', 'payment-created');
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
