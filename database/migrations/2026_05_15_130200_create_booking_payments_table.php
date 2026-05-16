<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 40);
            $table->string('status', 20);
            $table->string('payment_type', 20);
            $table->decimal('amount', 10, 2);
            $table->string('external_reference')->unique();
            $table->string('preference_id')->nullable();
            $table->string('external_payment_id')->nullable();
            $table->text('checkout_url')->nullable();
            $table->text('init_point')->nullable();
            $table->text('sandbox_init_point')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'gateway']);
            $table->index(['appointment_id', 'status']);
            $table->index('preference_id');
            $table->index('external_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};
