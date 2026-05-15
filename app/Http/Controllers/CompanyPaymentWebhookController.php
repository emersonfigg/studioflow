<?php

namespace App\Http\Controllers;

use App\Services\MembershipBillingService;
use App\Services\Payments\AsaasWebhookParser;
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

    public function mercadoPago(Request $request): Response
    {
        Log::info('mercado_pago.webhook.received', ['payload' => $request->all()]);

        return response('OK', 200);
    }
}
