<?php

namespace App\Services\Payments;

use DomainException;
use Throwable;

final class FinancialErrorCode
{
    public static function fromException(Throwable $error): string
    {
        $allowed = ['financial_execution_disabled', 'unsupported_event', 'untrusted_gateway_snapshot', 'uncorrelated_subscription',
            'customer_mismatch', 'amount_mismatch', 'missing_financial_dates', 'invalid_financial_dates', 'stale_processing_lease',
            'subscription_binding_changed', 'invoice_binding_mismatch', 'invoice_coverage_changed', 'subscription_snapshot_mismatch',
            'checkout_binding_mismatch', 'membership_account_unavailable', 'invalid_reconciliation_cursor', 'resource_mismatch', 'legacy_charge_mismatch', 'unsupported_legacy_status', 'missing_confirmation_date'];

        return $error instanceof DomainException
            ? (in_array($error->getMessage(), $allowed, true) ? $error->getMessage() : 'capability_unavailable')
            : 'gateway_unavailable';
    }
}
