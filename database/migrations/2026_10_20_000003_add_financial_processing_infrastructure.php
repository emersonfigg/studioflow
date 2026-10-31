<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table) {
            $table->uuid('processing_token')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->index(['processing_status', 'lease_until'], 'financial_event_recovery_index');
        });
        Schema::table('membership_invoices', function (Blueprint $table) {
            $table->timestamp('gateway_observed_at')->nullable();
        });
        Schema::table('customer_memberships', function (Blueprint $table) {
            $table->string('suspension_reason', 64)->nullable();
            $table->string('gateway_subscription_status', 32)->nullable();
        });
        Schema::create('subscription_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_account_id')->constrained('company_payment_integrations')->restrictOnDelete();
            $table->foreignId('customer_membership_id')->constrained()->restrictOnDelete();
            $table->string('status', 32);
            $table->unsignedInteger('charges_checked')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->foreign(['company_id', 'payment_account_id'], 'reconciliation_account_scope_fk')->references(['company_id', 'id'])->on('company_payment_integrations')->restrictOnDelete();
            $table->foreign(['company_id', 'customer_membership_id'], 'reconciliation_membership_scope_fk')->references(['company_id', 'id'])->on('customer_memberships')->restrictOnDelete();
            $table->index(['payment_account_id', 'started_at']);
        });
    }

    public function down(): void
    {
        throw new LogicException('Reversão financeira exige migration compensatória revisada.');
    }
};
