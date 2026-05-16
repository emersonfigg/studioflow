<?php

namespace App\Http\Controllers;

use App\Enums\PaymentProvider;
use App\Models\BookingPayment;
use App\Models\CompanyPaymentIntegration;
use App\Services\BookingPaymentService;
use App\Services\MembershipBillingService;
use App\Services\Payments\AsaasWebhookParser;
use App\Services\Payments\Gateways\MercadoPagoGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class CompanyPaymentWebhookController extends Controller
{
    public function asaas(Request $request, MembershipBillingService $billing): Response
    {
        try {
            $parsed = AsaasWebhookParser::parse($request->all());
            $billing->handleAsaasWebhook($parsed, $request->header('asaas-access-token'));

            return response('OK', 200);
        } catch (Throwable $e) {
            Log::error('asaas.webhook.error', ['message' => $e->getMessage(), 'payload' => $request->all()]);

            return response('Bad Request', 400);
        }
    }

    public function galaxyPay(Request $request): Response
    {
        Log::info('galaxy_pay.webhook.received', ['payload' => $request->all()]);

        return response('OK', 200);
    }

    public function mercadoPago(Request $request, BookingPaymentService $bookingPaymentService): Response
    {
        $payload = $request->all();

        Log::info('mercado_pago.webhook.received', [
            'payload' => $payload,
            'payment_context' => $request->query('payment_context'),
            'company_id' => $request->query('company_id'),
        ]);

        if ($request->query('payment_context') !== 'booking') {
            return response('OK', 200);
        }

        $companyId = (int) $request->query('company_id');
        $paymentId = (string) data_get($payload, 'data.id', data_get($payload, 'id', ''));
        $externalReference = (string) $request->query('external_reference', '');

        if ($companyId < 1 || $paymentId === '' || $externalReference === '') {
            Log::warning('mercado_pago.webhook.invalid_booking_context', [
                'company_id' => $companyId,
                'payment_id' => $paymentId,
                'external_reference' => $externalReference,
            ]);

            return response('OK', 200);
        }

        $integration = CompanyPaymentIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', PaymentProvider::MercadoPago)
            ->where('active', true)
            ->orderByDesc('id')
            ->first();

        if (! $integration) {
            Log::warning('mercado_pago.webhook.integration_missing', [
                'company_id' => $companyId,
                'external_reference' => $externalReference,
            ]);

            return response('OK', 200);
        }

        $bookingPayment = BookingPayment::query()
            ->with(['appointment.company'])
            ->where('company_id', $companyId)
            ->where('external_reference', $externalReference)
            ->first();

        if (! $bookingPayment) {
            Log::warning('mercado_pago.webhook.booking_payment_missing', [
                'company_id' => $companyId,
                'external_reference' => $externalReference,
            ]);

            return response('OK', 200);
        }

        try {
            $gateway = new MercadoPagoGateway($integration);
            $details = $gateway->fetchPaymentDetails($paymentId);
            $status = (string) ($details['status'] ?? 'pending');
            $resolvedReference = (string) ($details['external_reference'] ?? $externalReference);

            if ($resolvedReference !== $bookingPayment->external_reference) {
                $bookingPayment = BookingPayment::query()
                    ->with(['appointment.company'])
                    ->where('company_id', $companyId)
                    ->where('external_reference', $resolvedReference)
                    ->first() ?? $bookingPayment;
            }

            if ($resolvedReference !== $bookingPayment->external_reference) {
                Log::warning('mercado_pago.webhook.reference_mismatch', [
                    'company_id' => $companyId,
                    'expected' => $externalReference,
                    'resolved' => $resolvedReference,
                    'payment_id' => $paymentId,
                ]);

                return response('OK', 200);
            }

            $bookingPaymentService->handleWebhookStatus(
                $bookingPayment,
                $status,
                $paymentId,
                [
                    'webhook' => $payload,
                    'details' => array_intersect_key($details, array_flip([
                        'id',
                        'status',
                        'status_detail',
                        'external_reference',
                        'date_approved',
                        'transaction_amount',
                    ])),
                ],
            );
        } catch (Throwable $e) {
            Log::error('mercado_pago.webhook.booking_error', [
                'message' => $e->getMessage(),
                'company_id' => $companyId,
                'external_reference' => $externalReference,
                'payment_id' => $paymentId,
            ]);
        }

        return response('OK', 200);
    }
}
