<?php

return [
    // Production execution is additionally blocked in FinancialGatewayRegistry.
    'public_checkout_enabled' => env('FINANCIAL_PUBLIC_CHECKOUT_ENABLED', false),
    'asaas_v2_enabled' => env('FINANCIAL_ASAAS_V2_ENABLED', false),
    'reconciliation_enabled' => env('FINANCIAL_RECONCILIATION_ENABLED', false),
    'queue_connection' => 'database',
    'queue' => 'finance',
    'max_attempts' => 5,
    'lease_seconds' => 180,
];
