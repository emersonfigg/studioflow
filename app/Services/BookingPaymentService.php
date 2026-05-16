<?php

namespace App\Services;

use App\Enums\PaymentProvider;
use App\Models\Appointment;
use App\Models\BookingPayment;
use App\Models\Company;
use App\Models\CompanyPaymentIntegration;
use App\Services\Payments\Gateways\MercadoPagoGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BookingPaymentService
{
    public function onlinePaymentEnabled(Company $company): bool
    {
        return $company->onlineBookingPaymentEnabled();
    }

    public function paymentMode(Company $company): string
    {
        return (string) ($company->booking_payment_mode ?: 'none');
    }

    public function depositAmountFor(Company $company, float $totalAmount): float
    {
        return match ($this->paymentMode($company)) {
            'full' => round(max(0, (float) $totalAmount), 2),
            'deposit' => $this->calculateBookingDepositAmount($company, $totalAmount),
            default => 0.0,
        };
    }

    public function expiresAtFor(Company $company): Carbon
    {
        $minutes = max(1, (int) ($company->booking_payment_expiration_minutes ?: 15));

        return now()->addMinutes($minutes);
    }

    public function ensureCompanyCanAcceptOnlineBooking(Company $company, float $totalAmount): void
    {
        if (! $company->onlineBookingPaymentEnabled()) {
            throw new RuntimeException('O pagamento online para agendamento esta desativado nesta empresa.');
        }

        $this->mercadoPagoIntegrationForCompany($company);

        if (! $company->canOfferOnlineBookingPayment($totalAmount) || $this->depositAmountFor($company, $totalAmount) <= 0) {
            throw new RuntimeException('Configure um valor de sinal valido antes de ativar o pagamento online.');
        }
    }

    public function createCheckoutForAppointment(Company $company, Appointment $appointment): BookingPayment
    {
        $integration = $this->mercadoPagoIntegrationForCompany($company);
        $amount = $this->depositAmountFor($company, (float) $appointment->amount_total);
        $expiresAt = $this->expiresAtFor($company);

        if ($amount <= 0) {
            throw new RuntimeException('Configure um valor de sinal valido antes de ativar o pagamento online.');
        }

        /** @var BookingPayment $bookingPayment */
        $bookingPayment = DB::transaction(function () use ($company, $appointment, $amount, $expiresAt): BookingPayment {
            $existing = $appointment->bookingPayments()
                ->whereIn('status', [BookingPayment::STATUS_PENDING, BookingPayment::STATUS_FAILED])
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === BookingPayment::STATUS_PENDING && $existing->expires_at?->isFuture()) {
                return $existing;
            }

            $bookingPayment = BookingPayment::query()->create([
                'company_id' => $company->id,
                'appointment_id' => $appointment->id,
                'gateway' => PaymentProvider::MercadoPago->value,
                'status' => BookingPayment::STATUS_PENDING,
                'payment_type' => $this->paymentMode($company) === 'full'
                    ? BookingPayment::TYPE_FULL
                    : BookingPayment::TYPE_DEPOSIT,
                'amount' => $amount,
                'external_reference' => (string) Str::uuid(),
                'expires_at' => $expiresAt,
                'metadata' => [
                    'company_id' => (int) $company->id,
                    'appointment_id' => (int) $appointment->id,
                ],
            ]);

            $appointment->forceFill([
                'status' => 'pending_payment',
                'payment_status' => 'pending',
                'payment_gateway' => PaymentProvider::MercadoPago->value,
                'payment_reference' => $bookingPayment->external_reference,
                'amount_total' => round((float) $appointment->amount_total, 2),
                'amount_paid' => 0,
                'deposit_amount' => $amount,
                'payment_expires_at' => $expiresAt,
                'confirmed_at' => null,
            ])->save();

            return $bookingPayment;
        });

        if (! $bookingPayment->checkout_url) {
            try {
                $gateway = new MercadoPagoGateway($integration);
                $response = $gateway->createBookingPayment($company, $appointment->fresh(['company', 'client', 'service', 'services']), $bookingPayment);

                $bookingPayment->forceFill([
                    'preference_id' => $response['preference_id'] ?? null,
                    'checkout_url' => $response['checkout_url'] ?? $response['init_point'] ?? $response['sandbox_init_point'] ?? null,
                    'init_point' => $response['init_point'] ?? null,
                    'sandbox_init_point' => $response['sandbox_init_point'] ?? null,
                    'metadata' => array_merge($bookingPayment->metadata ?? [], [
                        'gateway_response' => array_filter([
                            'preference_id' => $response['preference_id'] ?? null,
                            'init_point' => $response['init_point'] ?? null,
                            'sandbox_init_point' => $response['sandbox_init_point'] ?? null,
                        ]),
                    ]),
                ])->save();

                $appointment->forceFill([
                    'payment_preference_id' => $bookingPayment->preference_id,
                ])->save();
            } catch (\Throwable $e) {
                $bookingPayment->forceFill([
                    'status' => BookingPayment::STATUS_FAILED,
                    'metadata' => array_merge($bookingPayment->metadata ?? [], [
                        'checkout_error' => $e->getMessage(),
                    ]),
                ])->save();

                $appointment->forceFill([
                    'payment_status' => 'failed',
                    'status' => 'cancelled',
                ])->save();

                throw $e;
            }
        }

        return $bookingPayment->fresh();
    }

    public function handleWebhookStatus(
        BookingPayment $bookingPayment,
        string $status,
        ?string $externalPaymentId = null,
        array $payload = [],
    ): void {
        match ($status) {
            'approved' => $this->markPaid($bookingPayment, $externalPaymentId, $payload),
            'pending', 'in_process' => $this->markPending($bookingPayment, $externalPaymentId, $payload),
            'rejected', 'cancelled' => $this->markFailed($bookingPayment, $externalPaymentId, $payload),
            'expired' => $this->markExpired($bookingPayment, $externalPaymentId, $payload),
            'refunded', 'charged_back' => $this->markRefunded($bookingPayment, $externalPaymentId, $payload),
            default => $this->markPending($bookingPayment, $externalPaymentId, $payload),
        };
    }

    public function expireUnpaidPayments(): int
    {
        $count = 0;

        BookingPayment::query()
            ->with(['appointment', 'company'])
            ->where('status', BookingPayment::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(50, function ($payments) use (&$count): void {
                foreach ($payments as $payment) {
                    $this->markExpired($payment, $payment->external_payment_id, $payment->metadata ?? []);
                    $count++;
                }
            });

        return $count;
    }

    public function calculateBookingDepositAmount(Company $company, float $totalAmount): float
    {
        $totalAmount = max(0, round($totalAmount, 2));
        $type = (string) ($company->booking_deposit_type ?: 'fixed');
        $value = (float) ($company->booking_deposit_value ?: 0);

        if ($type === 'percentage') {
            return round(min($totalAmount, max(0, $totalAmount * ($value / 100))), 2);
        }

        return round(min($totalAmount, max(0, $value)), 2);
    }

    private function mercadoPagoIntegrationForCompany(Company $company): CompanyPaymentIntegration
    {
        $integration = CompanyPaymentIntegration::query()
            ->where('company_id', $company->id)
            ->where('provider', PaymentProvider::MercadoPago)
            ->where('active', true)
            ->orderByDesc('id')
            ->first();

        if (! $integration) {
            throw new RuntimeException('Conecte a conta Mercado Pago da empresa antes de ativar o pagamento online no agendamento.');
        }

        return $integration;
    }

    private function markPaid(BookingPayment $bookingPayment, ?string $externalPaymentId, array $payload): void
    {
        DB::transaction(function () use ($bookingPayment, $externalPaymentId, $payload): void {
            $locked = BookingPayment::query()->whereKey($bookingPayment->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            $appointment = Appointment::query()->whereKey($locked->appointment_id)->lockForUpdate()->first();
            if (! $appointment) {
                return;
            }

            if ($locked->status === BookingPayment::STATUS_PAID && $appointment->payment_status === 'paid') {
                return;
            }

            $paidAt = now();

            $locked->forceFill([
                'status' => BookingPayment::STATUS_PAID,
                'external_payment_id' => $externalPaymentId ?: $locked->external_payment_id,
                'paid_at' => $paidAt,
                'metadata' => array_merge($locked->metadata ?? [], ['webhook' => $payload]),
            ])->save();

            $appointment->forceFill([
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'payment_external_id' => $externalPaymentId ?: $appointment->payment_external_id,
                'amount_paid' => (float) $locked->amount,
                'confirmed_at' => $appointment->confirmed_at ?? $paidAt,
            ])->save();
        });
    }

    private function markPending(BookingPayment $bookingPayment, ?string $externalPaymentId, array $payload): void
    {
        DB::transaction(function () use ($bookingPayment, $externalPaymentId, $payload): void {
            $locked = BookingPayment::query()->whereKey($bookingPayment->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            $locked->forceFill([
                'status' => BookingPayment::STATUS_PENDING,
                'external_payment_id' => $externalPaymentId ?: $locked->external_payment_id,
                'metadata' => array_merge($locked->metadata ?? [], ['webhook' => $payload]),
            ])->save();

            Appointment::query()->whereKey($locked->appointment_id)->update([
                'status' => 'pending_payment',
                'payment_status' => 'pending',
                'payment_external_id' => $externalPaymentId,
            ]);
        });
    }

    private function markFailed(BookingPayment $bookingPayment, ?string $externalPaymentId, array $payload): void
    {
        DB::transaction(function () use ($bookingPayment, $externalPaymentId, $payload): void {
            $locked = BookingPayment::query()->whereKey($bookingPayment->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            $appointment = Appointment::query()->whereKey($locked->appointment_id)->lockForUpdate()->first();
            if (! $appointment) {
                return;
            }

            $locked->forceFill([
                'status' => BookingPayment::STATUS_FAILED,
                'external_payment_id' => $externalPaymentId ?: $locked->external_payment_id,
                'metadata' => array_merge($locked->metadata ?? [], ['webhook' => $payload]),
            ])->save();

            $appointment->forceFill([
                'payment_status' => 'failed',
                'payment_external_id' => $externalPaymentId ?: $appointment->payment_external_id,
                'status' => $appointment->company?->booking_auto_cancel_unpaid ? 'cancelled' : 'pending_payment',
            ])->save();
        });
    }

    private function markExpired(BookingPayment $bookingPayment, ?string $externalPaymentId, array $payload): void
    {
        DB::transaction(function () use ($bookingPayment, $externalPaymentId, $payload): void {
            $locked = BookingPayment::query()->whereKey($bookingPayment->id)->lockForUpdate()->first();
            if (! $locked || $locked->status === BookingPayment::STATUS_PAID) {
                return;
            }

            $appointment = Appointment::query()->with('company')->whereKey($locked->appointment_id)->lockForUpdate()->first();
            if (! $appointment) {
                return;
            }

            $locked->forceFill([
                'status' => BookingPayment::STATUS_EXPIRED,
                'external_payment_id' => $externalPaymentId ?: $locked->external_payment_id,
                'metadata' => array_merge($locked->metadata ?? [], ['webhook' => $payload]),
            ])->save();

            $appointment->forceFill([
                'payment_status' => 'expired',
                'payment_external_id' => $externalPaymentId ?: $appointment->payment_external_id,
                'status' => $appointment->company && $appointment->company->booking_auto_cancel_unpaid ? 'cancelled' : $appointment->status,
            ])->save();
        });
    }

    private function markRefunded(BookingPayment $bookingPayment, ?string $externalPaymentId, array $payload): void
    {
        DB::transaction(function () use ($bookingPayment, $externalPaymentId, $payload): void {
            $locked = BookingPayment::query()->whereKey($bookingPayment->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            $appointment = Appointment::query()->whereKey($locked->appointment_id)->lockForUpdate()->first();
            if (! $appointment) {
                return;
            }

            $locked->forceFill([
                'status' => BookingPayment::STATUS_REFUNDED,
                'external_payment_id' => $externalPaymentId ?: $locked->external_payment_id,
                'metadata' => array_merge($locked->metadata ?? [], ['webhook' => $payload]),
            ])->save();

            $appointment->forceFill([
                'payment_status' => 'refunded',
                'payment_external_id' => $externalPaymentId ?: $appointment->payment_external_id,
                'status' => 'cancelled',
            ])->save();
        });
    }
}
