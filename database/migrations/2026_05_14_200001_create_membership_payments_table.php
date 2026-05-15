<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_membership_id')->constrained('customer_memberships')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_subscription_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('status', 32);
            $table->string('billing_type', 32)->default('unknown');
            $table->date('due_date')->nullable();
            $table->date('cycle_starts_at')->nullable();
            $table->date('cycle_ends_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('invoice_url')->nullable();
            $table->text('pix_qr_code')->nullable();
            $table->text('pix_copy_paste')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'customer_membership_id', 'status']);
            $table->unique(['company_id', 'provider', 'provider_payment_id'], 'membership_payments_provider_payment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_payments');
    }
};
