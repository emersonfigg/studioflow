<?php

namespace App\Http\Controllers;

use App\Enums\PaymentIntegrationEnvironment;
use App\Enums\PaymentProvider;
use App\Http\Requests\StoreCompanyPaymentIntegrationRequest;
use App\Http\Requests\UpdateCompanyPaymentIntegrationRequest;
use App\Models\CompanyPaymentIntegration;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyPaymentIntegrationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $companyId = (int) $request->user()->company_id;

        $integrations = CompanyPaymentIntegration::query()
            ->where('company_id', $companyId)
            ->orderByDesc('default_for_memberships')
            ->orderByDesc('active')
            ->orderBy('provider')
            ->get();

        return view('company.payment-integrations.index', [
            'integrations' => $integrations,
            'mercadoPagoIntegration' => $integrations->first(fn (CompanyPaymentIntegration $integration) => $integration->provider === PaymentProvider::MercadoPago),
            'mercadoPagoOauthConfigured' => filled(config('services.mercado_pago.client_id'))
                && filled(config('services.mercado_pago.client_secret'))
                && filled(config('services.mercado_pago.oauth_redirect_uri')),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('company.payment-integrations.create', [
            'providers' => PaymentProvider::cases(),
            'environments' => PaymentIntegrationEnvironment::cases(),
            'mercadoPagoOauthConfigured' => filled(config('services.mercado_pago.client_id'))
                && filled(config('services.mercado_pago.client_secret'))
                && filled(config('services.mercado_pago.oauth_redirect_uri')),
        ]);
    }

    public function store(StoreCompanyPaymentIntegrationRequest $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $data = $request->validated();
        $data['company_id'] = $companyId;
        $data['active'] = $request->boolean('active', true);
        $data['default_for_memberships'] = $request->boolean('default_for_memberships', false);

        if (($data['provider'] ?? null) === PaymentProvider::MercadoPago) {
            if (! empty($data['access_token']) && $data['active']) {
                $data['status'] = 'connected';
                $data['connected_at'] = now();
            } elseif (! $data['active']) {
                $data['status'] = 'disconnected';
            }
        }

        DB::transaction(function () use ($data, $companyId, $request): void {
            if ($request->boolean('default_for_memberships')) {
                CompanyPaymentIntegration::query()
                    ->where('company_id', $companyId)
                    ->update(['default_for_memberships' => false]);
            }

            CompanyPaymentIntegration::query()->create($data);
        });

        return redirect()->route('company.payment-integrations.index')
            ->with('status', 'payment-integration-created');
    }

    public function edit(Request $request, CompanyPaymentIntegration $integration): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureOwnCompany($request, $integration);

        return view('company.payment-integrations.edit', [
            'integration' => $integration,
            'providers' => PaymentProvider::cases(),
            'environments' => PaymentIntegrationEnvironment::cases(),
            'mercadoPagoOauthConfigured' => filled(config('services.mercado_pago.client_id'))
                && filled(config('services.mercado_pago.client_secret'))
                && filled(config('services.mercado_pago.oauth_redirect_uri')),
        ]);
    }

    public function update(UpdateCompanyPaymentIntegrationRequest $request, CompanyPaymentIntegration $integration): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureOwnCompany($request, $integration);

        $data = $request->validated();
        $data['active'] = $request->boolean('active', $integration->active);
        $data['default_for_memberships'] = $request->boolean('default_for_memberships', $integration->default_for_memberships);

        foreach (['api_key', 'access_token', 'refresh_token', 'public_key', 'webhook_secret'] as $secretField) {
            if (! array_key_exists($secretField, $data) || $data[$secretField] === null || $data[$secretField] === '') {
                unset($data[$secretField]);
            }
        }

        if (($data['provider'] ?? $integration->provider) === PaymentProvider::MercadoPago) {
            if (array_key_exists('access_token', $data) && ! empty($data['access_token']) && $data['active']) {
                $data['status'] = 'connected';
                $data['connected_at'] = now();
            } elseif (! $data['active']) {
                $data['status'] = 'disconnected';
            }
        }

        DB::transaction(function () use ($data, $integration, $request): void {
            if (! empty($data['default_for_memberships'])) {
                CompanyPaymentIntegration::query()
                    ->where('company_id', $request->user()->company_id)
                    ->where('id', '!=', $integration->id)
                    ->update(['default_for_memberships' => false]);
            }

            $integration->update($data);
        });

        return redirect()->route('company.payment-integrations.index')
            ->with('status', 'payment-integration-updated');
    }

    public function test(Request $request, CompanyPaymentIntegration $integration, PaymentGatewayManager $manager): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureOwnCompany($request, $integration);

        try {
            $manager->gatewayFor($integration)->ping();
        } catch (\Throwable $e) {
            return back()->withErrors(['test' => $e->getMessage()]);
        }

        return back()->with('status', 'payment-integration-tested');
    }

    public function toggle(Request $request, CompanyPaymentIntegration $integration): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->ensureOwnCompany($request, $integration);

        $integration->update(['active' => ! $integration->active]);

        return back()->with('status', 'payment-integration-toggled');
    }

    private function ensureOwnCompany(Request $request, CompanyPaymentIntegration $integration): void
    {
        abort_unless((int) $integration->company_id === (int) $request->user()->company_id, 404);
    }
}
