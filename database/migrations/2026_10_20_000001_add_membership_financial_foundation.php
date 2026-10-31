<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Globally unique IDs make these scoped indexes safe for existing rows.
        foreach (['company_payment_integrations', 'customer_memberships', 'clients'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->unique(['company_id', 'id'], $name.'_company_id_unique');
            });
        }
        Schema::table('company_payment_integrations', function (Blueprint $table) {
            $table->string('external_account_id')->nullable();
            $table->timestamp('account_verified_at')->nullable();
            $table->string('connection_type', 32)->nullable();
            $table->string('account_enablement_status', 32)->nullable();
            $table->string('external_webhook_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
        });
        Schema::table('customer_memberships', function (Blueprint $table) {
            $table->foreignId('payment_account_id')->nullable()->constrained('company_payment_integrations')->restrictOnDelete();
            $table->string('external_subscription_id')->nullable();
            $table->string('external_checkout_id')->nullable();
            $table->string('billing_mode', 32)->nullable();
            $table->string('origin', 32)->nullable();
            $table->string('legacy_classification', 32)->nullable();
            $table->timestamp('legacy_classified_at')->nullable();
            $table->json('legacy_evidence')->nullable();
            $table->json('commercial_snapshot')->nullable();
            $table->string('terms_version')->nullable();
            $table->string('financial_status', 32)->nullable();
            $table->timestamp('paid_through')->nullable();
            $table->timestamp('grace_until')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('effective_ends_at')->nullable();
            $table->unique(['payment_account_id', 'external_subscription_id'], 'memberships_account_subscription_unique');
        });
        Schema::create('payment_account_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_account_id')->constrained('company_payment_integrations')->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('external_customer_id');
            $table->timestamps();
            $table->unique(['payment_account_id', 'client_id'], 'account_client_unique');
            $table->unique(['payment_account_id', 'external_customer_id'], 'account_external_customer_unique');
        });
        Schema::create('membership_checkout_intentions', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_account_id')->constrained('company_payment_integrations')->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_membership_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('commercial_snapshot');
            $table->string('terms_version');
            $table->timestamp('accepted_terms_at');
            $table->string('status', 32)->default('pending');
            $table->string('external_checkout_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['payment_account_id', 'external_checkout_id'], 'intentions_account_checkout_unique');
        });
        Schema::create('membership_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_account_id')->constrained('company_payment_integrations')->restrictOnDelete();
            $table->foreignId('customer_membership_id')->constrained()->restrictOnDelete();
            $table->string('external_invoice_id');
            $table->string('currency', 3)->default('BRL');
            $table->decimal('amount', 12, 2);
            $table->decimal('net_amount', 12, 2)->nullable();
            $table->decimal('fee_amount', 12, 2)->nullable();
            $table->string('status', 32);
            $table->date('due_date');
            $table->timestamp('coverage_starts_at')->nullable();
            $table->timestamp('coverage_ends_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['payment_account_id', 'external_invoice_id'], 'invoices_account_external_unique');
            $table->index(['payment_account_id', 'status', 'due_date'], 'invoices_reconciliation_index');
        });
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->foreignId('payment_account_id')->nullable()->constrained('company_payment_integrations')->restrictOnDelete();
            $table->foreignId('membership_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('external_transaction_id')->nullable();
            $table->string('origin', 32)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->decimal('refunded_amount', 12, 2)->nullable();
            $table->unique(['payment_account_id', 'external_transaction_id'], 'payments_account_transaction_unique');
        });
        Schema::table('membership_usages', function (Blueprint $table) {
            $table->foreignId('professional_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('service_order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('origin', 32)->nullable();
            $table->string('usage_status', 32)->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
        });
        foreach (['payment_account_customers', 'membership_checkout_intentions', 'membership_invoices'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->foreign(['company_id', 'payment_account_id'], $name.'_account_scope_fk')
                    ->references(['company_id', 'id'])->on('company_payment_integrations')->restrictOnDelete();
                $table->unique(['company_id', 'id'], $name.'_company_id_unique');
            });
        }
        foreach (['payment_account_customers', 'membership_checkout_intentions'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->foreign(['company_id', 'client_id'], $name.'_client_scope_fk')
                    ->references(['company_id', 'id'])->on('clients')->restrictOnDelete();
            });
        }
        foreach (['membership_checkout_intentions', 'membership_invoices'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->foreign(['company_id', 'customer_membership_id'], $name.'_membership_scope_fk')
                    ->references(['company_id', 'id'])->on('customer_memberships')->restrictOnDelete();
            });
        }
        // These columns are new and NULL for all legacy rows; no data backfill.
        foreach (['customer_memberships', 'membership_payments'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->foreign(['company_id', 'payment_account_id'], $name.'_account_scope_fk')
                    ->references(['company_id', 'id'])->on('company_payment_integrations')->restrictOnDelete();
            });
        }
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->foreign(['company_id', 'membership_invoice_id'], 'membership_payments_invoice_scope_fk')
                ->references(['company_id', 'id'])->on('membership_invoices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new LogicException('Fundação financeira preserva histórico. Reversão exige migration compensatória revisada.');
    }
};
