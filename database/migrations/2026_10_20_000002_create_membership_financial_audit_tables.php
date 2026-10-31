<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_account_id')->constrained('company_payment_integrations')->restrictOnDelete();
            $table->string('external_event_id');
            $table->string('event_type');
            $table->string('resource_type', 32)->nullable();
            $table->string('external_resource_id')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('payload_hash', 64);
            $table->json('safe_payload')->nullable();
            $table->string('processing_status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error_code')->nullable();
            $table->timestamps();
            $table->unique(['payment_account_id', 'external_event_id'], 'webhook_account_event_unique');
            $table->unique(['company_id', 'id'], 'webhook_company_id_unique');
            $table->foreign(['company_id', 'payment_account_id'], 'webhook_account_scope_fk')
                ->references(['company_id', 'id'])->on('company_payment_integrations')->restrictOnDelete();
        });
        Schema::create('subscription_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_webhook_event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('membership_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('original_actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32);
            $table->text('reason');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['company_id', 'customer_membership_id'], 'history_company_membership_index');
            $table->foreign(['company_id', 'customer_membership_id'], 'history_membership_scope_fk')
                ->references(['company_id', 'id'])->on('customer_memberships')->restrictOnDelete();
            $table->foreign(['company_id', 'payment_webhook_event_id'], 'history_event_scope_fk')
                ->references(['company_id', 'id'])->on('payment_webhook_events')->restrictOnDelete();
            $table->foreign(['company_id', 'membership_invoice_id'], 'history_invoice_scope_fk')
                ->references(['company_id', 'id'])->on('membership_invoices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new LogicException('Auditoria financeira não pode ser removida por rollback automático.');
    }
};
