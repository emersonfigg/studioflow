<?php

namespace App\Models\Concerns;

use App\Models\CompanyPaymentIntegration;
use App\Models\PaymentWebhookEvent;
use DomainException;

trait ValidatesFinancialCompany
{
    protected static function bootValidatesFinancialCompany(): void
    {
        static::saving(function ($record) {
            $accountId = $record->payment_account_id;
            $clientId = $record->client_id;
            $membershipId = $record->customer_membership_id;
            foreach ($record->financialReferences() as $field => $model) {
                if ($record->getAttribute($field) === null) {
                    continue;
                }
                $related = $model::query()->find($record->getAttribute($field));
                if (! $related || (int) $related->company_id !== (int) $record->company_id) {
                    throw new DomainException('Referência financeira pertence a outra empresa ou não existe.');
                }
                if ($accountId && $related->payment_account_id && (int) $accountId !== (int) $related->payment_account_id) {
                    throw new DomainException('Referência pertence a outra conta financeira.');
                }
                if ($clientId && $related->client_id && (int) $clientId !== (int) $related->client_id) {
                    throw new DomainException('Referência pertence a outro cliente.');
                }
                if ($membershipId && $related->customer_membership_id && (int) $membershipId !== (int) $related->customer_membership_id) {
                    throw new DomainException('Referência pertence a outra assinatura.');
                }
                $quarantineOnly = $record instanceof PaymentWebhookEvent && $record->processing_status === 'quarantined';
                if ($model === CompanyPaymentIntegration::class && (! $related->account_verified_at || ! filled($related->external_account_id)) && ! $quarantineOnly) {
                    throw new DomainException('Conta financeira não verificada.');
                }
                $accountId ??= $related->payment_account_id;
                $clientId ??= $related->client_id;
                $membershipId ??= $related->customer_membership_id;
            }
        });
    }
}
